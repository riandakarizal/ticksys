<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['requester_id']);
            $table->dropForeign(['created_by']);
        });

        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('app_notifications', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('requester_id')->nullable()->change();
            $table->foreignId('created_by')->nullable()->change();
            $table->foreign('requester_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('app_notifications', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['requester_id']);
            $table->dropForeign(['created_by']);
        });

        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('app_notifications', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('requester_id')->nullable(false)->change();
            $table->foreignId('created_by')->nullable(false)->change();
            $table->foreign('requester_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('app_notifications', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
