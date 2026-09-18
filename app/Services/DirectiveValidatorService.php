<?php

namespace App\Services;

use RuntimeException;

class DirectiveValidatorService
{
    private const ALLOWED_TYPES = [
        'solar_reduction',
        'minimum_battery_reserve',
        'no_charge_window',
        'no_discharge_window',
        'max_grid_window',
        'no_op',
    ];

    /**
     * Canonical adjustment keys for each directive type.
     * After normalization, every directive will have exactly these keys.
     */
    private const REQUIRED_ADJUSTMENT_KEYS = [
        'solar_reduction'         => ['hours', 'factor'],
        'minimum_battery_reserve' => ['hours', 'minimum_energy_kwh'],
        'no_charge_window'        => ['hours'],
        'no_discharge_window'     => ['hours'],
        'max_grid_window'         => ['hours', 'max_grid_kwh'],
        'no_op'                   => [],
    ];

    /**
     * Alias map — LLMs emit different names for the same concept.
     * Keys are canonical; values are accepted synonyms.
     */
    private const KEY_ALIASES = [
        'hours' => [
            'hour',
            'hours_list',
            'hour_list',
            'time',
            'times',
            'window',
            'windows',
        ],
        'factor' => [
            'reduction_factor',
            'solar_factor',
            'multiplier',
            'ratio',
        ],
        'minimum_energy_kwh' => [
            'minimum_battery_reserve',
            'reserve_kwh',
            'min_energy_kwh',
            'minimum_kwh',
            'reserve',
            'battery_reserve',
            'battery_reserve_kwh',
            'minimum_battery_energy_kwh',
        ],
        'max_grid_kwh' => [
            'max_grid',
            'grid_limit_kwh',
            'grid_cap_kwh',
            'grid_cap',
            'max_grid_limit_kwh',
        ],
    ];

    public function validate(
        array $directives,
        array $notes,
        array $battery
    ): array {
        /*
         * ---------------------------------------------------------
         * 1. Every note must have exactly one directive
         * ---------------------------------------------------------
         */

        if (count($directives) !== count($notes)) {
            throw new RuntimeException(
                'Every operator note must have exactly one interpretation.'
            );
        }

        /*
         * ---------------------------------------------------------
         * 2. Basic battery validation
         * ---------------------------------------------------------
         */

        $capacity = $battery['capacity_kwh'] ?? null;

        if (
            !is_numeric($capacity) ||
            !is_finite((float) $capacity) ||
            (float) $capacity < 0
        ) {
            throw new RuntimeException(
                'Invalid battery capacity.'
            );
        }

        $capacity = (float) $capacity;

        $normalized = [];

        /*
         * ---------------------------------------------------------
         * 3. Validate every directive
         * ---------------------------------------------------------
         */

        foreach ($directives as $index => $directive) {

            if (!is_array($directive)) {
                throw new RuntimeException(
                    "Directive at index {$index} must be an object."
                );
            }

            /*
             * -----------------------------------------------------
             * Required fields
             * -----------------------------------------------------
             */

            $requiredFields = [
                'note_index',
                'applies',
                'directive_type',
                'structured_adjustment',
                'explanation',
            ];

            foreach ($requiredFields as $field) {
                if (!array_key_exists($field, $directive)) {
                    throw new RuntimeException(
                        "Directive at index {$index} is missing field: {$field}."
                    );
                }
            }

            /*
             * -----------------------------------------------------
             * note_index
             * -----------------------------------------------------
             */

            $noteIndex = $directive['note_index'];

            if (
                !is_int($noteIndex) ||
                $noteIndex !== $index
            ) {
                throw new RuntimeException(
                    "Directive note_index must be {$index}."
                );
            }

            /*
             * -----------------------------------------------------
             * applies
             * -----------------------------------------------------
             */

            $applies = $directive['applies'];

            if (!is_bool($applies)) {
                throw new RuntimeException(
                    "Directive applies must be boolean at index {$index}."
                );
            }

            /*
             * -----------------------------------------------------
             * directive_type
             * -----------------------------------------------------
             */

            $type = $directive['directive_type'];

            if (
                !is_string($type) ||
                !in_array($type, self::ALLOWED_TYPES, true)
            ) {
                throw new RuntimeException(
                    "Unsupported directive type at index {$index}."
                );
            }

            /*
             * -----------------------------------------------------
             * explanation
             * -----------------------------------------------------
             */

            if (
                !is_string($directive['explanation']) ||
                trim($directive['explanation']) === ''
            ) {
                throw new RuntimeException(
                    "Directive explanation must be a non-empty string at index {$index}."
                );
            }

            /*
             * -----------------------------------------------------
             * NO OP
             * -----------------------------------------------------
             */

            if ($type === 'no_op') {

                if ($applies !== false) {
                    throw new RuntimeException(
                        "no_op directive must have applies=false at index {$index}."
                    );
                }

                if ($directive['structured_adjustment'] !== null) {
                    throw new RuntimeException(
                        "no_op directive must have structured_adjustment=null at index {$index}."
                    );
                }

                $normalized[] = [
                    'note_index' => $noteIndex,
                    'applies' => false,
                    'directive_type' => 'no_op',
                    'structured_adjustment' => null,
                    'explanation' => trim($directive['explanation']),
                ];

                continue;
            }

            /*
             * -----------------------------------------------------
             * NON NO-OP
             * -----------------------------------------------------
             */

            if ($applies !== true) {
                throw new RuntimeException(
                    "Non-no_op directive must have applies=true at index {$index}."
                );
            }

            $adjustment = $directive['structured_adjustment'];

            if (!is_array($adjustment)) {
                throw new RuntimeException(
                    "structured_adjustment must be an object at index {$index}."
                );
            }

            /*
             * -----------------------------------------------------
             * Normalize adjustment keys (aliases → canonical)
             * -----------------------------------------------------
             */

            $adjustment = $this->normalizeAdjustmentKeys(
                $adjustment,
                $index
            );

            /*
             * -----------------------------------------------------
             * Hours
             * -----------------------------------------------------
             */

            if (!array_key_exists('hours', $adjustment)) {
                throw new RuntimeException(
                    "Directive is missing hours at index {$index}."
                );
            }

            $hours = $adjustment['hours'];

            if (!is_array($hours)) {
                throw new RuntimeException(
                    "Directive hours must be an array at index {$index}."
                );
            }

            $normalizedHours = [];

            foreach ($hours as $hour) {

                if (
                    !is_int($hour) &&
                    !(
                        is_string($hour) &&
                        preg_match('/^-?\d+$/', $hour)
                    )
                ) {
                    throw new RuntimeException(
                        "Directive hours must contain integers at index {$index}."
                    );
                }

                $hour = (int) $hour;

                if ($hour < 0 || $hour > 23) {
                    throw new RuntimeException(
                        "Directive hour must be between 0 and 23 at index {$index}."
                    );
                }

                $normalizedHours[] = $hour;
            }

            if (
                count($normalizedHours) !==
                count(array_unique($normalizedHours))
            ) {
                throw new RuntimeException(
                    "Directive hours must be unique at index {$index}."
                );
            }

            $sortedHours = $normalizedHours;
            sort($sortedHours, SORT_NUMERIC);

            if ($normalizedHours !== $sortedHours) {
                throw new RuntimeException(
                    "Directive hours must be ascending at index {$index}."
                );
            }

            $adjustment['hours'] = $normalizedHours;

            /*
             * -----------------------------------------------------
             * Directive-specific validation
             * -----------------------------------------------------
             */

            switch ($type) {

                case 'solar_reduction':

                    $factor = $adjustment['factor'] ?? null;

                    if (
                        !is_numeric($factor) ||
                        !is_finite((float) $factor)
                    ) {
                        throw new RuntimeException(
                            "Solar reduction factor must be a finite number at index {$index}."
                        );
                    }

                    $factor = (float) $factor;

                    if ($factor < 0 || $factor > 1) {
                        throw new RuntimeException(
                            "Solar reduction factor must be between 0 and 1 at index {$index}."
                        );
                    }

                    $adjustment['factor'] = $factor;

                    break;

                case 'minimum_battery_reserve':

                    $reserve =
                        $adjustment['minimum_energy_kwh'] ?? null;

                    if (
                        !is_numeric($reserve) ||
                        !is_finite((float) $reserve)
                    ) {
                        throw new RuntimeException(
                            "Battery reserve must be a finite number at index {$index}."
                        );
                    }

                    $reserve = (float) $reserve;

                    if ($reserve < 0) {
                        throw new RuntimeException(
                            "Battery reserve cannot be negative at index {$index}."
                        );
                    }

                    if ($reserve > $capacity) {
                        throw new RuntimeException(
                            "Battery reserve cannot exceed battery capacity at index {$index}."
                        );
                    }

                    $adjustment['minimum_energy_kwh'] = $reserve;

                    break;

                case 'max_grid_window':

                    $maxGrid =
                        $adjustment['max_grid_kwh'] ?? null;

                    if (
                        !is_numeric($maxGrid) ||
                        !is_finite((float) $maxGrid)
                    ) {
                        throw new RuntimeException(
                            "Grid cap must be a finite number at index {$index}."
                        );
                    }

                    $maxGrid = (float) $maxGrid;

                    if ($maxGrid < 0) {
                        throw new RuntimeException(
                            "Grid cap cannot be negative at index {$index}."
                        );
                    }

                    $adjustment['max_grid_kwh'] = $maxGrid;

                    break;

                case 'no_charge_window':
                    break;

                case 'no_discharge_window':
                    break;
            }

            /*
             * -----------------------------------------------------
             * Ensure only canonical keys survive
             * -----------------------------------------------------
             */

            $adjustment = $this->keepOnlyCanonicalKeys(
                $adjustment,
                $type
            );

            $normalized[] = [
                'note_index' => $noteIndex,
                'applies' => true,
                'directive_type' => $type,
                'structured_adjustment' => $adjustment,
                'explanation' => trim($directive['explanation']),
            ];
        }

        return $normalized;
    }

    /**
     * Translate aliased keys to canonical names.
     */
    private function normalizeAdjustmentKeys(
        array $adjustment,
        int|string $index
    ): array {
        $normalized = [];

        foreach ($adjustment as $key => $value) {

            $canonical = $this->canonicalKey($key);

            if ($canonical === null) {
                // Keep unknown keys — they won't be used downstream,
                // but we don't want to silently lose data.
                $normalized[$key] = $value;
                continue;
            }

            // If both an alias and the canonical key exist,
            // the canonical key wins (first-write policy is fine).
            if (!array_key_exists($canonical, $normalized)) {
                $normalized[$canonical] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Map a key (canonical or alias) to its canonical form, or null.
     */
    private function canonicalKey(string $key): ?string
    {
        foreach (self::KEY_ALIASES as $canonical => $aliases) {
            if ($key === $canonical) {
                return $canonical;
            }
            if (in_array($key, $aliases, true)) {
                return $canonical;
            }
        }

        return null;
    }

    /**
     * Remove any keys not required for this directive type.
     * Prevents alias leakage into the optimizer.
     */
    private function keepOnlyCanonicalKeys(
        array $adjustment,
        string $type
    ): array {
        $allowed = self::REQUIRED_ADJUSTMENT_KEYS[$type] ?? [];

        $filtered = [];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $adjustment)) {
                $filtered[$key] = $adjustment[$key];
            }
        }

        return $filtered;
    }
}
