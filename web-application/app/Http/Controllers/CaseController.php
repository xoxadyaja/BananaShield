<?php

namespace App\Http\Controllers;

use App\Models\CaseImage;
use App\Models\FarmSection;
use App\Models\PlantCase;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CaseController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:120',
            'block' => 'nullable|string|max:120',
            'status' => ['nullable', Rule::in(['open', 'improving', 'unchanged', 'worsening', 'referred', 'closed'])],
            'review' => ['nullable', Rule::in(['pending', 'reviewed', 'needs_follow_up', 'referred', 'closed', 'self_recorded'])],
            'decision' => ['nullable', Rule::in(['conclusive', 'inconclusive'])],
        ]);

        $search = trim($filters['q'] ?? '');
        $selectedBlock = trim($filters['block'] ?? '');
        $selectedStatus = $filters['status'] ?? '';
        $selectedReview = $filters['review'] ?? '';
        $selectedDecision = $filters['decision'] ?? '';
        $visibleCases = PlantCase::query()->visibleTo($request->user());
        $recordTotal = (clone $visibleCases)->count();

        $reportedBlocks = (clone $visibleCases)
            ->selectRaw('farm_section, COUNT(*) as reports_count')
            ->groupBy('farm_section')
            ->get();

        $reportedBlockCounts = [];
        $reportedBlockLabels = [];
        foreach ($reportedBlocks as $reportedBlock) {
            $label = trim((string) $reportedBlock->farm_section);
            $key = $label === '' ? '__unassigned' : Str::lower($label);
            $reportedBlockCounts[$key] = ($reportedBlockCounts[$key] ?? 0) + (int) $reportedBlock->reports_count;
            if ($label !== '') {
                $reportedBlockLabels[$key] ??= $label;
            }
        }

        $blocks = FarmSection::query()
            ->orderByDesc('active')
            ->orderBy('name')
            ->get()
            ->map(function (FarmSection $section) use ($reportedBlockCounts) {
                $key = Str::lower(trim($section->name));

                return [
                    'name' => $section->name,
                    'query' => $section->name,
                    'reports_count' => $reportedBlockCounts[$key] ?? 0,
                    'area_hectares' => $section->area_hectares,
                    'active' => $section->active,
                    'key' => $key,
                ];
            });

        $configuredBlockKeys = $blocks->pluck('key')->all();
        foreach ($reportedBlockLabels as $key => $label) {
            if (! in_array($key, $configuredBlockKeys, true)) {
                $blocks->push([
                    'name' => $label,
                    'query' => $label,
                    'reports_count' => $reportedBlockCounts[$key],
                    'area_hectares' => null,
                    'active' => true,
                    'key' => $key,
                ]);
            }
        }

        if (($reportedBlockCounts['__unassigned'] ?? 0) > 0) {
            $blocks->push([
                'name' => 'Unassigned block',
                'query' => '__unassigned',
                'reports_count' => $reportedBlockCounts['__unassigned'],
                'area_hectares' => null,
                'active' => true,
                'key' => '__unassigned',
            ]);
        }

        $query = (clone $visibleCases)
            ->with(['latestPrediction', 'submitter'])
            ->withCount('followUps');

        if ($selectedBlock === '__unassigned') {
            $query->where(function ($blockQuery) {
                $blockQuery->whereNull('farm_section')->orWhereRaw("TRIM(farm_section) = ''");
            });
        } elseif ($selectedBlock !== '') {
            $query->whereRaw('LOWER(TRIM(farm_section)) = ?', [Str::lower($selectedBlock)]);
        }

        if ($search !== '') {
            $like = "%{$search}%";
            $query->where(function ($searchQuery) use ($like) {
                $searchQuery
                    ->where('case_number', 'like', $like)
                    ->orWhere('farm_section', 'like', $like)
                    ->orWhere('tree_codename', 'like', $like)
                    ->orWhere('variety', 'like', $like)
                    ->orWhere('status', 'like', $like)
                    ->orWhere('review_status', 'like', $like)
                    ->orWhere('screening_path', 'like', $like)
                    ->orWhereHas('submitter', function ($submitterQuery) use ($like) {
                        $submitterQuery->where('name', 'like', $like)->orWhere('email', 'like', $like);
                    })
                    ->orWhereHas('predictions', function ($predictionQuery) use ($like) {
                        $predictionQuery->where('display_label', 'like', $like)->orWhere('predicted_class', 'like', $like);
                    });
            });
        }

        if ($selectedStatus !== '') {
            $query->where('status', $selectedStatus);
        }

        if ($selectedReview !== '') {
            $query->where('review_status', $selectedReview);
        }

        if ($selectedDecision !== '') {
            $query->whereHas('latestPrediction', function ($predictionQuery) use ($selectedDecision) {
                $predictionQuery->where('decision_status', $selectedDecision);
            });
        }

        $cases = $query->latest('observed_at')->paginate(12)->withQueryString();

        return view('cases.index', compact(
            'blocks',
            'cases',
            'recordTotal',
            'search',
            'selectedBlock',
            'selectedStatus',
            'selectedReview',
            'selectedDecision',
        ));
    }

    public function show(Request $request, PlantCase $case)
    {
        $this->authorizeCase($request, $case);
        $case->load(['submitter', 'reviewer', 'images', 'latestPrediction.modelVersion', 'followUps.creator']);
        return view('cases.show', compact('case'));
    }

    public function image(Request $request, PlantCase $case, CaseImage $image)
    {
        $this->authorizeCase($request, $case);
        abort_unless($image->case_id === $case->id, 404);
        abort_unless(Storage::disk($image->storage_disk)->exists($image->storage_path), 404);
        return Storage::disk($image->storage_disk)->response($image->storage_path, null, ['Content-Type' => $image->mime_type]);
    }

    public function review(Request $request, PlantCase $case, AuditLogger $audit)
    {
        $data = $request->validate([
            'review_status' => 'required|in:reviewed,needs_follow_up,referred,closed',
            'review_notes' => 'nullable|string|max:2000',
        ]);

        $updates = $data + ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()];
        if ($data['review_status'] === 'referred') $updates += ['status' => 'referred', 'referred_at' => now()];
        if ($data['review_status'] === 'closed') $updates += ['status' => 'closed', 'closed_at' => now()];
        $case->update($updates);
        $audit->record('case.owner_reviewed', $case, metadata: ['review_status' => $data['review_status']]);

        return back()->with('success', 'Owner review saved without changing the historical prediction.');
    }

    private function authorizeCase(Request $request, PlantCase $case): void
    {
        abort_unless($case->isReportable(), 404);
        abort_unless($request->user()->role === 'farm_owner' || $case->submitted_by === $request->user()->id, 403);
    }
}
