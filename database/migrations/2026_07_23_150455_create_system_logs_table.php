<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 20)->nullable();
            $table->string('action', 20); // create, update, delete
            $table->string('loggable_type', 100); // e.g. PjctMain, PjctBudget
            $table->string('loggable_id', 30); // e.g. PJ0001, USR-001
            $table->string('description', 255);
            $table->json('properties')->nullable(); // changed fields, old/new values
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['loggable_type', 'loggable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_logs');
    }
};
