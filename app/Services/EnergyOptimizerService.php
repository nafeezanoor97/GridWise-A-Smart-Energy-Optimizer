<?php

namespace App\Services;

use RuntimeException;

class EnergyOptimizerService
{
    private const SCALE = 100;
    private const EPS = 0.000001;

    public function optimize(
        array $hours,
        array $battery,
        array $directives = []
    ): array {
        $this->validateInput($hours, $battery);

        /*
         * ---------------------------------------------------------
         * 1. Hourly data
         * ---------------------------------------------------------
         */

        $demand = [];
        $solar = [];
        $tariff = [];

        for ($h = 0; $h < 24; $h++) {
            $demand[$h] = (float) $hours[$h]['demand_kwh'];
            $solar[$h] = (float) $hours[$h]['solar_kwh'];
            $tariff[$h] = (float) $hours[$h]['tariff_bdt_per_kwh'];
        }

        /*
         * ---------------------------------------------------------
         * 2. Default constraints
         * ---------------------------------------------------------
         */

        $effectiveSolar = $solar;

        $reserve = array_fill(
            0,
            24,
            (float) $battery['minimum_energy_kwh']
        );

        $noCharge = array_fill(0, 24, false);
        $noDischarge = array_fill(0, 24, false);
        $maxGrid = array_fill(0, 24, null);

        /*
         * ---------------------------------------------------------
         * 3. Apply directives
         * ---------------------------------------------------------
         */

        foreach ($directives as $directive) {

            if (($directive['applies'] ?? false) !== true) {
                continue;
            }

            $type = $directive['directive_type'] ?? null;
            $adjustment = $directive['structured_adjustment'] ?? [];

            switch ($type) {

                case 'solar_reduction':
                    $factor = (float) ($adjustment['factor'] ?? 1);

                    if ($factor < 0 || $factor > 1) {
                        throw new RuntimeException(
                            'Invalid solar reduction factor.'
                        );
                    }

                    foreach (($adjustment['hours'] ?? []) as $hour) {
                        $h = (int) $hour;
                        if ($h >= 0 && $h <= 23) {
                            $effectiveSolar[$h] = $solar[$h] * $factor;
                        }
                    }
                    break;

                case 'minimum_battery_reserve':
                    $minimum = (float) ($adjustment['minimum_energy_kwh'] ?? 0);

                    foreach (($adjustment['hours'] ?? []) as $hour) {
                        $h = (int) $hour;
                        if ($h >= 0 && $h <= 23) {
                            $reserve[$h] = max($reserve[$h], $minimum);
                        }
                    }
                    break;

                case 'no_charge_window':
                    foreach (($adjustment['hours'] ?? []) as $hour) {
                        $h = (int) $hour;
                        if ($h >= 0 && $h <= 23) {
                            $noCharge[$h] = true;
                        }
                    }
                    break;

                case 'no_discharge_window':
                    foreach (($adjustment['hours'] ?? []) as $hour) {
                        $h = (int) $hour;
                        if ($h >= 0 && $h <= 23) {
                            $noDischarge[$h] = true;
                        }
                    }
                    break;

                case 'max_grid_window':
                    $limit = (float) ($adjustment['max_grid_kwh'] ?? 0);

                    foreach (($adjustment['hours'] ?? []) as $hour) {
                        $h = (int) $hour;
                        if ($h < 0 || $h > 23) {
                            continue;
                        }
                        if ($maxGrid[$h] === null) {
                            $maxGrid[$h] = $limit;
                        } else {
                            $maxGrid[$h] = min($maxGrid[$h], $limit);
                        }
                    }
                    break;

                case 'no_op':
                    break;

                default:
                    throw new RuntimeException(
                        "Unsupported directive: {$type}"
                    );
            }
        }

        /*
         * ---------------------------------------------------------
         * 4. Battery configuration
         * ---------------------------------------------------------
         */

        $capacity = (float) $battery['capacity_kwh'];
        $initialEnergy = (float) $battery['initial_energy_kwh'];
        $minimumEnergy = (float) $battery['minimum_energy_kwh'];
        $maxCharge = (float) $battery['max_charge_kwh_per_hour'];
        $maxDischarge = (float) $battery['max_discharge_kwh_per_hour'];

        if ($initialEnergy < $minimumEnergy - self::EPS) {
            throw new RuntimeException(
                'Initial battery energy is below minimum reserve.'
            );
        }

        if ($initialEnergy > $capacity + self::EPS) {
            throw new RuntimeException(
                'Initial battery energy exceeds capacity.'
            );
        }

        /*
         * ---------------------------------------------------------
         * 5. Convert battery energy to integer units (centi-kWh)
         * ---------------------------------------------------------
         */

        $capacityUnits = (int) floor($capacity * self::SCALE + self::EPS);
        $initialUnits = (int) round($initialEnergy * self::SCALE);

        /*
         * ---------------------------------------------------------
         * 6. DP
         * ---------------------------------------------------------
         */

        $INF = PHP_FLOAT_MAX;

        $dp = array_fill(0, $capacityUnits + 1, $INF);
        $dp[$initialUnits] = 0.0;

        $parents = [];

        for ($h = 0; $h < 24; $h++) {

            $nextDp = array_fill(0, $capacityUnits + 1, $INF);
            $parent = array_fill(0, $capacityUnits + 1, -1);

            $chargeUnits = (int) floor($maxCharge * self::SCALE + self::EPS);
            $dischargeUnits = (int) floor($maxDischarge * self::SCALE + self::EPS);

            if ($noCharge[$h]) {
                $chargeUnits = 0;
            }
            if ($noDischarge[$h]) {
                $dischargeUnits = 0;
            }

            $demandUnits = (int) round($demand[$h] * self::SCALE);
            $reserveUnits = (int) ceil($reserve[$h] * self::SCALE - self::EPS);

            for ($before = 0; $before <= $capacityUnits; $before++) {

                if ($dp[$before] >= $INF) {
                    continue;
                }

                $minAfter = max(0, $before - $dischargeUnits);
                $maxAfter = min($capacityUnits, $before + $chargeUnits);

                $minAfter = max($minAfter, $reserveUnits);

                if ($minAfter > $maxAfter) {
                    continue;
                }

                for ($after = $minAfter; $after <= $maxAfter; $after++) {

                    $delta = $after - $before;

                    // Cannot discharge more than demand
                    if ($delta < -$demandUnits) {
                        continue;
                    }

                    // Energy balance: grid = demand - solar + delta
                    $grid = max(
                        0.0,
                        $demand[$h] - $effectiveSolar[$h] + ($delta / self::SCALE)
                    );

                    // Max grid directive
                    if (
                        $maxGrid[$h] !== null &&
                        $grid > $maxGrid[$h] + 0.01
                    ) {
                        continue;
                    }

                    $hourCost = $grid * $tariff[$h];
                    $newCost = $dp[$before] + $hourCost;

                    if ($newCost < $nextDp[$after]) {
                        $nextDp[$after] = $newCost;
                        $parent[$after] = $before;
                    }
                }
            }

            $parents[$h] = $parent;
            $dp = $nextDp;

            $reachable = false;
            for ($energy = 0; $energy <= $capacityUnits; $energy++) {
                if ($dp[$energy] < $INF) {
                    $reachable = true;
                    break;
                }
            }

            if (!$reachable) {
                throw new RuntimeException(
                    "No feasible energy schedule exists at hour {$h}."
                );
            }
        }

        /*
         * ---------------------------------------------------------
         * 7. Final state must equal initial battery energy.
         * ---------------------------------------------------------
         */

        if ($dp[$initialUnits] >= $INF) {
            throw new RuntimeException(
                'No feasible schedule can restore battery to initial energy.'
            );
        }

        /*
         * ---------------------------------------------------------
         * 8. Backtrack
         * ---------------------------------------------------------
         */

        $state = $initialUnits;
        $statesAfter = array_fill(0, 24, 0);
        $statesBefore = array_fill(0, 24, 0);

        for ($h = 23; $h >= 0; $h--) {

            $statesAfter[$h] = $state;

            if (
                !isset($parents[$h]) ||
                !isset($parents[$h][$state]) ||
                $parents[$h][$state] < 0
            ) {
                throw new RuntimeException(
                    "Unable to reconstruct hour {$h}."
                );
            }

            $previous = $parents[$h][$state];
            $statesBefore[$h] = $previous;
            $state = $previous;
        }

        if ($state !== $initialUnits) {
            throw new RuntimeException(
                'Backtracking failed to reach initial battery state.'
            );
        }

        /*
         * ---------------------------------------------------------
         * 9. Build EXACTLY 24 hourly rows
         * ---------------------------------------------------------
         */

        $hourlyPlan = [];

        for ($h = 0; $h < 24; $h++) {

            $beforeUnits = $statesBefore[$h];
            $afterUnits = $statesAfter[$h];

            $beforeEnergy = $beforeUnits / self::SCALE;
            $afterEnergy = $afterUnits / self::SCALE;

            $delta = $afterEnergy - $beforeEnergy;

            $charge = max(0.0, $delta);
            $discharge = max(0.0, -$delta);

            $netRequired = $demand[$h] + $delta;

            $solarUsed = min(
                $effectiveSolar[$h],
                max(0.0, $netRequired)
            );

            $grid = max(0.0, $netRequired - $solarUsed);

            if ($charge > 0.000001) {
                $action = 'charge';
                $batteryKwh = $charge;
            } elseif ($discharge > 0.000001) {
                $action = 'discharge';
                $batteryKwh = $discharge;
            } else {
                $action = 'idle';
                $batteryKwh = 0.0;
            }

            $hourlyPlan[$h] = [
                'hour' => $h,
                'grid_kwh' => $this->round($grid),
                'solar_used_kwh' => $this->round($solarUsed),
                'battery_action' => $action,
                'battery_kwh' => $this->round($batteryKwh),
                'battery_energy_after_kwh' => $this->round($afterEnergy),
            ];
        }

        if (count($hourlyPlan) !== 24) {
            throw new RuntimeException(
                'Internal error: optimizer did not create 24 hourly rows.'
            );
        }

        /*
         * ---------------------------------------------------------
         * 10. Final validation
         * ---------------------------------------------------------
         */

        $this->validateFinalPlan(
            $hourlyPlan,
            $hours,
            $battery,
            $effectiveSolar,
            $reserve,
            $maxGrid,
            $noCharge,
            $noDischarge
        );

        /*
         * ---------------------------------------------------------
         * 11. Totals
         * ---------------------------------------------------------
         */

        $totalGrid = 0.0;
        $totalCost = 0.0;
        $peakGrid = 0.0;

        foreach ($hourlyPlan as $row) {

            $h = (int) $row['hour'];
            $grid = (float) $row['grid_kwh'];

            $totalGrid += $grid;
            $totalCost += $grid * $tariff[$h];
            $peakGrid = max($peakGrid, $grid);
        }

        return [
            'hourly_plan' => $hourlyPlan,
            'total_grid_kwh' => $this->round($totalGrid),
            'total_cost_bdt' => $this->round($totalCost),
            'peak_grid_kwh' => $this->round($peakGrid),
        ];
    }

    /**
     * Final deterministic validation.
     */
    private function validateFinalPlan(
        array $plan,
        array $hours,
        array $battery,
        array $effectiveSolar,
        array $reserve,
        array $maxGrid,
        array $noCharge,
        array $noDischarge
    ): void {

        if (count($plan) !== 24) {
            throw new RuntimeException(
                'hourly_plan must contain exactly 24 entries.'
            );
        }

        $initialEnergy = (float) $battery['initial_energy_kwh'];
        $capacity = (float) $battery['capacity_kwh'];
        $maxCharge = (float) $battery['max_charge_kwh_per_hour'];
        $maxDischarge = (float) $battery['max_discharge_kwh_per_hour'];

        $previousEnergy = $initialEnergy;

        for ($h = 0; $h < 24; $h++) {

            if (!isset($plan[$h])) {
                throw new RuntimeException(
                    "Missing hourly plan entry for hour {$h}."
                );
            }

            $row = $plan[$h];

            if ((int) $row['hour'] !== $h) {
                throw new RuntimeException(
                    "Invalid hour ordering at hour {$h}."
                );
            }

            $grid = (float) $row['grid_kwh'];
            $solarUsed = (float) $row['solar_used_kwh'];
            $batteryKwh = (float) $row['battery_kwh'];
            $afterEnergy = (float) $row['battery_energy_after_kwh'];
            $action = $row['battery_action'];

            if ($grid < -0.000001) {
                throw new RuntimeException("Negative grid at hour {$h}.");
            }
            if ($solarUsed < -0.000001) {
                throw new RuntimeException("Negative solar usage at hour {$h}.");
            }
            if ($batteryKwh < -0.000001) {
                throw new RuntimeException("Negative battery movement at hour {$h}.");
            }

            if ($solarUsed > $effectiveSolar[$h] + 0.01) {
                throw new RuntimeException(
                    "Solar availability exceeded at hour {$h}."
                );
            }

            if (!in_array($action, ['charge', 'discharge', 'idle'], true)) {
                throw new RuntimeException(
                    "Invalid battery action at hour {$h}."
                );
            }

            if ($action === 'charge' && $batteryKwh > $maxCharge + 0.01) {
                throw new RuntimeException(
                    "Maximum charge rate exceeded at hour {$h}."
                );
            }

            if ($action === 'discharge' && $batteryKwh > $maxDischarge + 0.01) {
                throw new RuntimeException(
                    "Maximum discharge rate exceeded at hour {$h}."
                );
            }

            if ($noCharge[$h] && $action === 'charge' && $batteryKwh > 0.01) {
                throw new RuntimeException(
                    "Charging forbidden at hour {$h}."
                );
            }

            if ($noDischarge[$h] && $action === 'discharge' && $batteryKwh > 0.01) {
                throw new RuntimeException(
                    "Discharging forbidden at hour {$h}."
                );
            }

            if ($action === 'charge') {
                $expected = $previousEnergy + $batteryKwh;
            } elseif ($action === 'discharge') {
                $expected = $previousEnergy - $batteryKwh;
            } else {
                $expected = $previousEnergy;
            }

            if (abs($expected - $afterEnergy) > 0.01) {
                throw new RuntimeException(
                    "Battery transition invalid at hour {$h}."
                );
            }

            if ($afterEnergy < $reserve[$h] - 0.01) {
                throw new RuntimeException(
                    "Battery reserve violated at hour {$h}."
                );
            }

            if ($afterEnergy > $capacity + 0.01) {
                throw new RuntimeException(
                    "Battery capacity exceeded at hour {$h}."
                );
            }

            $d = (float) $hours[$h]['demand_kwh'];
            $charge = $action === 'charge' ? $batteryKwh : 0.0;
            $discharge = $action === 'discharge' ? $batteryKwh : 0.0;

            $left = $grid + $solarUsed + $discharge;
            $right = $d + $charge;

            if (abs($left - $right) > 0.01) {
                throw new RuntimeException(
                    "Energy balance violated at hour {$h}. " .
                    "Left={$left}, Right={$right}"
                );
            }

            if ($maxGrid[$h] !== null && $grid > $maxGrid[$h] + 0.01) {
                throw new RuntimeException(
                    "Maximum grid limit violated at hour {$h}."
                );
            }

            $previousEnergy = $afterEnergy;
        }

        if (abs($previousEnergy - $initialEnergy) > 0.01) {
            throw new RuntimeException(
                'Final battery energy does not equal initial energy.'
            );
        }
    }

    /**
     * Input validation.
     */
    private function validateInput(
        array $hours,
        array $battery
    ): void {

        if (count($hours) !== 24) {
            throw new RuntimeException(
                'Exactly 24 hours are required.'
            );
        }

        for ($h = 0; $h < 24; $h++) {

            if (!isset($hours[$h]) || (int) $hours[$h]['hour'] !== $h) {
                throw new RuntimeException(
                    'Hours must be exactly 0 through 23.'
                );
            }

            foreach (['demand_kwh', 'solar_kwh', 'tariff_bdt_per_kwh'] as $field) {
                if (!isset($hours[$h][$field]) || !is_numeric($hours[$h][$field])) {
                    throw new RuntimeException("Invalid hourly field: {$field}");
                }
                if ((float) $hours[$h][$field] < 0) {
                    throw new RuntimeException("{$field} cannot be negative.");
                }
            }
        }

        foreach ([
                     'capacity_kwh',
                     'initial_energy_kwh',
                     'minimum_energy_kwh',
                     'max_charge_kwh_per_hour',
                     'max_discharge_kwh_per_hour',
                 ] as $field) {

            if (!isset($battery[$field]) || !is_numeric($battery[$field])) {
                throw new RuntimeException("Invalid battery field: {$field}");
            }
            if ((float) $battery[$field] < 0) {
                throw new RuntimeException("{$field} cannot be negative.");
            }
        }
    }

    /**
     * Round numeric output.
     */
    private function round(float $value): float
    {
        $value = round($value, 2);
        if (abs($value) < 0.000001) {
            return 0.0;
        }
        return $value;
    }
}
