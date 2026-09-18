<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class OptimizeEnergyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scenario_id' => [
                'required',
                'string',
                'max:255',
            ],

            'operator_notes' => [
                'required',
                'array',
                'min:1',
                'max:3',
            ],

            'operator_notes.*' => [
                'required',
                'string',
                'min:1',
                'max:2000',
            ],

            'hours' => [
                'required',
                'array',
                'size:24',
            ],

            'hours.*' => [
                'required',
                'array',
            ],

            'hours.*.hour' => [
                'required',
                'integer',
                'between:0,23',
            ],

            'hours.*.demand_kwh' => [
                'required',
                'numeric',
                'min:0',
            ],

            'hours.*.solar_kwh' => [
                'required',
                'numeric',
                'min:0',
            ],

            'hours.*.tariff_bdt_per_kwh' => [
                'required',
                'numeric',
                'min:0',
            ],

            'battery' => [
                'required',
                'array',
            ],

            'battery.capacity_kwh' => [
                'required',
                'numeric',
                'min:0',
            ],

            'battery.initial_energy_kwh' => [
                'required',
                'numeric',
                'min:0',
            ],

            'battery.minimum_energy_kwh' => [
                'required',
                'numeric',
                'min:0',
            ],

            'battery.max_charge_kwh_per_hour' => [
                'required',
                'numeric',
                'min:0',
            ],

            'battery.max_discharge_kwh_per_hour' => [
                'required',
                'numeric',
                'min:0',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $hours = $this->input('hours', []);

                if (count($hours) === 24) {
                    $hourValues = array_map(
                        fn ($item) => $item['hour'] ?? null,
                        $hours
                    );

                    $expected = range(0, 23);

                    sort($hourValues);
                    $sortedExpected = $expected;

                    if ($hourValues !== $sortedExpected) {
                        $validator->errors()->add(
                            'hours',
                            'hours must contain each integer from 0 through 23 exactly once.'
                        );
                    }
                }

                $battery = $this->input('battery', []);

                if (
                    isset(
                        $battery['capacity_kwh'],
                        $battery['initial_energy_kwh']
                    )
                    &&
                    $battery['initial_energy_kwh'] >
                    $battery['capacity_kwh']
                ) {
                    $validator->errors()->add(
                        'battery.initial_energy_kwh',
                        'Initial battery energy cannot exceed capacity.'
                    );
                }

                if (
                    isset(
                        $battery['capacity_kwh'],
                        $battery['minimum_energy_kwh']
                    )
                    &&
                    $battery['minimum_energy_kwh'] >
                    $battery['capacity_kwh']
                ) {
                    $validator->errors()->add(
                        'battery.minimum_energy_kwh',
                        'Minimum battery energy cannot exceed capacity.'
                    );
                }
            },
        ];
    }
}
