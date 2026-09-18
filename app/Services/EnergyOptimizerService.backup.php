<?php

namespace App\Services;

use RuntimeException;

class EnergyOptimizerService
{
    public function optimize(
        array $hours,
        array $battery,
        array $directives
    ): array {
        /*
         * Temporary implementation.
         *
         * Actual minimum-cost optimizer will be added next.
         */

        if (count($hours) !== 24) {
            throw new RuntimeException(
                'Scenario must contain exactly 24 hours.'
            );
        }

        $plan = [];

        foreach ($hours as $hour) {
            $h = (int) $hour['hour'];

            $demand = (float) $hour['demand_kwh'];
            $solar = (float) $hour['solar_kwh'];

            /*
             * Temporary simple plan:
             * use solar first, grid for the remaining demand.
             *
             * No battery movement yet.
             */
            $solarUsed = min($solar, $demand);
            $grid = max(0, $demand - $solarUsed);

            $plan[] = [
                'hour' => $h,
                'grid_kwh' => $grid,
                'solar_used_kwh' => $solarUsed,
                'battery_kwh' => 0,
                'battery_energy_after_kwh' =>
                    (float) $battery['initial_energy_kwh'],
                'battery_action' => 'idle',
            ];
        }

        return $plan;
    }
}
