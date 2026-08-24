<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::table('users')->where('role', 'farmer')->update(['role' => 'monitoring_personnel']);
        DB::table('users')->where('role', 'technician')->update(['role' => 'monitoring_personnel', 'status' => 'inactive']);

        if (Schema::hasTable('farmer_profiles') && ! Schema::hasTable('user_profiles')) {
            Schema::rename('farmer_profiles', 'user_profiles');
        }
        if (Schema::hasColumn('cases', 'farmer_id') && ! Schema::hasColumn('cases', 'submitted_by')) {
            Schema::table('cases', fn (Blueprint $table) => $table->renameColumn('farmer_id', 'submitted_by'));
        }
        if (Schema::hasTable('technician_reviews') && ! Schema::hasTable('professional_consultations')) {
            Schema::rename('technician_reviews', 'professional_consultations');
        }

        Schema::table('cases', function (Blueprint $table) {
            $table->string('review_status')->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
        });

        Schema::table('advisories', function (Blueprint $table) {
            $table->string('version_label')->default('v1');
        });
    }

    public function down(): void
    {
        Schema::table('advisories', fn (Blueprint $table) => $table->dropColumn('version_label'));
        Schema::table('cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review_status', 'review_notes', 'reviewed_at']);
        });
    }
};
