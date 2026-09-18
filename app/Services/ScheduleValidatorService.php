<?php

namespace App\Services;

use RuntimeException;

class ScheduleValidatorService
{
    private const TOLERANCE = 0.01;

    public function validate(
        array $hours,
        array $battery,
        array $directives,
        array $plan
    ): void {

        /*
         * =========================================================
         * 0. Normalize input
         *
         * The optimizer returns:
         *
         *   [
         *     'hourly_plan'    => [ ... 24 rows ... ],
         *     'total_grid_kwh' => ...,
         *     'total_cost_bdt' => ...,
         *     'peak_grid_kwh'  => ...,
         *   ]
         *
         * This validator expects the bare 24-hour plan.
         * Accept both shapes so callers can pass either one.
         * =========================================================
         */
        if (
            isset($plan['hourly_plan']) &&
            is_array($plan['hourly_plan'])
        ) {
            $plan = $plan['hourly_plan'];
        }

        /*
         * =========================================================
         * 1. Validate scenario hours
         * =========================================================
         */

        if (count($hours) !== 24) {
            throw new RuntimeException(
                'Scenario hours must contain exactly 24 entries.'
            );
        }

        foreach ($hours as $index => $hour) {
            if (!is_array($hour)) {
                throw new RuntimeException(
                    "Scenario hour {$index} must be an object."
                );
            }

            if (!isset($hour['hour'])) {
                throw new RuntimeException(
                    "Scenario hour {$index} is missing hour."
                );
            }

            if ((int) $hour['hour'] !== $index) {
                throw new RuntimeException(
                    'Scenario hours must be ordered from 0 through 23.'
                );
            }

            foreach ([
                         'demand_kwh',
                         'solar_kwh',
                         'tariff_bdt_per_kwh',
                     ] as $field) {
                if (!isset($hour[$field])) {
                    throw new RuntimeException(
                        "Scenario hour {$index} is missing {$field}."
                    );
                }

                if (
                    !is_numeric($hour[$field]) ||
                    !is_finite((float) $hour[$field])
                ) {
                    throw new RuntimeException(
                        "Scenario hour {$index} has invalid {$field}."
                    );
                }
            }
        }

        /*
         * =========================================================
         * 2. Validate battery configuration
         * =========================================================
         */

        $capacity = $this->number(
            $battery['capacity_kwh'] ?? null,
            'battery capacity'
        );

        $initialEnergy = $this->number(
            $battery['initial_energy_kwh'] ?? null,
            'initial battery energy'
        );

        $minimumEnergy = $this->number(
            $battery['minimum_energy_kwh'] ?? null,
            'minimum battery energy'
        );

        $maxCharge = $this->number(
            $battery['max_charge_kwh_per_hour'] ?? null,
            'maximum charge rate'
        );

        $maxDischarge = $this->number(
            $battery['max_discharge_kwh_per_hour'] ?? null,
            'maximum discharge rate'
        );

        if ($capacity < 0) {
            throw new RuntimeException(
                'Battery capacity cannot be negative.'
            );
        }

        if ($initialEnergy < 0 || $initialEnergy > $capacity) {
            throw new RuntimeException(
                'Initial battery energy is outside battery capacity.'
            );
        }

        if ($minimumEnergy < 0 || $minimumEnergy > $capacity) {
            throw new RuntimeException(
                'Minimum battery energy is invalid.'
            );
        }

        if ($maxCharge < 0 || $maxDischarge < 0) {
            throw new RuntimeException(
                'Battery charge/discharge rates cannot be negative.'
            );
        }

        /*
         * =========================================================
         * 3. Build effective directive constraints
         * =========================================================
         */

        $effectiveSolar = [];

        for ($h = 0; $h < 24; $h++) {
            $effectiveSolar[$h] = (float) $hours[$h]['solar_kwh'];
        }

        $minimumReserve = array_fill(0, 24, $minimumEnergy);
        $noCharge = array_fill(0, 24, false);
        $noDischarge = array_fill(0, 24, false);
        $maxGrid = array_fill(0, 24, null);

        foreach ($directives as $directive) {

            if (($directive['applies'] ?? false) !== true) {
                continue;
            }

            $type = $directive['directive_type'] ?? null;
            $adjustment = $directive['structured_adjustment'] ?? null;

            if (!is_array($adjustment)) {
                throw new RuntimeException(
                    'Directive adjustment must be an object.'
                );
            }

            $directiveHours = $adjustment['hours'] ?? [];

            if (!is_array($directiveHours)) {
                throw new RuntimeException(
                    'Directive hours must be an array.'
                );
            }

            foreach ($directiveHours as $h) {

                $h = (int) $h;

                if ($h < 0 || $h > 23) {
                    throw new RuntimeException(
                        'Directive hour must be between 0 and 23.'
                    );
                }

                switch ($type) {

                    case 'solar_reduction':
                        $factor = (float) $adjustment['factor'];
                        $effectiveSolar[$h] *= $factor;
                        break;

                    case 'minimum_battery_reserve':
                        $reserve = (float) $adjustment['minimum_energy_kwh'];
                        $minimumReserve[$h] = max(
                            $minimumReserve[$h],
                            $reserve
                        );
                        break;

                    case 'no_charge_window':
                        $noCharge[$h] = true;
                        break;

                    case 'no_discharge_window':
                        $noDischarge[$h] = true;
                        break;

                    case 'max_grid_window':
                        $cap = (float) $adjustment['max_grid_kwh'];
                        if (
                            $maxGrid[$h] === null ||
                            $cap < $maxGrid[$h]
                        ) {
                            $maxGrid[$h] = $cap;
                        }
                        break;

                    case 'no_op':
                        break;

                    default:
                        throw new RuntimeException(
                            "Unsupported directive type: {$type}"
                        );
                }
            }
        }

        /*
         * =========================================================
         * 4. Validate hourly plan
         * =========================================================
         */

        if (count($plan) !== 24) {
            throw new RuntimeException(
                'hourly_plan must contain exactly 24 entries.'
            );
        }

        $planHours = [];

        foreach ($plan as $index => $entry) {

            if (!is_array($entry)) {
                throw new RuntimeException(
                    "hourly_plan entry {$index} must be an object."
                );
            }

            if (!array_key_exists('hour', $entry)) {
                throw new RuntimeException(
                    "hourly_plan entry {$index} is missing hour."
                );
            }

            $hour = $entry['hour'];

            if (
                !is_int($hour) &&
                !(
                    is_string($hour) &&
                    preg_match('/^\d+$/', $hour)
                )
            ) {
                throw new RuntimeException(
                    "hourly_plan entry {$index} has invalid hour."
                );
            }

            $hour = (int) $hour;

            if ($hour < 0 || $hour > 23) {
                throw new RuntimeException(
                    "hourly_plan hour must be between 0 and 23."
                );
            }

            $planHours[] = $hour;
        }

        $sortedPlanHours = $planHours;
        sort($sortedPlanHours, SORT_NUMERIC);

        if ($sortedPlanHours !== range(0, 23)) {
            throw new RuntimeException(
                'hourly_plan must contain hours 0 through 23 exactly once.'
            );
        }

        /*
         * =========================================================
         * 5. Replay battery state and validate every hour
         * =========================================================
         */

        $previousEnergy = $initialEnergy;

        foreach ($plan as $entry) {

            $h = (int) $entry['hour'];

            foreach ([
                         'grid_kwh',
                         'solar_used_kwh',
                         'battery_kwh',
                         'battery_energy_after_kwh',
                         'battery_action',
                     ] as $field) {
                if (!array_key_exists($field, $entry)) {
                    throw new RuntimeException(
                        "Hour {$h} is missing {$field}."
                    );
                }
            }

            $grid = $this->number($entry['grid_kwh'], "hour {$h} grid");
            $solar = $this->number($entry['solar_used_kwh'], "hour {$h} solar");
            $batteryKwh = $this->number($entry['battery_kwh'], "hour {$h} battery");
            $after = $this->number(
                $entry['battery_energy_after_kwh'],
                "hour {$h} battery after"
            );
            $action = $entry['battery_action'];
            $demand = (float) $hours[$h]['demand_kwh'];

            if ($grid < -self::TOLERANCE) {
                throw new RuntimeException(
                    "Hour {$h} grid_kwh cannot be negative."
                );
            }

            if ($solar < -self::TOLERANCE) {
                throw new RuntimeException(
                    "Hour {$h} solar_used_kwh cannot be negative."
                );
            }

            if ($batteryKwh < -self::TOLERANCE) {
                throw new RuntimeException(
                    "Hour {$h} battery_kwh cannot be negative."
                );
            }

            if ($after < -self::TOLERANCE) {
                throw new RuntimeException(
                    "Hour {$h} battery energy cannot be negative."
                );
            }

            if ($solar > $effectiveSolar[$h] + self::TOLERANCE) {
                throw new RuntimeException(
                    "Hour {$h} exceeds effective solar availability."
                );
            }

            if (
                !in_array(
                    $action,
                    ['idle', 'charge', 'discharge'],
                    true
                )
            ) {
                throw new RuntimeException(
                    "Hour {$h} has invalid battery action."
                );
            }

            if (
                $action === 'idle' &&
                abs($batteryKwh) > self::TOLERANCE
            ) {
                throw new RuntimeException(
                    "Hour {$h} idle action must have zero battery_kwh."
                );
            }

            if ($action === 'charge') {
                $expected = $previousEnergy + $batteryKwh;
            } elseif ($action === 'discharge') {
                $expected = $previousEnergy - $batteryKwh;
            } else {
                $expected = $previousEnergy;
            }

            if (abs($expected - $after) > self::TOLERANCE) {
                throw new RuntimeException(
                    "Hour {$h} battery transition is invalid."
                );
            }

            if ($after < $minimumReserve[$h] - self::TOLERANCE) {
                throw new RuntimeException(
                    "Hour {$h} violates minimum battery reserve."
                );
            }

            if ($after > $capacity + self::TOLERANCE) {
                throw new RuntimeException(
                    "Hour {$h} exceeds battery capacity."
                );
            }

            if (
                $action === 'charge' &&
                $batteryKwh > $maxCharge + self::TOLERANCE
            ) {
                throw new RuntimeException(
                    "Hour {$h} exceeds maximum charge rate."
                );
            }

            if (
                $action === 'discharge' &&
                $batteryKwh > $maxDischarge + self::TOLERANCE
            ) {
                throw new RuntimeException(
                    "Hour {$h} exceeds maximum discharge rate."
                );
            }

            if (
                $noCharge[$h] &&
                $action === 'charge' &&
                $batteryKwh > self::TOLERANCE
            ) {
                throw new RuntimeException(
                    "Hour {$h} violates no-charge directive."
                );
            }

            if (
                $noDischarge[$h] &&
                $action === 'discharge' &&
                $batteryKwh > self::TOLERANCE
            ) {
                throw new RuntimeException(
                    "Hour {$h} violates no-discharge directive."
                );
            }

            if (
                $maxGrid[$h] !== null &&
                $grid > $maxGrid[$h] + self::TOLERANCE
            ) {
                throw new RuntimeException(
                    "Hour {$h} violates maximum grid constraint."
                );
            }

            $charge = $action === 'charge' ? $batteryKwh : 0.0;
            $discharge = $action === 'discharge' ? $batteryKwh : 0.0;

            $lhs = $grid + $solar + $discharge;
            $rhs = $demand + $charge;

            if (abs($lhs - $rhs) > self::TOLERANCE) {
                throw new RuntimeException(
                    "Hour {$h} violates energy balance."
                );
            }

            $previousEnergy = $after;
        }

        /*
         * =========================================================
         * 6. End-of-day battery neutrality
         * =========================================================
         */

        if (
            abs($previousEnergy - $initialEnergy) >
            self::TOLERANCE
        ) {
            throw new RuntimeException(
                'End-of-day battery neutrality failed.'
            );
        }
    }

    /**
     * Numeric helper.
     */
    private function number(
        mixed $value,
        string $name
    ): float {
        if (
            !is_numeric($value) ||
            !is_finite((float) $value)
        ) {
            throw new RuntimeException(
                "{$name} must be a finite number."
            );
        }

        return (float) $value;
    }
}
