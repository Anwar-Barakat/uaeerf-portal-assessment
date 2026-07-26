<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ClassEntriesWeb.RiderID and .HorseID are varchar(50) and hold values like
     * E0007711 / H000103, so the staging table must not narrow them to int.
     */
    public function up(): void
    {
        Schema::table('show_jumping_entries', function (Blueprint $table) {
            $table->string('rider_id', 50)->change();
            $table->string('horse_id', 50)->change();
        });
    }

    public function down(): void
    {
        Schema::table('show_jumping_entries', function (Blueprint $table) {
            $table->integer('rider_id')->change();
            $table->integer('horse_id')->change();
        });
    }
};
