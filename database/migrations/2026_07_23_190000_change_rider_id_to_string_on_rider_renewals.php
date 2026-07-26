<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Federation rider ids are alphanumeric (E0022141), so the column cannot be an
 * integer. Previously issued as raw T-SQL against the 'mssql' connection, which
 * left the default connection with the wrong type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rider_renewals', function (Blueprint $table) {
            $table->string('rider_id', 20)->change();
        });
    }

    public function down(): void
    {
        Schema::table('rider_renewals', function (Blueprint $table) {
            $table->integer('rider_id')->change();
        });
    }
};
