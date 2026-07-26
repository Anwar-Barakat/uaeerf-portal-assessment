<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ClassEntriesWeb.SeasonCode is populated on every real portal entry;
     * leaving it null risks the entry being invisible to season-scoped views.
     * Resolved server-side from the chosen event rather than trusted from the
     * client.
     */
    public function up(): void
    {
        Schema::table('show_jumping_entries', function (Blueprint $table) {
            $table->integer('season_code')->nullable()->after('class_id');
        });
    }

    public function down(): void
    {
        Schema::table('show_jumping_entries', function (Blueprint $table) {
            $table->dropColumn('season_code');
        });
    }
};
