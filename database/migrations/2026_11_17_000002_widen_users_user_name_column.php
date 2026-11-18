<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users.user_name dulu varchar(20), padahal nama dari data manpower (pjct_emp.emp_name, varchar 225)
 * sering lebih panjang. Di MySQL (koneksi strict) daftar akun dengan nama > 20 karakter gagal insert → 500.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('user_name', 225)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('user_name', 20)->change();
        });
    }
};
