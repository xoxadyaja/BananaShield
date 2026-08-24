<?php

namespace App\Http\Controllers;

use App\Models\DiseaseClass;
use App\Services\PrototypeContentService;
use Illuminate\Http\Request;

class AdvisoryController extends Controller
{
    public function __invoke(Request $request, PrototypeContentService $fallback)
    {
        $advisories = [];
        $diseases = DiseaseClass::query()->where('active', true)->with('activeAdvisory')->get();
        foreach ($diseases as $disease) {
            if (! $disease->activeAdvisory) continue;
            $advisory = $disease->activeAdvisory;
            $advisories[$disease->technical_name] = [
                'title' => $disease->display_name,
                'path' => 'Either screening path',
                'tone' => match ($disease->technical_name) {
                    'healthy_banana' => 'success',
                    'black_sigatoka', 'fusarium_wilt' => 'danger',
                    default => 'warning',
                },
                'summary' => $disease->description ?: 'General educational guidance for this supported class.',
                'signs' => $this->lines($advisory->visible_signs),
                'prevention' => $this->lines($advisory->prevention),
                'containment' => $this->lines($advisory->containment_reminders),
                'guidance' => $this->lines($advisory->general_guidance),
                'consult' => $advisory->consultation_guidance,
                'version' => $advisory->version_label,
            ];
        }

        $fallbackAdvisories = $fallback->advisories();
        if (! $advisories) $advisories = $fallbackAdvisories;
        elseif (! isset($advisories['inconclusive'])) $advisories['inconclusive'] = $fallbackAdvisories['inconclusive'];
        return view('advisories.index', ['advisories' => $advisories, 'selected' => $request->string('condition')->toString()]);
    }

    private function lines(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $value))));
    }
}
