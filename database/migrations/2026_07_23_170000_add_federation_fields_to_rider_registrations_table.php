<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Runs on the default connection, like every other migration.
 *
 * This previously targeted the 'mssql' connection explicitly and skipped itself
 * during tests, so the columns existed only in one environment while the models
 * read another - the app 500'd with "no column named first_name". Guarded with
 * hasColumn so it is safe to re-run where the columns already exist.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $columns = [
        'gender_id', 'nationality_id', 'city_id', 'country_id',
        'email', 'mobile', 'address', 'po_box',
    ];

    public function up(): void
    {
        Schema::table('rider_registrations', function (Blueprint $table) {
            foreach ($this->columns as $column) {
                if (!Schema::hasColumn('rider_registrations', $column)) {
                    $table->string($column)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('rider_registrations', function (Blueprint $table) {
            $table->dropColumn($this->columns);
        });
    }
};
