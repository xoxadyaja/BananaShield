<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('farm_sections', function (Blueprint $table) {
            $table->json('plant_codenames')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('farm_sections', function (Blueprint $table) {
            $table->dropColumn('plant_codenames');
        });
    }
};