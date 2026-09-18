<?php

namespace App\Http\Controllers;

use App\Http\Requests\OptimizeEnergyRequest;
use App\Services\EnergyOptimizerService;
use Illuminate\Http\JsonResponse;

class EnergyController extends Controller
{
    public function optimize(
        OptimizeEnergyRequest $request,
        EnergyOptimizerService $optimizer
    ): JsonResponse {
        $data = $request->validated();

        $result = $optimizer->optimize(
            $data['hours'],
            $data['battery'],
            $data['directives'] ?? []
        );

        // DEBUG CHECK
        if (!isset($result['hourly_plan'])) {
            return response()->json([
                'error' => 'Optimization failed.',
                'message' => 'hourly_plan is missing from optimizer result.',
                'debug' => $result,
            ], 422);
        }

        if (count($result['hourly_plan']) !== 24) {
            return response()->json([
                'error' => 'Optimization failed.',
                'message' => 'hourly_plan must contain exactly 24 entries.',
                'count' => count($result['hourly_plan']),
                'debug' => $result['hourly_plan'],
            ], 422);
        }

        return response()->json($result);
    }
}
