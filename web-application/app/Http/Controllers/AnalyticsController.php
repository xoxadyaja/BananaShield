<?php

namespace App\Http\Controllers;

use App\Models\FarmProfile;
use App\Models\PlantCase;
use App\Models\Prediction;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AnalyticsController extends Controller
{
    public function __invoke(Request $request)
    {
        $farms = FarmProfile::query()->orderBy('farm_name')->get();
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
            ->select('cases.id', 'cases.farm_profile_id', 'cases.created_at', 'cases.status', 'cases.review_status', 'predictions.predicted_class')
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

        return view('analytics.index', [
            'farms' => $farms,
            'selectedFarm' => $selectedFarm,
            'selectedFarmName' => $selectedFarm === '__unassigned'
                ? 'Farm not assigned'
                : ($farms->firstWhere('id', $selectedFarm)?->farm_name ?? 'All farms'),
            'reportParams' => $reportParams,
            'analytics' => compact('metrics', 'summary', 'classes', 'trend', 'statuses', 'pendingReview', 'farmRows'),
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
}
