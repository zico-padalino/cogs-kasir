<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\CogsCalculationService;
use App\Support\Format;

class DashboardController extends Controller
{
    public function index(CogsCalculationService $cogsService)
    {
        $steps = \App\Support\SetupProgress::steps();
        $summary = $cogsService->getSummaryReport();

        return view('dashboard.index', [
            'steps' => $steps,
            'progress' => [
                'percent' => \App\Support\SetupProgress::percentComplete(),
                'complete' => \App\Support\SetupProgress::isFullyComplete(),
                'currentStep' => \App\Support\SetupProgress::currentStepNumber(),
                'current' => \App\Support\SetupProgress::currentStep() ?? end($steps),
            ],
            'summary' => $summary,
            'format' => Format::class,
        ]);
    }
}
