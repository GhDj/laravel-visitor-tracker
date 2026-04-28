<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $visitorsTable = config('visitor-tracker.tables.visitors', 'visitors');
        $visitsTable = config('visitor-tracker.tables.visits', 'visits');

        Schema::table($visitorsTable, function (Blueprint $table) use ($visitorsTable) {
            $table->index('created_at', $visitorsTable.'_created_at_index');
            $table->index(['created_at', 'is_bot'], $visitorsTable.'_created_at_is_bot_index');
            $table->index('browser', $visitorsTable.'_browser_index');
            $table->index('platform', $visitorsTable.'_platform_index');
            $table->index('country_code', $visitorsTable.'_country_code_index');
        });

        Schema::table($visitsTable, function (Blueprint $table) use ($visitsTable) {
            $table->index(['visitor_id', 'created_at'], $visitsTable.'_visitor_id_created_at_index');
        });
    }

    public function down(): void
    {
        $visitorsTable = config('visitor-tracker.tables.visitors', 'visitors');
        $visitsTable = config('visitor-tracker.tables.visits', 'visits');

        Schema::table($visitorsTable, function (Blueprint $table) use ($visitorsTable) {
            $table->dropIndex($visitorsTable.'_created_at_index');
            $table->dropIndex($visitorsTable.'_created_at_is_bot_index');
            $table->dropIndex($visitorsTable.'_browser_index');
            $table->dropIndex($visitorsTable.'_platform_index');
            $table->dropIndex($visitorsTable.'_country_code_index');
        });

        Schema::table($visitsTable, function (Blueprint $table) use ($visitsTable) {
            $table->dropIndex($visitsTable.'_visitor_id_created_at_index');
        });
    }
};
