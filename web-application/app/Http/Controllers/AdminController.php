<?php

namespace App\Http\Controllers;

use App\Models\Advisory;
use App\Models\AuditLog;
use App\Models\DiseaseClass;
use App\Models\ModelVersion;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class AdminController extends Controller
{
    public function index()
    {
        return view('admin.index', [
            'users' => User::orderBy('name')->get(),
            'diseases' => DiseaseClass::with('activeAdvisory')->orderBy('display_name')->get(),
            'models' => ModelVersion::latest()->get(),
            'logs' => AuditLog::with('user')->latest('timestamp')->limit(30)->get(),
        ]);
    }

    public function storeUser(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:255|unique:users',
            'role' => 'required|in:farm_owner,monitoring_personnel,system_administrator',
            'password' => ['required', Password::min(8)],
        ]);
        $user = User::create($data + ['status' => 'active']);
        $audit->record('admin.user_created', $user, metadata: ['role' => $user->role]);
        return back()->with('success', 'Authorized user account created.');
    }

    public function updateUser(Request $request, User $user, AuditLogger $audit)
    {
        $data = $request->validate([
            'role' => 'required|in:farm_owner,monitoring_personnel,system_administrator',
            'status' => 'required|in:active,inactive',
        ]);
        abort_if($user->is($request->user()) && $data['status'] === 'inactive', 422, 'You cannot deactivate your current account.');
        $user->update($data);
        $audit->record('admin.user_updated', $user, metadata: $data);
        return back()->with('success', 'User access updated.');
    }

    public function storeDisease(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'technical_name' => 'required|alpha_dash|max:100|unique:disease_classes',
            'display_name' => 'required|string|max:120',
            'description' => 'nullable|string|max:2000',
        ]);
        $disease = DiseaseClass::create($data + ['screening_path' => 'all', 'active' => true]);
        $audit->record('admin.disease_created', $disease);
        return back()->with('success', 'Supported category created. Activate it only after model validation.');
    }

    public function updateDisease(Request $request, DiseaseClass $disease, AuditLogger $audit)
    {
        $data = $request->validate(['display_name' => 'required|string|max:120', 'active' => 'required|boolean']);
        $disease->update($data);
        $audit->record('admin.disease_updated', $disease, metadata: ['active' => (bool) $data['active']]);
        return back()->with('success', 'Supported category updated without deleting historical results.');
    }

    public function storeAdvisory(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'disease_id' => 'required|exists:disease_classes,id',
            'version_label' => 'required|string|max:50',
            'visible_signs' => 'required|string',
            'prevention' => 'required|string',
            'containment_reminders' => 'required|string',
            'general_guidance' => 'required|string',
            'consultation_guidance' => 'required|string',
        ]);

        $advisory = DB::transaction(function () use ($data, $request) {
            Advisory::where('disease_id', $data['disease_id'])->where('active', true)->update(['active' => false]);
            return Advisory::create($data + ['language' => 'en', 'reviewed_by' => $request->user()->id, 'active' => true]);
        });
        $audit->record('admin.advisory_version_created', $advisory, metadata: ['version' => $data['version_label']]);
        return back()->with('success', 'New advisory version activated; previous versions remain stored.');
    }

    public function storeModel(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'architecture' => 'required|string|max:120',
            'version_name' => 'required|string|max:120|unique:model_versions',
            'confidence_threshold' => 'required|numeric|min:0|max:1',
            'model_size' => 'nullable|string|max:50',
            'inference_summary' => 'nullable|string|max:2000',
            'metrics_summary' => 'nullable|json',
        ]);
        if (isset($data['metrics_summary'])) $data['metrics_summary'] = json_decode($data['metrics_summary'], true);

        $model = DB::transaction(function () use ($data) {
            ModelVersion::where('active', true)->update(['active' => false]);
            return ModelVersion::create($data + ['screening_path' => 'all', 'active' => true]);
        });
        $audit->record('admin.model_activated', $model, metadata: ['threshold' => $model->confidence_threshold]);
        return back()->with('success', 'Model registry entry activated for both screening paths.');
    }
}
