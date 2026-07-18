<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['eqt_projects', 'eqt_handovers', 'eqt_vehicles', 'eqt_maintenances'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (['eqt_projects', 'eqt_handovers', 'eqt_vehicles', 'eqt_maintenances'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropSoftDeletes();
            });
        }
    }
};
