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
        // Sudah ada di database prism asli — no-op di sana.
        if (Schema::hasTable('pjct_doc')) {
            return;
        }

        Schema::create('pjct_doc', function (Blueprint $table) {
            $table->string('id', 20)->primary();
            $table->string('doc_number', 225)->nullable();
            $table->string('doc_pjctid', 20)->index();
            $table->string('doc_type', 225); // KONTRAK, RKST, RAB, BAST, SOP, BOQ
            $table->string('doc_filetype', 225)->nullable();
            $table->text('doc_filename');
            $table->text('doc_filepath');
            $table->text('doc_desc')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pjct_doc');
    }
};
