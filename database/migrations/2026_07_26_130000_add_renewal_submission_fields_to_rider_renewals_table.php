<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields Submit_PersonRenewal requires but the form never captured.
 *
 * Weight is required for every discipline ("Please enter a valid weight in
 * kilogram"). Show jumping additionally requires a division level and a
 * competition level - without the latter the service answers "Invalid Category"
 * and then fails to deserialise an empty value into an Integer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rider_renewals', function (Blueprint $table) {
            $table->string('weight')->nullable()->after('discipline_id');
            $table->integer('jumping_division_level')->nullable()->after('weight');
            $table->string('competition_level')->nullable()->after('jumping_division_level');
        });
    }

    public function down(): void
    {
        Schema::table('rider_renewals', function (Blueprint $table) {
            $table->dropColumn(['weight', 'jumping_division_level', 'competition_level']);
        });
    }
};
