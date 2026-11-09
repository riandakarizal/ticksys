<?php

namespace Database\Seeders;

use App\Models\AstMain;
use App\Models\Category;
use App\Models\CustomField;
use App\Models\PjctMain;
use App\Models\SlaPolicy;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Support\Helpdesk;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::create([
            'user_empid'  => 'EMP001',
            'user_name'   => 'Alya Admin',
            'user_email'  => 'admin@ias.test',
            'user_pass'   => 'password',
            'user_level'  => 'L2',
            'user_role'   => 'admin',
            'user_unit'   => 'Technology Operation & Maintenance',
            'user_div'    => 'Equipment & Technology Operation & Maintenance',
            'user_parid'  => '-',
            'user_status' => 'active',
        ]);

        $siteAdmin = User::create([
            'user_empid'  => 'EMP002',
            'user_name'   => 'Arif Siteadmin',
            'user_email'  => 'siteadmin@ias.test',
            'user_pass'   => 'password',
            'user_level'  => 'L5',
            'user_role'   => 'siteadmin',
            'user_unit'   => 'Technology Operation & Maintenance',
            'user_div'    => 'Equipment & Technology Operation & Maintenance',
            'user_parid'  => $admin->id,
            'user_status' => 'active',
        ]);

        $client = User::create([
            'user_empid'  => 'EMP003',
            'user_name'   => 'Citra Client',
            'user_email'  => 'client@ias.test',
            'user_pass'   => 'password',
            'user_level'  => 'L7',
            'user_role'   => 'user',
            'user_unit'   => 'Technology Operation & Maintenance',
            'user_div'    => 'Equipment & Technology Operation & Maintenance',
            'user_parid'  => $siteAdmin->id,
            'user_status' => 'active',
        ]);

        $itTeam = Team::create([
            'lead_user_id' => $siteAdmin->id,
            'name' => 'IT Operations',
            'code' => 'IT-OPS',
            'description' => 'Infrastructure and endpoint support',
        ]);

        $itTeam->members()->sync([$admin->id, $siteAdmin->id, $client->id]);

        $standardSla = SlaPolicy::create([
            'name' => 'Standard',
            'response_minutes' => 60,
            'resolution_minutes' => 240,
            'is_default' => true,
        ]);

        $network = Category::create([
            'team_id' => $itTeam->id,
            'auto_assign_user_id' => $siteAdmin->id,
            'name' => 'Network',
            'slug' => 'network',
            'color' => '#2563eb',
        ]);

        Category::create([
            'parent_id' => $network->id,
            'team_id' => $itTeam->id,
            'auto_assign_user_id' => $siteAdmin->id,
            'name' => 'VPN',
            'slug' => 'vpn',
            'color' => '#1d4ed8',
        ]);

        CustomField::create([
            'name' => 'Business Impact',
            'key' => 'business_impact',
            'type' => 'text',
            'is_required' => false,
        ]);

        // Sebuah project + aset PRISM contoh, supaya tiket bisa didemokan terkait ke aset nyata.
        $project = PjctMain::create([
            'pjct_name' => 'Contoh Project Seed',
            'pjct_type' => 'RENT',
            'pjct_status' => 'OG',
            'pjct_div' => 'TC',
        ]);

        $laptop = AstMain::create([
            'ast_type' => 'Laptop',
            'ast_brand' => 'Dell',
            'ast_brandmodel' => 'Latitude 5420',
            'ast_serial' => 'SN-SEED-001',
            'ast_cond' => 'Good',
            'ast_stat' => 'Aktif',
            'ast_pjctid' => $project->id,
        ]);

        // Ticket contoh mengikuti flow teknisi: dibuat role `user` (teknisi), status open, device
        // project terlampir — kondisinya otomatis menjadi Bad sampai ticket di-approve (closed).
        $ticket = Ticket::create([
            'requester_name' => $project->pjct_client,
            'created_by' => $client->id,
            'assigned_to' => $client->id,
            'pjct_id' => $project->id,
            'category_id' => $network->id,
            'sla_policy_id' => $standardSla->id,
            'subject' => 'Laptop meja check-in tidak bisa menyala',
            'description' => 'Laptop mati total sejak pagi setelah tersiram air. Rencana: cek adaptor dan board, eskalasi bila perlu.',
            'issue_type' => 'kerusakan',
            'status' => 'open',
            'priority' => 'high',
            'first_responded_at' => now(),
        ]);
        app(Helpdesk::class)->syncTicketAssets($ticket, [$laptop->id]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $siteAdmin->id,
            'body' => 'Kalau board-nya kena, langsung eskalasi ke pusat ya.',
            'is_internal' => false,
        ]);

        DB::table('activity_logs')->insert([
            'ticket_id'   => $ticket->id,
            'user_id'     => $client->id,
            'action'      => 'ticket_created',
            'description' => 'Ticket dibuat',
            'properties'  => json_encode(['to' => 'open', 'priority' => 'high', 'assets' => [$laptop->id]]),
            'created_at'  => now()->subMinutes(30),
            'updated_at'  => now()->subMinutes(30),
        ]);
    }
}
