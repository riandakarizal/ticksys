<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_team', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['category_id', 'team_id']);
        });

        DB::table('categories')
            ->whereNotNull('team_id')
            ->orderBy('id')
            ->get(['id', 'team_id'])
            ->each(function ($category): void {
                DB::table('category_team')->updateOrInsert(
                    ['category_id' => $category->id, 'team_id' => $category->team_id],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_team');
    }
};
