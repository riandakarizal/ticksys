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
        Schema::create('eqt_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('record_type', 50); // project, handover, vehicle, maintenance
            $table->unsignedBigInteger('record_id');
            $table->string('record_label', 300)->nullable();
            $table->enum('action', ['created', 'updated', 'archived', 'restored', 'imported']);
            $table->enum('source', ['manual', 'import'])->default('manual');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['record_type', 'record_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eqt_change_logs');
    }
};
