<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CustomField;
use App\Models\SlaPolicy;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $tenant = Tenant::create([
            'name' => 'Acme Indonesia',
            'code' => 'ACME-ID',
            'contact_email' => 'hello@acme.test',
            'auto_close_days' => 3,
        ]);

        $admin = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Alya Admin',
            'email' => 'admin@acme.test',
            'role' => 'admin',
            'job_title' => 'System Administrator',
            'password' => 'password',
        ]);

        $supervisor = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Surya Supervisor',
            'email' => 'supervisor@acme.test',
            'role' => 'supervisor',
            'job_title' => 'Support Supervisor',
            'password' => 'password',
        ]);

        $agent = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Arif Agent',
            'email' => 'agent@acme.test',
            'role' => 'agent',
            'job_title' => 'IT Support',
            'password' => 'password',
        ]);

        $client = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Citra Client',
            'email' => 'client@acme.test',
            'role' => 'client',
            'job_title' => 'Finance Staff',
            'password' => 'password',
        ]);

        $itTeam = Team::create([
            'tenant_id' => $tenant->id,
            'lead_user_id' => $supervisor->id,
            'name' => 'IT Operations',
            'code' => 'IT-OPS',
            'description' => 'Infrastructure and endpoint support',
        ]);

        $appTeam = Team::create([
            'tenant_id' => $tenant->id,
            'lead_user_id' => $supervisor->id,
            'name' => 'Application Support',
            'code' => 'APP-SUP',
            'description' => 'Business application support',
        ]);

        $itTeam->members()->sync([$agent->id, $supervisor->id, $client->id]);
        $appTeam->members()->sync([$agent->id, $supervisor->id, $client->id]);

        $standardSla = SlaPolicy::create([
            'tenant_id' => $tenant->id,
            'name' => 'Standard',
            'response_minutes' => 60,
            'resolution_minutes' => 240,
            'is_default' => true,
        ]);

        SlaPolicy::create([
            'tenant_id' => $tenant->id,
            'name' => 'Critical',
            'response_minutes' => 15,
            'resolution_minutes' => 120,
            'is_default' => false,
        ]);

        $network = Category::create([
            'tenant_id' => $tenant->id,
            'team_id' => $itTeam->id,
            'auto_assign_user_id' => $agent->id,
            'name' => 'Network',
            'slug' => 'network',
            'color' => '#2563eb',
        ]);

        $vpn = Category::create([
            'tenant_id' => $tenant->id,
            'parent_id' => $network->id,
            'team_id' => $itTeam->id,
            'auto_assign_user_id' => $agent->id,
            'name' => 'VPN',
            'slug' => 'vpn',
            'color' => '#1d4ed8',
        ]);

        $application = Category::create([
            'tenant_id' => $tenant->id,
            'team_id' => $appTeam->id,
            'auto_assign_user_id' => $supervisor->id,
            'name' => 'Application',
            'slug' => 'application',
            'color' => '#7c3aed',
        ]);

        Category::create([
            'tenant_id' => $tenant->id,
            'parent_id' => $application->id,
            'team_id' => $appTeam->id,
            'auto_assign_user_id' => $supervisor->id,
            'name' => 'ERP',
            'slug' => 'erp',
            'color' => '#6d28d9',
        ]);

        CustomField::create([
            'tenant_id' => $tenant->id,
            'name' => 'Affected Device',
            'key' => 'affected_device',
            'type' => 'text',
            'is_required' => false,
        ]);

        CustomField::create([
            'tenant_id' => $tenant->id,
            'name' => 'Business Impact',
            'key' => 'business_impact',
            'type' => 'select',
            'options' => ['Low', 'Medium', 'High'],
            'is_required' => true,
        ]);

        $ticket = Ticket::create([
            'tenant_id' => $tenant->id,
            'requester_id' => $client->id,
            'created_by' => $client->id,
            'assigned_to' => $agent->id,
            'team_id' => $itTeam->id,
            'category_id' => $network->id,
            'subcategory_id' => $vpn->id,
            'sla_policy_id' => $standardSla->id,
            'subject' => 'VPN kantor tidak bisa terkoneksi',
            'description' => 'Sejak pagi VPN gagal login dan akses server accounting terputus.',
            'status' => 'in_progress',
            'priority' => 'high',
            'tags' => ['vpn', 'remote', 'finance'],
            'first_responded_at' => now()->subMinutes(20),
            'last_reply_at' => now()->subMinutes(10),
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'body' => 'Kami sedang cek endpoint VPN dan policy user. Mohon standby 15 menit ya.',
            'is_internal' => false,
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $supervisor->id,
            'body' => 'Pastikan log authenticator dan timeout ISP diperiksa.',
            'is_internal' => true,
        ]);

        Ticket::create([
            'tenant_id' => $tenant->id,
            'requester_id' => $client->id,
            'created_by' => $client->id,
            'assigned_to' => $supervisor->id,
            'team_id' => $appTeam->id,
            'category_id' => $application->id,
            'sla_policy_id' => $standardSla->id,
            'subject' => 'Approval invoice di ERP lambat',
            'description' => 'Submit approval invoice butuh waktu lebih dari 1 menit.',
            'status' => 'pending',
            'priority' => 'medium',
            'last_reply_at' => now()->subHours(3),
        ]);
    }
}
