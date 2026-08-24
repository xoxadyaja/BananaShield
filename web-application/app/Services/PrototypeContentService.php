<?php

namespace App\Services;

class PrototypeContentService
{
    public function advisories(): array
    {
        return [
            'healthy_banana' => [
                'title' => 'Healthy Banana', 'path' => 'Either screening path', 'tone' => 'success',
                'summary' => 'No supported visible disease pattern was identified in the submitted image.',
                'signs' => ['Coloration and form appear generally appropriate for the variety', 'No clear supported disease pattern is visible in this image'],
                'prevention' => ['Continue routine field observation', 'Maintain good drainage and field sanitation', 'Use clean planting material from reliable sources'],
                'containment' => ['No disease-specific containment is indicated by this preliminary result.'],
                'guidance' => ['Save a baseline image for future comparison', 'Record a follow-up if visible changes develop'],
                'consult' => 'Consult agricultural personnel if decline continues despite a visually healthy result.',
            ],
            'black_sigatoka' => [
                'title' => 'Black Sigatoka', 'path' => 'Either screening path', 'tone' => 'danger',
                'summary' => 'Visible symptoms may be consistent with Black Sigatoka, a disease that can reduce functional leaf area.',
                'signs' => ['Narrow dark streaks or elongated spots', 'Lesions that enlarge and develop gray or tan centers', 'Progressive yellowing or drying of affected leaf tissue'],
                'prevention' => ['Inspect plants regularly and document changes', 'Promote airflow through appropriate field sanitation', 'Avoid moving contaminated leaf material between farm sections'],
                'containment' => ['Mark the affected area for monitoring', 'Follow local guidance when handling removed plant material', 'Clean tools before using them in another section'],
                'guidance' => ['Record follow-up photographs from the same view', 'Seek locally appropriate management guidance from agricultural personnel'],
                'consult' => 'Request professional assessment when lesions spread quickly or multiple plants show similar symptoms.',
            ],
            'fusarium_wilt' => [
                'title' => 'Fusarium Wilt', 'path' => 'Either screening path', 'tone' => 'danger',
                'summary' => 'Visible symptoms may resemble Fusarium Wilt, but image screening cannot confirm the pathogen or identify a race.',
                'signs' => ['Yellowing that may begin on older leaves', 'Wilting, collapse, or skirt-like hanging leaves', 'Whole-plant decline that progresses over time'],
                'prevention' => ['Avoid moving soil, water, tools, or planting material from the area', 'Use clean footwear and tools between farm sections', 'Obtain planting material from reliable disease-aware sources'],
                'containment' => ['Limit unnecessary entry into the affected area', 'Mark the plant and document nearby observations', 'Contact agricultural personnel before removing or disturbing the plant'],
                'guidance' => ['Do not attempt to identify a Fusarium race from this result', 'Professional field assessment and laboratory confirmation may be needed'],
                'consult' => 'Request prompt agricultural assessment, especially when several nearby plants show progressive wilt.',
            ],
            'banana_bunchy_top_disease' => [
                'title' => 'Banana Bunchy Top Disease', 'path' => 'Either screening path', 'tone' => 'warning',
                'summary' => 'The crown or upper leaves may show features consistent with Banana Bunchy Top Disease.',
                'signs' => ['Short, narrow, upright leaves clustered near the crown', 'Reduced spacing between emerging leaves', 'Dark green streaking or dot-dash patterns may be visible'],
                'prevention' => ['Use clean planting material', 'Monitor new leaf growth and nearby plants', 'Discuss appropriate vector management with agricultural personnel'],
                'containment' => ['Avoid transferring planting material from the affected plant', 'Mark and isolate the observation area where practical', 'Seek professional advice before plant removal'],
                'guidance' => ['Capture a clear crown follow-up image', 'Professional visual or laboratory confirmation may be necessary'],
                'consult' => 'Refer promptly when bunching is clear or similar crown symptoms appear in neighboring plants.',
            ],
            'inconclusive' => [
                'title' => 'Inconclusive result', 'path' => 'Either screening path', 'tone' => 'neutral',
                'summary' => 'The image or model confidence was insufficient for a reliable preliminary result.',
                'signs' => ['Blur, obstruction, low resolution, or incorrect plant view', 'Visible symptoms outside the supported classes', 'Confidence below the configured threshold'],
                'prevention' => ['Retake the photo in good natural light', 'Center the required leaf, crown, or whole plant', 'Keep the camera steady and avoid digital zoom'],
                'containment' => ['Do not take disease-specific action from an inconclusive result.'],
                'guidance' => ['Submit another photograph using the capture guide', 'Refer the case if visible decline is concerning'],
                'consult' => 'Seek professional assessment when the plant is worsening or the required image cannot be obtained.',
            ],
        ];
    }
}
