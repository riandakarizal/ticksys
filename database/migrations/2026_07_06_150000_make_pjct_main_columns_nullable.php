<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE pjct_main MODIFY pjct_contract VARCHAR(255) NULL');
        DB::statement('ALTER TABLE pjct_main MODIFY pjct_codate DATE NULL');
        DB::statement('ALTER TABLE pjct_main MODIFY pjct_type VARCHAR(255) NULL');
        DB::statement('ALTER TABLE pjct_main MODIFY pjct_client VARCHAR(255) NULL');
        DB::statement('ALTER TABLE pjct_main MODIFY pjct_area VARCHAR(255) NULL');
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE pjct_main MODIFY pjct_contract VARCHAR(255) NOT NULL DEFAULT ''");
        DB::statement("ALTER TABLE pjct_main MODIFY pjct_codate DATE NOT NULL DEFAULT '1970-01-01'");
        DB::statement("ALTER TABLE pjct_main MODIFY pjct_type VARCHAR(255) NOT NULL DEFAULT ''");
        DB::statement("ALTER TABLE pjct_main MODIFY pjct_client VARCHAR(255) NOT NULL DEFAULT ''");
        DB::statement("ALTER TABLE pjct_main MODIFY pjct_area VARCHAR(255) NOT NULL DEFAULT ''");
    }
};
