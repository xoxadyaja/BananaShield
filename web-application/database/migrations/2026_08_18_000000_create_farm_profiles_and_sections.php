<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('farm_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('farm_name');
            $table->string('barangay')->nullable();
            $table->string('municipality')->nullable();
            $table->string('province')->nullable();
            $table->decimal('total_area_hectares', 10, 2)->nullable();
            $table->text('primary_varieties')->nullable();
            $table->string('notification_email')->nullable();
            $table->json('notification_preferences')->nullable();
            $table->foreignId('managed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('farm_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('area_hectares', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->unique(['farm_profile_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_sections');
        Schema::dropIfExists('farm_profiles');
    }
};
