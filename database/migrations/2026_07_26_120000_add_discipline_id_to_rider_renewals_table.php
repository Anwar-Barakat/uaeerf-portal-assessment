<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Submit_PersonRenewal requires a DivisionID (the discipline being renewed);
 * without it the service answers "Invalid Division ID". The renewal form
 * previously captured only rider and season.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rider_renewals', function (Blueprint $table) {
            $table->integer('discipline_id')->nullable()->after('season_id');
        });
    }

    public function down(): void
    {
        Schema::table('rider_renewals', function (Blueprint $table) {
            $table->dropColumn('discipline_id');
        });
    }
};
