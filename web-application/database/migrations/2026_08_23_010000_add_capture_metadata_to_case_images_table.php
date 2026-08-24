<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_images', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('follow_up_id')->index();
            $table->string('specific_view')->nullable()->after('image_path')->index();
        });
    }

    public function down(): void
    {
        Schema::table('case_images', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'specific_view']);
        });
    }
};
