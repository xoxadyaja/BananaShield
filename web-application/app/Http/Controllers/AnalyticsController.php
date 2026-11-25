<?php

namespace App\Http\Controllers;

use App\Models\FarmProfile;
use App\Models\PlantCase;
use App\Models\Prediction;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AnalyticsController extends Controller
{
    public function __invoke(Request $request)
    {
        $farms = FarmProfile::query()->with('sections')->orderBy('farm_name')->get();
        $filters = $request->validate([
            'farm' => ['nullable', 'string', Rule::in(array_merge(
                ['__unassigned'], $farms->modelKeys()
            ))],
        ]);
        $selectedFarm = $filters['farm'] ?? '';
        $reportParams = $selectedFarm !== '' ? ['farm' => $selectedFarm] : [];

        // Use only the latest prediction for each saved screening.
        $latestPredictions = Prediction::query()
            ->selectRaw('case_id, MAX(id) as prediction_id')
            ->groupBy('case_id');
        $caseRows = PlantCase::query()->visibleTo($request->user())
            ->leftJoinSub($latestPredictions, 'latest', 'latest.case_id', '=', 'cases.id')
            ->leftJoin('predictions', 'predictions.id', '=', 'latest.prediction_id')
            ->select('cases.id', 'cases.farm_profile_id', 'cases.farm_section', 'cases.created_at', 'cases.status', 'cases.review_status', 'predictions.predicted_class', 'predictions.decision_status')
            ->selectRaw(Prediction::outcomeSql().' as outcome');

        if ($selectedFarm === '__unassigned') {
            $caseRows->whereNull('cases.farm_profile_id');
        } elseif ($selectedFarm !== '') {
            $caseRows->where('cases.farm_profile_id', $selectedFarm);
        }

        $records = DB::query()->fromSub($caseRows, 'reports');
        $summary = $this->counts((clone $records)->selectRaw($this->countColumns())->first());
        $metrics = [
            ['label' => 'Recorded screenings', 'value' => $summary['total'], 'hint' => 'All saved results', 'tone' => 'forest', 'href' => route('monitoring', $reportParams)],
            ['label' => 'Healthy screenings', 'value' => $summary['healthy'], 'hint' => 'Preliminary healthy results', 'tone' => 'leaf', 'href' => route('monitoring', $reportParams + ['outcome' => 'healthy'])],
            ['label' => 'Disease indications', 'value' => $summary['disease'], 'hint' => 'Supported disease results', 'tone' => 'soil', 'href' => route('monitoring', $reportParams + ['outcome' => 'disease'])],
            ['label' => 'Inconclusive results', 'value' => $summary['inconclusive'], 'hint' => 'Results needing another look', 'tone' => 'gold', 'href' => route('monitoring', $reportParams + ['outcome' => 'inconclusive'])],
        ];

        $classExpression = "CASE WHEN outcome IN ('inconclusive', 'unavailable') THEN outcome ELSE predicted_class END";
        $classCounts = (clone $records)->selectRaw($classExpression.' as class_key, COUNT(*) as total')
            ->groupByRaw($classExpression)->pluck('total', 'class_key');
        $classes = collect([
            'healthy_banana' => 'Healthy Banana',
            'black_sigatoka' => 'Black Sigatoka',
            'fusarium_wilt' => 'Fusarium Wilt',
            'banana_bunchy_top_disease' => 'Banana Bunchy Top Disease',
            'inconclusive' => 'Inconclusive result',
            'unavailable' => 'Result unavailable',
        ])->map(fn ($label, $key) => [
            'label' => $label,
            'value' => (int) $classCounts->get($key, 0),
            'tone' => $key === 'healthy_banana' ? 'healthy' : ($key === 'inconclusive' ? 'inconclusive' : ($key === 'unavailable' ? 'unavailable' : 'disease')),
        ])->values()->all();

        $trendStart = CarbonImmutable::now()->startOfMonth()->subMonths(5);
        $trendEnd = CarbonImmutable::now()->startOfMonth()->addMonth();
        $monthCounts = (clone $records)
            ->where('created_at', '>=', $trendStart)->where('created_at', '<', $trendEnd)
            ->selectRaw('SUBSTR(created_at, 1, 7) as month_key, '.$this->countColumns())
            ->groupByRaw('SUBSTR(created_at, 1, 7)')->get()->keyBy('month_key');
        $trend = collect(range(0, 5))->map(function ($offset) use ($trendStart, $monthCounts) {
            $month = $trendStart->addMonths($offset);
            return [
                'label' => $month->format('M'),
                'full_label' => $month->format('F Y'),
            ] + $this->counts($monthCounts->get($month->format('Y-m')));
        })->all();

        $statuses = array_replace(array_fill_keys(['open', 'improving', 'unchanged', 'worsening', 'referred', 'closed'], 0),
            (clone $records)->selectRaw('status, COUNT(*) as total')->groupBy('status')
                ->pluck('total', 'status')->map(fn ($count) => (int) $count)->all()
        );
        $pendingReview = (clone $records)->where('review_status', 'pending')->count();

        $farmCounts = (clone $records)->selectRaw('farm_profile_id, '.$this->countColumns())
            ->groupBy('farm_profile_id')->get()->keyBy(fn ($row) => $row->farm_profile_id ?? '__unassigned');
        $farmRows = $farms
            ->filter(fn ($farm) => $selectedFarm === '' || (string) $farm->id === $selectedFarm)
            ->map(fn ($farm) => [
                'name' => $farm->farm_name,
                'location' => collect([$farm->municipality, $farm->province])->filter()->implode(', '),
                'href' => route('monitoring', ['farm' => $farm->id]),
            ] + $this->counts($farmCounts->get($farm->id)))->values();
        if ($farmCounts->has('__unassigned') || $selectedFarm === '__unassigned') {
            $farmRows->push([
                'name' => 'Farm not assigned',
                'location' => 'Reports without a linked farm',
                'href' => route('monitoring', ['farm' => '__unassigned']),
            ] + $this->counts($farmCounts->get('__unassigned')));
        }

        // Block analytics keeps the same latest-prediction and visibility scope as the
        // rest of this report, while retaining registered blocks that have no cases yet.
        $blockCounts = (clone $records)
            ->selectRaw('farm_profile_id, farm_section, '.$this->blockCountColumns())
            ->groupBy('farm_profile_id', 'farm_section')
            ->get();
        $blockAnalytics = $this->blockAnalytics($farms, $blockCounts, $selectedFarm);

        return view('analytics.index', [
            'farms' => $farms,
            'selectedFarm' => $selectedFarm,
            'selectedFarmName' => $selectedFarm === '__unassigned'
                ? 'Farm not assigned'
                : ($farms->firstWhere('id', $selectedFarm)?->farm_name ?? 'All farms'),
            'reportParams' => $reportParams,
            'analytics' => compact('metrics', 'summary', 'classes', 'trend', 'statuses', 'pendingReview', 'farmRows', 'blockAnalytics'),
        ]);
    }

    private function countColumns(): string
    {
        return "COUNT(*) as total,
            SUM(CASE WHEN outcome = 'healthy' THEN 1 ELSE 0 END) as healthy,
            SUM(CASE WHEN outcome = 'disease' THEN 1 ELSE 0 END) as disease,
            SUM(CASE WHEN outcome = 'inconclusive' THEN 1 ELSE 0 END) as inconclusive,
            SUM(CASE WHEN outcome = 'unavailable' THEN 1 ELSE 0 END) as unavailable";
    }

    private function counts(?object $row): array
    {
        return collect(['total', 'healthy', 'disease', 'inconclusive', 'unavailable'])
            ->mapWithKeys(fn ($key) => [$key => (int) ($row?->{$key} ?? 0)])->all();
    }

    private function blockCountColumns(): string
    {
        // The deployed Laravel service persists the descriptive BBTD key while the
        // trained-model artifact names its fourth class "bbtv". Accept both so older
        // or directly imported model results are counted consistently.
        $diseaseClasses = "'black_sigatoka', 'fusarium_wilt', 'banana_bunchy_top_disease', 'bbtv'";

        return "COUNT(*) as total,
            SUM(CASE WHEN outcome = 'healthy' THEN 1 ELSE 0 END) as healthy,
            SUM(CASE WHEN decision_status = 'conclusive' AND predicted_class IN ({$diseaseClasses}) THEN 1 ELSE 0 END) as disease,
            SUM(CASE WHEN outcome = 'inconclusive' THEN 1 ELSE 0 END) as inconclusive,
            SUM(CASE WHEN outcome = 'unavailable' AND COALESCE(predicted_class, '') != 'bbtv' THEN 1 ELSE 0 END) as unavailable,
            SUM(CASE WHEN decision_status = 'conclusive' AND predicted_class = 'black_sigatoka' THEN 1 ELSE 0 END) as black_sigatoka,
            SUM(CASE WHEN decision_status = 'conclusive' AND predicted_class = 'fusarium_wilt' THEN 1 ELSE 0 END) as fusarium_wilt,
            SUM(CASE WHEN decision_status = 'conclusive' AND predicted_class IN ('banana_bunchy_top_disease', 'bbtv') THEN 1 ELSE 0 END) as banana_bunchy_top_disease";
    }

    private function blockAnalytics($farms, $blockCounts, string $selectedFarm)
    {
        $diseaseLabels = [
            'black_sigatoka' => 'Black Sigatoka',
            'fusarium_wilt' => 'Fusarium Wilt',
            'banana_bunchy_top_disease' => 'Banana Bunchy Top Disease',
        ];

        $countsByFarm = $blockCounts->groupBy('farm_profile_id');

        return $farms
            ->filter(fn (FarmProfile $farm) => $selectedFarm === '' || (string) $farm->id === $selectedFarm)
            ->map(function (FarmProfile $farm) use ($countsByFarm, $diseaseLabels) {
                $reported = [];
                foreach ($countsByFarm->get($farm->id, collect()) as $row) {
                    $key = Str::lower(trim((string) $row->farm_section));
                    $key = $key === '' ? '__unassigned' : $key;
                    $reported[$key] = $this->mergeBlockCounts($reported[$key] ?? [], $row);
                }

                $blocks = $farm->sections->map(function ($section) use (&$reported, $farm, $diseaseLabels) {
                    $key = Str::lower(trim($section->name));
                    $counts = $reported[$key] ?? $this->emptyBlockCounts();
                    unset($reported[$key]);

                    return $this->blockRow($farm->id, $section->name, $section->name, $counts, $diseaseLabels);
                });

                // Preserve named legacy blocks in the report instead of silently
                // dropping historical screenings that predate block registration.
                foreach ($reported as $key => $counts) {
                    if ($key === '__unassigned') {
                        continue;
                    }
                    $blocks->push($this->blockRow($farm->id, $counts['name'], $counts['name'], $counts, $diseaseLabels));
                }
                if (isset($reported['__unassigned'])) {
                    $blocks->push($this->blockRow($farm->id, 'Unassigned Block', '__unassigned', $reported['__unassigned'], $diseaseLabels));
                }

                $maxDisease = $blocks->max('disease') ?? 0;
                $hotspotBlocks = $maxDisease > 0
                    ? $blocks->where('disease', $maxDisease)->pluck('name')->values()->all()
                    : [];
                $hotspotDiseases = $maxDisease > 0
                    ? $blocks->where('disease', $maxDisease)->flatMap(fn (array $block) => $block['dominant_diseases'])->unique()->values()->all()
                    : [];

                return [
                    'id' => $farm->id,
                    'name' => $farm->farm_name,
                    'location' => collect([$farm->municipality, $farm->province])->filter()->implode(', '),
                    'chart_id' => 'block-disease-chart-'.$farm->id,
                    'chart_max' => max(1, $maxDisease),
                    'blocks' => $blocks->values(),
                    'hotspot' => [
                        'has_disease' => $maxDisease > 0,
                        'count' => $maxDisease,
                        'blocks' => $hotspotBlocks,
                        'diseases' => $hotspotDiseases,
                    ],
                ];
            })->values();
    }

    private function mergeBlockCounts(array $current, object $row): array
    {
        $counts = $current ?: $this->emptyBlockCounts();
        $counts['name'] ??= trim((string) $row->farm_section);
        foreach (array_keys($this->emptyBlockCounts()) as $key) {
            if ($key !== 'name') {
                $counts[$key] += (int) ($row->{$key} ?? 0);
            }
        }

        return $counts;
    }

    private function emptyBlockCounts(): array
    {
        return array_fill_keys([
            'total', 'disease', 'healthy', 'inconclusive', 'unavailable',
            'black_sigatoka', 'fusarium_wilt', 'banana_bunchy_top_disease',
        ], 0);
    }

    private function blockRow(int $farmId, string $name, string $query, array $counts, array $diseaseLabels): array
    {
        $diseaseCounts = collect($diseaseLabels)
            ->mapWithKeys(fn ($label, $key) => [$key => (int) $counts[$key]])
            ->all();
        $highestDiseaseCount = max($diseaseCounts);
        $dominantDiseases = $highestDiseaseCount > 0
            ? collect($diseaseCounts)->filter(fn ($count) => $count === $highestDiseaseCount)
                ->keys()->map(fn ($key) => $diseaseLabels[$key])->values()->all()
            : [];

        return [
            'name' => $name,
            'href' => route('monitoring', ['farm' => $farmId, 'block' => $query]),
            'dominant_disease' => $dominantDiseases ? implode(', ', $dominantDiseases) : 'None',
            'dominant_diseases' => $dominantDiseases,
            'disease_counts' => $diseaseCounts,
        ] + $counts;
    }
}
