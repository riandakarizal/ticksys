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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();

            $table->string('requester_id', 20)->nullable();
            $table->foreign('requester_id')->references('id')->on('users')->nullOnDelete();

            $table->string('created_by', 20)->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->string('assigned_to', 20)->nullable();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();

            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('sla_policy_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('merged_into_ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->foreignId('split_from_ticket_id')->nullable()->constrained('tickets')->nullOnDelete();

            // Aset PRISM (ast_main) yang dilaporkan bermasalah oleh tiket ini — opsional,
            // karena mayoritas project tidak memiliki aset yang tercatat (lihat docs/PRISM-DATABASE.md).
            // Tidak pakai FK constraint keras: ast_main dibuat migration terpisah (legacy PRISM
            // table, PK custom "AST-xxx"), sama seperti pjct_doc/pjct_budget yang juga hanya
            // menyimpan id project sebagai kolom biasa tanpa constraint.
            $table->string('ast_id', 20)->nullable()->index();
            $table->string('asset_cond_before', 225)->nullable();

            // Project PRISM tempat tiket ini dibuat — sumber kebenaran untuk "siapa clientnya"
            // (pjct_main.pjct_client), menggantikan requester berupa akun user asli.
            $table->string('pjct_id', 10)->nullable()->index();
            $table->string('requester_name', 255)->nullable();

            $table->string('ticket_number')->unique();
            $table->string('subject');
            $table->longText('description');
            $table->string('status')->default('open');
            $table->string('priority')->default('medium');
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('first_responded_at')->nullable();
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamp('response_due_at')->nullable();
            $table->timestamp('resolution_due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['assigned_to', 'status']);
            $table->index(['ast_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
