<?php

namespace App\Http\Controllers;

use App\Http\Requests\OptimizeEnergyRequest;
use App\Services\DirectiveValidatorService;
use App\Services\EnergyOptimizerService;
use App\Services\LlmInterpreterService;
use App\Services\ScheduleValidatorService;
use Illuminate\Http\JsonResponse;
use Throwable;

class EnergyOptimizationController extends Controller
{
    public function __construct(
        private readonly LlmInterpreterService $llm,
        private readonly DirectiveValidatorService $directiveValidator,
        private readonly EnergyOptimizerService $optimizer,
        private readonly ScheduleValidatorService $scheduleValidator,
    ) {
    }

    public function optimize(
        OptimizeEnergyRequest $request
    ): JsonResponse {
        try {

            /*
             * =====================================================
             * 1. Validate incoming API request
             * =====================================================
             */

            $data = $request->validated();

            /*
             * =====================================================
             * 2. Gemini interprets operator notes
             * =====================================================
             */

            $rawDirectives = $this->llm->interpret(
                $data['operator_notes'],
                $data['battery']
            );

            /*
             * =====================================================
             * 3. Deterministic validation of Gemini output
             * =====================================================
             */

            $directives = $this->directiveValidator->validate(
                $rawDirectives,
                $data['operator_notes'],
                $data['battery']
            );

            /*
             * =====================================================
             * 4. Run deterministic energy optimizer
             *
             * Returns:
             *   [
             *     'hourly_plan'    => [...24 rows...],
             *     'total_grid_kwh' => float,
             *     'total_cost_bdt' => float,
             *     'peak_grid_kwh'  => float,
             *   ]
             * =====================================================
             */

            $result = $this->optimizer->optimize(
                $data['hours'],
                $data['battery'],
                $directives
            );

            /*
             * Extract the 24-hour plan once.
             */
            $hourlyPlan = $result['hourly_plan'];

            /*
             * =====================================================
             * 5. Replay and validate final schedule
             *
             * Pass the BARE 24-hour plan, not the full result.
             * =====================================================
             */

            $this->scheduleValidator->validate(
                $data['hours'],
                $data['battery'],
                $directives,
                $hourlyPlan          // ✅ fixed
            );

            /*
             * =====================================================
             * 6. Recalculate totals from the FINAL hourly plan
             * =====================================================
             */

            $totalGrid = 0.0;
            $totalCost = 0.0;
            $peakGrid = 0.0;

            foreach ($hourlyPlan as $entry) {   // ✅ iterate hourly_plan

                $hour = (int) $entry['hour'];

                $grid = (float) $entry['grid_kwh'];

                $tariff = (float) $data['hours'][$hour]['tariff_bdt_per_kwh'];

                $totalGrid += $grid;
                $totalCost += $grid * $tariff;
                $peakGrid  = max($peakGrid, $grid);
            }

            /*
             * =====================================================
             * 7. Final API response
             * =====================================================
             */

            return response()->json([

                'scenario_id' => $data['scenario_id'],

                'directive_interpretation' => $directives,

                'hourly_plan' => $hourlyPlan,

                'total_grid_kwh' => round($totalGrid, 6),

                'total_cost_bdt' => round($totalCost, 6),

                'peak_grid_kwh' => round($peakGrid, 6),

                'plan_summary' =>
                    'The plan applies all validated operator directives, ' .
                    'satisfies the energy and battery constraints, ' .
                    'and minimizes grid electricity cost.',
            ]);

        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'error'   => 'Optimization failed.',
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),   // helpful for debugging
                'line'    => $e->getLine(),   // helpful for debugging
            ], 500);
        }
    }
}
