<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Default connection; guarded so it is safe to re-run. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rider_registrations', function (Blueprint $table) {
            if (!Schema::hasColumn('rider_registrations', 'first_name')) {
                $table->string('first_name')->nullable();
            }

            if (!Schema::hasColumn('rider_registrations', 'last_name')) {
                $table->string('last_name')->nullable();
            }

            if (!Schema::hasColumn('rider_registrations', 'visa_category')) {
                $table->string('visa_category')->nullable();
            }

            if (!Schema::hasColumn('rider_registrations', 'eid')) {
                $table->string('eid')->nullable();
            }

            if (!Schema::hasColumn('rider_registrations', 'register_season')) {
                $table->boolean('register_season')->default(true);
            }

            if (!Schema::hasColumn('rider_registrations', 'register_fei')) {
                $table->boolean('register_fei')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('rider_registrations', function (Blueprint $table) {
            $table->dropColumn([
                'first_name', 'last_name', 'visa_category',
                'eid', 'register_season', 'register_fei',
            ]);
        });
    }
};
