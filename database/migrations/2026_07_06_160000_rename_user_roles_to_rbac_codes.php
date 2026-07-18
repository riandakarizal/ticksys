<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Order matters: admin->superadmin must run before supervisor->admin,
        // otherwise the second update would re-catch rows just renamed to 'admin'.
        DB::table('users')->where('user_role', 'admin')->update(['user_role' => 'superadmin']);
        DB::table('users')->where('user_role', 'supervisor')->update(['user_role' => 'admin']);
        DB::table('users')->where('user_role', 'agent')->update(['user_role' => 'siteadmin']);
        DB::table('users')->where('user_role', 'client')->update(['user_role' => 'user']);
    }

    public function down(): void
    {
        DB::table('users')->where('user_role', 'user')->update(['user_role' => 'client']);
        DB::table('users')->where('user_role', 'siteadmin')->update(['user_role' => 'agent']);
        DB::table('users')->where('user_role', 'admin')->update(['user_role' => 'supervisor']);
        DB::table('users')->where('user_role', 'superadmin')->update(['user_role' => 'admin']);
    }
};
