<?php

namespace App\Http\Controllers;

use App\Models\CaseImage;
use App\Models\DiseaseClass;
use App\Models\FarmProfile;
use App\Models\FarmSection;
use App\Models\ModelVersion;
use App\Models\PlantCase;
use App\Models\Prediction;
use App\Services\AiClient;
use App\Services\AuditLogger;
use App\Services\MockPredictionService;
use App\Services\PartDetectionReceipt;
use App\Services\PartDetectionService;
use App\Services\PrototypeContentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScreeningController extends Controller
{
    public function create()
    {
        return view('screening.create', [
            'farms' => FarmProfile::query()
                ->with(['sections' => fn ($query) => $query->where('active', true)->orderBy('name')])
                ->orderBy('farm_name')
                ->get(),
        ]);
    }

    public function sampleResult(PrototypeContentService $content)
    {
        $result = [
            'screening_path' => 'leaf',
            'view_type' => 'leaf_surface',
            'image_path' => 'leaf',
            'specific_view' => 'leaf_surface',
            'predicted_class' => 'black_sigatoka',
            'display_label' => 'Black Sigatoka',
            'decision_status' => 'conclusive',
            'confidence' => 0.87,
            'confidence_threshold' => 0.75,
            'architecture' => 'EfficientNet-B0 integration (sample)',
            'model_version' => 'efficientnet-b0-demo-v0.2',
            'inference_time_ms' => 418,
            'quality_status' => 'accepted',
            'quality_flags' => [],
            'detected_part' => 'Leaf',
            'part_detection_provider' => 'Gemini',
            'part_detection_status' => 'valid',
            'message' => 'The visible symptoms are consistent with Black Sigatoka.',
            'disclaimer' => 'This is a preliminary visual-screening result and not a confirmed diagnosis.',
        ];

        return view('screening.result', [
            'result' => $result,
            'data' => [],
            'isDemo' => true,
            'case' => null,
            'advisory' => $this->advisoryFor($content, $result['predicted_class']),
        ]);
    }

    public function detectPart(
        Request $request,
        PartDetectionService $detector,
        PartDetectionReceipt $receipt
    ) {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $size = getimagesize($request->file('image')->getRealPath());
        if (! $size || $size[0] < 224 || $size[1] < 224) {
            return response()->json([
                'message' => 'The image must be readable and at least 224 x 224 pixels.',
                'errors' => ['image' => ['The image must be readable and at least 224 x 224 pixels.']],
            ], 422);
        }

        try {
            $result = $detector->detect($request->file('image'));
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'is_banana_image' => false,
                'part' => 'Unknown',
                'usable' => false,
                'message' => 'Image analysis is temporarily unavailable. Please try again.',
            ], 503);
        }

        $response = [
            'success' => true,
            'is_banana_image' => $result['is_banana_image'],
            'part' => $result['part'],
            'usable' => $result['usable'],
            'message' => $result['message'],
            'status' => $result['status'],
        ];
        if ($result['usable']) {
            $response['detection_receipt'] = $receipt->issue($request->file('image'), $result);
        }

        return response()->json($response);
    }

    public function store(
        Request $request,
        AiClient $ai,
        MockPredictionService $mock,
        PartDetectionService $detector,
        PartDetectionReceipt $receipt,
        PrototypeContentService $content,
        AuditLogger $audit
    ) {
        $data = $request->validate([

            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'variety' => 'nullable|in:Cardava,Binangay,Tundan,Other,Unknown',
            'plant_age' => 'nullable|integer|min:1|max:3650',
            'plant_age_unit' => 'nullable|required_with:plant_age|in:weeks,months,years',
            'observed_at' => 'required|date',
            'farm_profile_id' => 'nullable|integer|exists:farm_profiles,id',
            'farm_section_id' => 'nullable|integer',
            'tree_codename' => 'nullable|string|max:120',
            'symptom_notes' => 'nullable|string|max:2000',
            'part_detection_receipt' => 'nullable|string|max:12000',
        ]);
        $this->resolveFarmContext($data);
        // The model receives the uploaded image directly. Capture-area metadata is
        // assigned automatically instead of being selected by the monitoring personnel.
        $data['screening_path'] = 'auto_detected';
        $data['view_type'] = 'auto_detected';
        $data['image_path'] = 'auto_detected';
        $data['specific_view'] = 'auto_detected';

        $size = getimagesize($request->file('image')->getRealPath());
        if (! $size || $size[0] < 224 || $size[1] < 224) {
            return back()->withErrors(['image' => 'Image must be readable and at least 224 x 224 pixels.'])->withInput();
        }

        try {
            $partResult = filled($data['part_detection_receipt'] ?? null)
                ? $receipt->verify($request->file('image'), $data['part_detection_receipt'])
                : $detector->detect($request->file('image'));
        } catch (\Throwable $exception) {
            report($exception);
            $message = filled($data['part_detection_receipt'] ?? null)
                ? $exception->getMessage()
                : 'Image analysis is temporarily unavailable. No disease classification was run; please retry.';

            return back()->withErrors(['image' => $message])->withInput();
        }

        if (($partResult['usable'] ?? false) !== true) {
            $message = ($partResult['status'] ?? null) === 'invalid_image'
                ? 'The submitted image does not appear to contain a banana plant. Please capture or upload a clear banana plant image.'
                : 'The image is not clear enough to analyze. Please retake it with proper lighting, focus, and visibility.';

            return back()->withErrors(['image' => $message])->withInput();
        }

        if (config('services.bananashield.mode') === 'mock') {
            $result = $mock->predict($request->file('image'), $data['screening_path'], $data['view_type']);
        } else {
            try {
                $result = $ai->predict($request->file('image'), $data);
            } catch (\Throwable $exception) {
                report($exception);
                return back()->withErrors(['image' => 'The AI service is unavailable. No prediction was saved; please retry.'])->withInput();
            }
        }

        // Automatic image-area metadata is retained with the result and case record.
        $result['screening_path'] = $data['screening_path'];
        $result['view_type'] = $data['view_type'];
        $result['image_path'] = $data['image_path'];
        $result['specific_view'] = $data['specific_view'];
        $result['detected_part'] = $partResult['part'];
        $result['part_detection_provider'] = $partResult['provider'];
        $result['part_detection_status'] = $partResult['status'];

        $model = ModelVersion::query()->where('active', true)->latest()->first();
        $threshold = $model?->confidence_threshold ?? (float) ($result['confidence_threshold'] ?? 0.75);
        $result['confidence_threshold'] = $threshold;
        $result['model_version'] = $model?->version_name ?? $result['model_version'];
        $result['architecture'] = $model?->architecture ?? $result['architecture'];

        if (($result['confidence'] ?? 0) < $threshold) {
            $result = array_merge($result, [
                'predicted_class' => 'inconclusive',
                'display_label' => 'Inconclusive result',
                'decision_status' => 'inconclusive',
                'quality_flags' => array_values(array_unique(array_merge($result['quality_flags'] ?? [], ['below_confidence_threshold']))),
                'message' => 'The model confidence is below the configured threshold. Retake the image or seek professional assessment.',
            ]);
        }

        if (
            ($result['predicted_class'] ?? 'inconclusive') !== 'inconclusive'
            && DiseaseClass::query()->exists()
            && ! DiseaseClass::query()->where('technical_name', $result['predicted_class'])->where('active', true)->exists()
        ) {
            $result = array_merge($result, [
                'predicted_class' => 'inconclusive',
                'display_label' => 'Inconclusive result',
                'decision_status' => 'inconclusive',
                'quality_flags' => array_values(array_unique(array_merge($result['quality_flags'] ?? [], ['class_not_active']))),
                'message' => 'The model output is not currently active as a validated BananaShield category. Seek professional assessment.',
            ]);
        }


        $storedPath = null;
        try {
            $storedPath = $request->file('image')->store('cases/'.now()->format('Y/m'), 'local');
            [$case, $caseImage] = DB::transaction(function () use ($request, $data, $result, $partResult, $size, $model, $storedPath) {
                $case = PlantCase::create([
                    'case_number' => 'BS-'.now()->format('Y').'-'.strtoupper(Str::random(8)),
                    'submitted_by' => $request->user()->id,
                    'screening_path' => $data['screening_path'],
                    'variety' => $data['variety'] ?? null,
                    'plant_age' => $data['plant_age'] ?? null,
                    'plant_age_unit' => $data['plant_age_unit'] ?? null,
                    'symptom_notes' => $data['symptom_notes'] ?? null,
                    'observed_at' => $data['observed_at'],
                    'farm_profile_id' => $data['farm_profile_id'] ?? null,
                    'farm_section' => $data['farm_section'] ?? null,
                    'tree_codename' => $data['tree_codename'] ?? null,
                    'status' => 'open',
                    'review_status' => $request->user()->role === 'farm_owner' ? 'self_recorded' : 'pending',
                ]);

                $file = $request->file('image');
                $caseImage = CaseImage::create([
                    'case_id' => $case->id,
                    'image_path' => $data['image_path'],
                    'specific_view' => $data['specific_view'],
                    'view_type' => $data['view_type'],
                    'image_type' => 'original',
                    'storage_disk' => 'local',
                    'storage_path' => $storedPath,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'width' => $size[0],
                    'height' => $size[1],
                    'image_quality_status' => $result['quality_status'] ?? 'accepted',
                    'detected_part' => $partResult['part'],
                    'part_detection_provider' => $partResult['provider'],
                    'part_detection_status' => $partResult['status'],
                    'metadata_removed' => false,
                    'uploaded_at' => now(),
                ]);

                Prediction::create([
                    'case_id' => $case->id,
                    'image_id' => $caseImage->id,
                    'model_version_id' => $model?->id,
                    'predicted_class' => $result['predicted_class'],
                    'display_label' => $result['display_label'],
                    'confidence' => $result['confidence'],
                    'decision_status' => $result['decision_status'],
                    'quality_status' => $result['quality_status'] ?? 'accepted',
                    'quality_flags' => $result['quality_flags'] ?? [],
                    'inference_time_ms' => $result['inference_time_ms'] ?? null,
                    'result_message' => $result['message'],
                    'disclaimer' => $result['disclaimer'],
                ]);

                return [$case, $caseImage];
            });
        } catch (\Throwable $exception) {
            if ($storedPath) Storage::disk('local')->delete($storedPath);
            report($exception);
            return back()->withErrors(['image' => 'The screening could not be saved. No completed prediction record was created; please retry.'])->withInput();
        }

        $audit->record('screening.case_created', $case, metadata: [
            'decision_status' => $result['decision_status'],
            'detected_part' => $partResult['part'],
            'part_detection_provider' => $partResult['provider'],
        ]);
        $advisory = $this->advisoryFor($content, $result['predicted_class'] ?? 'inconclusive');

        return view('screening.result', compact('result', 'data', 'advisory', 'case', 'caseImage'));
    }

    private function resolveFarmContext(array &$data): void
    {
        $farmId = $data['farm_profile_id'] ?? null;
        $sectionId = $data['farm_section_id'] ?? null;
        $treeCodename = $data['tree_codename'] ?? null;
        $data['farm_section'] = null;

        if (! $farmId) {
            return;
        }

        $section = $sectionId
            ? FarmSection::query()
                ->whereKey($sectionId)
                ->where('farm_profile_id', $farmId)
                ->where('active', true)
                ->first()
            : null;

        if ($sectionId && ! $section) {
            throw ValidationException::withMessages([
                'farm_section_id' => 'Choose a registered active block from the selected farm.',
            ]);
        }

        if (! $section && $treeCodename) {
            throw ValidationException::withMessages([
                'farm_section_id' => 'Choose a block before choosing a plant codename.',
            ]);
        }

        if (! $section) {
            return;
        }

        $data['farm_section'] = $section->name;
        if ($treeCodename && ! in_array($treeCodename, $section->plant_codenames ?? [], true)) {
            throw ValidationException::withMessages([
                'tree_codename' => 'Choose a registered plant codename from the selected block.',
            ]);
        }
    }

    private function advisoryFor(PrototypeContentService $content, string $predictedClass): array
    {
        $advisories = $content->advisories();
        return $advisories[$predictedClass] ?? $advisories['inconclusive'];
    }
}
