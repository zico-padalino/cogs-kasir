<?php

namespace App\Http\Controllers\Api\Cogs;

use App\Http\Controllers\Controller;
use App\Services\CogsCalculationService;
use App\Support\SetupProgress;
use Illuminate\Http\JsonResponse;

class DashboardApiController extends Controller
{
    public function index(CogsCalculationService $cogsService): JsonResponse
    {
        $steps = SetupProgress::steps();
        $summary = $cogsService->getSummaryReport();

        return response()->json([
            'message' => 'Dashboard COGS berhasil dimuat.',
            'data' => [
                'steps' => $steps,
                'progress' => [
                    'percent' => SetupProgress::percentComplete(),
                    'complete' => SetupProgress::isFullyComplete(),
                    'current_step' => SetupProgress::currentStepNumber(),
                    'current' => SetupProgress::currentStep() ?? end($steps),
                ],
                'summary' => $summary,
            ],
        ]);
    }
}
