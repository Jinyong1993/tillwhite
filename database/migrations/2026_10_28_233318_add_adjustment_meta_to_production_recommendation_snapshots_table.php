<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'production_recommendation_snapshots',
            function (Blueprint $table) {
                $table->json('adjustment_meta')->nullable();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'production_recommendation_snapshots',
            function (Blueprint $table) {
                $table->dropColumn('adjustment_meta');
            }
        );
    }
};
