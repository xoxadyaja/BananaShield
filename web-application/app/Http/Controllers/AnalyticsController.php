<?php

namespace App\Http\Controllers;

use App\Models\PlantCase;
use App\Models\Prediction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function __invoke()
    {
        $totalCases = PlantCase::reportable()->count();
        $latestPredictionIds = Prediction::query()
            ->selectRaw('MAX(id)')
            ->groupBy('case_id');
        $reportablePredictions = Prediction::query()
            ->whereIn('id', $latestPredictionIds)
            ->where('predicted_class', '!=', 'healthy_banana');
        $totalPredictions = (clone $reportablePredictions)->count();
        $conclusiveResults = (clone $reportablePredictions)->where('decision_status', 'conclusive')->count();
        $inconclusiveResults = (clone $reportablePredictions)->where('decision_status', 'inconclusive')->count();
        $referredCases = PlantCase::reportable()->where('status', 'referred')->count();

        $metrics = [
            ['label' => 'Total submitted cases', 'value' => $totalCases, 'hint' => 'All farm records', 'tone' => 'forest', 'href' => route('monitoring'), 'cta' => 'View all cases'],
            ['label' => 'Conclusive results', 'value' => $conclusiveResults, 'hint' => $totalPredictions ? round(($conclusiveResults / $totalPredictions) * 100).'% of predictions' : 'No predictions yet', 'tone' => 'leaf', 'href' => route('monitoring', ['decision' => 'conclusive']), 'cta' => 'View conclusive cases'],
            ['label' => 'Inconclusive results', 'value' => $inconclusiveResults, 'hint' => $totalPredictions ? round(($inconclusiveResults / $totalPredictions) * 100).'% need review' : 'No predictions yet', 'tone' => 'gold', 'href' => route('monitoring', ['decision' => 'inconclusive']), 'cta' => 'Review inconclusive cases'],
            ['label' => 'Cases referred', 'value' => $referredCases, 'hint' => $totalCases ? round(($referredCases / $totalCases) * 100).'% of submitted cases' : 'No referrals yet', 'tone' => 'soil', 'href' => route('monitoring', ['status' => 'referred']), 'cta' => 'View referred cases'],
        ];

        $pathCounts = PlantCase::reportable()->select('screening_path', DB::raw('count(*) as total'))
            ->groupBy('screening_path')
            ->pluck('total', 'screening_path')
            ->map(fn ($value) => (int) $value)
            ->all();
        $paths = array_replace(['leaf' => 0, 'whole_plant' => 0], $pathCounts);

        $classCounts = (clone $reportablePredictions)
            ->select('display_label', DB::raw('count(*) as total'))
            ->groupBy('display_label')
            ->pluck('total', 'display_label')
            ->map(fn ($value) => (int) $value)
            ->all();
        $classes = array_replace([
            'Black Sigatoka' => 0,
            'Fusarium Wilt' => 0,
            'Banana Bunchy Top Disease' => 0,
            'Inconclusive result' => 0,
        ], $classCounts);

        $statusCounts = PlantCase::reportable()->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($value) => (int) $value)
            ->all();
        $statuses = array_replace([
            'open' => 0,
            'improving' => 0,
            'unchanged' => 0,
            'worsening' => 0,
            'referred' => 0,
            'closed' => 0,
        ], $statusCounts);

        $trendStart = CarbonImmutable::now()->startOfMonth()->subMonths(5);
        $monthlyCounts = PlantCase::query()
            ->reportable()
            ->where('created_at', '>=', $trendStart)
            ->get(['created_at'])
            ->filter(fn (PlantCase $case) => $case->created_at !== null)
            ->countBy(fn (PlantCase $case) => $case->created_at->format('Y-m'));

        $trend = collect(range(0, 5))->map(function (int $offset) use ($trendStart, $monthlyCounts) {
            $month = $trendStart->addMonths($offset);

            return [
                'key' => $month->format('Y-m'),
                'label' => $month->format('M'),
                'full_label' => $month->format('F Y'),
                'value' => (int) $monthlyCounts->get($month->format('Y-m'), 0),
            ];
        })->all();

        return view('analytics.index', [
            'analytics' => compact('metrics', 'paths', 'classes', 'statuses', 'trend'),
        ]);
    }
}
