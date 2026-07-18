<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Device;
use App\Models\CustomField;
use App\Models\SlaPolicy;
use App\Models\Team;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $company = Company::create([
            'name' => 'Acme Indonesia',
            'code' => 'ACME-ID',
            'contact_email' => 'hello@acme.test',
            'auto_close_days' => 3,
        ]);

        $admin = User::create([
            'company_id' => $company->id,
            'name' => 'Alya Admin',
            'email' => 'admin@acme.test',
            'role' => 'admin',
            'job_title' => 'System Administrator',
            'password' => 'password',
        ]);

        $supervisor = User::create([
            'company_id' => $company->id,
            'name' => 'Surya Supervisor',
            'email' => 'supervisor@acme.test',
            'role' => 'supervisor',
            'job_title' => 'Support Supervisor',
            'password' => 'password',
        ]);

        $agent = User::create([
            'company_id' => $company->id,
            'name' => 'Arif Agent',
            'email' => 'agent@acme.test',
            'role' => 'agent',
            'job_title' => 'IT Support',
            'password' => 'password',
        ]);

        $client = User::create([
            'company_id' => $company->id,
            'name' => 'Citra Client',
            'email' => 'client@acme.test',
            'role' => 'client',
            'job_title' => 'Finance Staff',
            'password' => 'password',
        ]);

        $itTeam = Team::create([
            'company_id' => $company->id,
            'lead_user_id' => $supervisor->id,
            'name' => 'IT Operations',
            'code' => 'IT-OPS',
            'description' => 'Infrastructure and endpoint support',
        ]);

        $appTeam = Team::create([
            'company_id' => $company->id,
            'lead_user_id' => $supervisor->id,
            'name' => 'Application Support',
            'code' => 'APP-SUP',
            'description' => 'Business application support',
        ]);

        $itTeam->members()->sync([$agent->id, $supervisor->id, $client->id]);
        $appTeam->members()->sync([$agent->id, $supervisor->id, $client->id]);

        $standardSla = SlaPolicy::create([
            'company_id' => $company->id,
            'name' => 'Standard',
            'response_minutes' => 60,
            'resolution_minutes' => 240,
            'is_default' => true,
        ]);

        SlaPolicy::create([
            'company_id' => $company->id,
            'name' => 'Critical',
            'response_minutes' => 15,
            'resolution_minutes' => 120,
            'is_default' => false,
        ]);

        $network = Category::create([
            'company_id' => $company->id,
            'team_id' => $itTeam->id,
            'auto_assign_user_id' => $agent->id,
            'name' => 'Network',
            'slug' => 'network',
            'color' => '#2563eb',
        ]);

        $vpn = Category::create([
            'company_id' => $company->id,
            'parent_id' => $network->id,
            'team_id' => $itTeam->id,
            'auto_assign_user_id' => $agent->id,
            'name' => 'VPN',
            'slug' => 'vpn',
            'color' => '#1d4ed8',
        ]);

        $application = Category::create([
            'company_id' => $company->id,
            'team_id' => $appTeam->id,
            'auto_assign_user_id' => $supervisor->id,
            'name' => 'Application',
            'slug' => 'application',
            'color' => '#7c3aed',
        ]);

        Category::create([
            'company_id' => $company->id,
            'parent_id' => $application->id,
            'team_id' => $appTeam->id,
            'auto_assign_user_id' => $supervisor->id,
            'name' => 'ERP',
            'slug' => 'erp',
            'color' => '#6d28d9',
        ]);

        CustomField::create([
            'company_id' => $company->id,
            'name' => 'Affected Device',
            'key' => 'affected_device',
            'type' => 'text',
            'is_required' => false,
        ]);

        $vpnLaptop = Device::create([
            'company_id' => $company->id,
            'team_id' => $itTeam->id,
            'name' => 'Laptop Finance-01',
            'asset_code' => 'ACME-LPT-001',
            'device_type' => 'Laptop',
            'serial_number' => 'SN-LPT-001',
            'ip_address' => '10.10.1.21',
            'location' => 'Finance Floor',
            'notes' => 'Primary finance laptop',
            'is_active' => true,
        ]);

        Device::create([
            'company_id' => $company->id,
            'team_id' => $itTeam->id,
            'name' => 'VPN Router Branch-01',
            'asset_code' => 'ACME-RTR-014',
            'device_type' => 'Router',
            'serial_number' => 'SN-RTR-014',
            'ip_address' => '10.20.0.1',
            'location' => 'Bandung Branch',
            'notes' => 'Router for branch VPN access',
            'is_active' => true,
        ]);

        Device::create([
            'company_id' => $company->id,
            'team_id' => $appTeam->id,
            'name' => 'ERP Application Server',
            'asset_code' => 'ACME-SRV-002',
            'device_type' => 'Server',
            'serial_number' => 'SN-SRV-002',
            'ip_address' => '10.30.0.15',
            'location' => 'Jakarta DC',
            'notes' => 'Handles ERP approval workflow',
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'company_id' => $company->id,
            'requester_id' => $client->id,
            'created_by' => $client->id,
            'assigned_to' => $agent->id,
            'team_id' => $itTeam->id,
            'device_id' => $vpnLaptop->id,
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

        DB::table('activity_logs')->insert([
            [
                'company_id'  => $company->id,
                'ticket_id'   => $ticket->id,
                'user_id'     => $client->id,
                'action'      => 'ticket_created',
                'description' => 'Ticket dibuat',
                'properties'  => json_encode(['status' => 'open', 'priority' => 'high', 'assigned_to' => $agent->id]),
                'created_at'  => now()->subMinutes(30),
                'updated_at'  => now()->subMinutes(30),
            ],
            [
                'company_id'  => $company->id,
                'ticket_id'   => $ticket->id,
                'user_id'     => $client->id,
                'action'      => 'ticket_status_changed',
                'description' => 'Status awal ticket: Open',
                'properties'  => json_encode(['from' => null, 'to' => 'open']),
                'created_at'  => now()->subMinutes(30),
                'updated_at'  => now()->subMinutes(30),
            ],
            [
                'company_id'  => $company->id,
                'ticket_id'   => $ticket->id,
                'user_id'     => $agent->id,
                'action'      => 'reply_added',
                'description' => 'Reply added',
                'properties'  => json_encode([]),
                'created_at'  => now()->subMinutes(20),
                'updated_at'  => now()->subMinutes(20),
            ],
            [
                'company_id'  => $company->id,
                'ticket_id'   => $ticket->id,
                'user_id'     => $agent->id,
                'action'      => 'ticket_status_changed',
                'description' => 'Status ticket berubah dari Open ke In Progress',
                'properties'  => json_encode(['from' => 'open', 'to' => 'in_progress']),
                'created_at'  => now()->subMinutes(20),
                'updated_at'  => now()->subMinutes(20),
            ],
            [
                'company_id'  => $company->id,
                'ticket_id'   => $ticket->id,
                'user_id'     => $supervisor->id,
                'action'      => 'internal_note_added',
                'description' => 'Internal note added',
                'properties'  => json_encode([]),
                'created_at'  => now()->subMinutes(10),
                'updated_at'  => now()->subMinutes(10),
            ],
        ]);

        $pendingTicket = Ticket::create([
            'company_id' => $company->id,
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

        DB::table('activity_logs')->insert([
            [
                'company_id'  => $company->id,
                'ticket_id'   => $pendingTicket->id,
                'user_id'     => $client->id,
                'action'      => 'ticket_created',
                'description' => 'Ticket dibuat',
                'properties'  => json_encode(['status' => 'open', 'priority' => 'medium', 'assigned_to' => $supervisor->id]),
                'created_at'  => now()->subHours(4),
                'updated_at'  => now()->subHours(4),
            ],
            [
                'company_id'  => $company->id,
                'ticket_id'   => $pendingTicket->id,
                'user_id'     => $client->id,
                'action'      => 'ticket_status_changed',
                'description' => 'Status awal ticket: Open',
                'properties'  => json_encode(['from' => null, 'to' => 'open']),
                'created_at'  => now()->subHours(4),
                'updated_at'  => now()->subHours(4),
            ],
            [
                'company_id'  => $company->id,
                'ticket_id'   => $pendingTicket->id,
                'user_id'     => $supervisor->id,
                'action'      => 'ticket_status_changed',
                'description' => 'Status ticket berubah dari Open ke Pending',
                'properties'  => json_encode(['from' => 'open', 'to' => 'pending']),
                'created_at'  => now()->subHours(3),
                'updated_at'  => now()->subHours(3),
            ],
        ]);
    }
}
