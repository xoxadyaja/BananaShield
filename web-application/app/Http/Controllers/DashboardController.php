<?php

namespace App\Http\Controllers;

use App\Models\FarmProfile;
use App\Models\PlantCase;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $cases = PlantCase::query()
            ->visibleTo($user)
            ->with('latestPrediction')
            ->latest('observed_at');

        return view('dashboard', [
            'user' => $user,
            'caseCount' => (clone $cases)->count(),
            'pendingReviewCount' => (clone $cases)->where('review_status', 'pending')->count(),
            'latestCase' => (clone $cases)->first(),
            'recentCases' => (clone $cases)->limit(5)->get(),
            'farmProfile' => $user->role === 'farm_owner'
                ? FarmProfile::query()->withCount(['sections as active_sections_count' => fn ($query) => $query->where('active', true)])->first()
                : null,
        ]);
    }
}
