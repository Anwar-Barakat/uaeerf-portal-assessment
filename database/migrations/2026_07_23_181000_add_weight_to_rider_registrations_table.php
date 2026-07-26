<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Default connection; guarded so it is safe to re-run. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('rider_registrations', 'weight')) {
            return;
        }

        Schema::table('rider_registrations', function (Blueprint $table) {
            $table->string('weight')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rider_registrations', function (Blueprint $table) {
            $table->dropColumn('weight');
        });
    }
};
