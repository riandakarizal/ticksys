<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('tenants', 'companies');

        $tables = [
            'users', 'teams', 'sla_policies', 'categories',
            'tickets', 'custom_fields', 'activity_logs', 'devices',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $blueprint): void {
                $blueprint->renameColumn('tenant_id', 'company_id');
            });
        }
    }

    public function down(): void
    {
        Schema::rename('companies', 'tenants');

        $tables = [
            'users', 'teams', 'sla_policies', 'categories',
            'tickets', 'custom_fields', 'activity_logs', 'devices',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $blueprint): void {
                $blueprint->renameColumn('company_id', 'tenant_id');
            });
        }
    }
};
