<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_images', function (Blueprint $table) {
            $table->string('detected_part')->nullable()->after('image_quality_status');
            $table->string('part_detection_provider')->nullable()->after('detected_part');
            $table->string('part_detection_status')->nullable()->after('part_detection_provider')->index();
        });
    }

    public function down(): void
    {
        Schema::table('case_images', function (Blueprint $table) {
            $table->dropIndex(['part_detection_status']);
            $table->dropColumn([
                'detected_part',
                'part_detection_provider',
                'part_detection_status',
            ]);
        });
    }
};
