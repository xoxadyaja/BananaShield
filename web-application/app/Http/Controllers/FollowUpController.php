<?php

namespace App\Http\Controllers;

use App\Models\CaseImage;
use App\Models\FollowUp;
use App\Models\PlantCase;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FollowUpController extends Controller
{
    public function store(Request $request, PlantCase $case, AuditLogger $audit)
    {
        abort_unless($request->user()->role === 'monitoring_personnel' && $case->submitted_by === $request->user()->id, 403);
        $data = $request->validate([
            'observation' => 'required|string|max:2000',
            'action_taken' => 'nullable|string|max:2000',
            'case_status' => 'required|in:open,improving,unchanged,worsening,referred,closed',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'view_type' => 'nullable|required_with:image|in:follow_up_leaf,follow_up_whole_plant,crown',
        ]);

        $imageSize = $request->hasFile('image') ? getimagesize($request->file('image')->getRealPath()) : null;
        if ($imageSize && ($imageSize[0] < 224 || $imageSize[1] < 224)) {
            return back()->withErrors(['image' => 'A follow-up image must be at least 224 x 224 pixels.']);
        }

        $followUp = DB::transaction(function () use ($request, $case, $data, $imageSize) {
            $followUp = FollowUp::create([
                'case_id' => $case->id,
                'observation' => $data['observation'],
                'action_taken' => $data['action_taken'] ?? null,
                'case_status' => $data['case_status'],
                'created_by' => $request->user()->id,
            ]);

            $case->update([
                'status' => $data['case_status'],
                'referred_at' => $data['case_status'] === 'referred' ? now() : $case->referred_at,
                'closed_at' => $data['case_status'] === 'closed' ? now() : null,
            ]);

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $path = $file->store("cases/{$case->id}/follow-ups", 'local');
                CaseImage::create([
                    'case_id' => $case->id,
                    'follow_up_id' => $followUp->id,
                    'view_type' => $data['view_type'],
                    'image_type' => 'follow_up',
                    'storage_disk' => 'local',
                    'storage_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'width' => $imageSize[0],
                    'height' => $imageSize[1],
                    'image_quality_status' => 'not_classified',
                    'metadata_removed' => false,
                    'uploaded_at' => now(),
                ]);
            }

            return $followUp;
        });

        $audit->record('case.follow_up_added', $followUp, metadata: ['case_status' => $data['case_status']]);
        return back()->with('success', 'Follow-up observation added to the case history.');
    }
}
