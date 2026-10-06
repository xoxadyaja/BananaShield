<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->foreignId('farm_profile_id')->nullable()->after('observed_at')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('farm_profile_id');
        });
    }
};