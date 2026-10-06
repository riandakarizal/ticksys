<?php

namespace Database\Seeders;

use App\Models\AstMain;
use App\Models\Category;
use App\Models\PjctMain;
use App\Models\SlaPolicy;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class E2EDemoSeeder extends Seeder
{
    public function run(): void
    {
        // ── Users ─────────────────────────────────────────────────────────────
        $admin = User::where('user_role', 'superadmin')->first();

        $teknisi = User::firstOrCreate(
            ['user_email' => 'aldo@ias.test'],
            [
                'user_empid'  => 'EMP-E2E-1',
                'user_name'   => 'Aldo Teknisi',
                'user_pass'   => 'password',
                'user_level'  => 'L6',
                'user_role'   => 'siteadmin',
                'user_unit'   => 'Technology Operation & Maintenance',
                'user_div'    => 'Equipment & Technology Operation & Maintenance',
                'user_parid'  => $admin?->id ?? '-',
                'user_status' => 'active',
            ]
        );

        $client = User::firstOrCreate(
            ['user_email' => 'budi@ias.test'],
            [
                'user_empid'  => 'EMP-E2E-2',
                'user_name'   => 'Budi Client',
                'user_pass'   => 'password',
                'user_level'  => 'L7',
                'user_role'   => 'user',
                'user_unit'   => 'Technology Operation & Maintenance',
                'user_div'    => 'Equipment & Technology Operation & Maintenance',
                'user_parid'  => $teknisi->id,
                'user_status' => 'active',
            ]
        );

        // ── SLA Policy ────────────────────────────────────────────────────────
        $sla = SlaPolicy::where('is_default', true)->first()
            ?? SlaPolicy::create([
                'name'               => 'Standard',
                'response_minutes'   => 60,
                'resolution_minutes' => 480,
                'is_default'         => true,
            ]);

        // ── Project (grup helpdesk internal) ─────────────────────────────────
        $project = Team::firstOrCreate(
            ['code' => 'SEAT-IAS'],
            [
                'lead_user_id' => $admin?->id,
                'name'         => 'Seat Management IAS',
                'description'  => 'Pengelolaan aset dan perangkat kerja karyawan IAS',
            ]
        );

        $syncIds = collect([$admin?->id, $teknisi->id, $client->id])->filter()->all();
        $project->members()->syncWithoutDetaching($syncIds);

        // ── Categories ────────────────────────────────────────────────────────
        $hardware = Category::firstOrCreate(
            ['slug' => 'hardware-ias'],
            [
                'team_id' => $project->id,
                'name'    => 'Hardware',
                'color'   => '#0284c7',
            ]
        );

        $laptopCat = Category::firstOrCreate(
            ['slug' => 'laptop-ias'],
            [
                'parent_id' => $hardware->id,
                'team_id'   => $project->id,
                'name'      => 'Laptop',
                'color'     => '#0369a1',
            ]
        );

        // ── Project PRISM contoh untuk menaungi aset demo ───────────────────────
        $pjctProject = PjctMain::firstOrCreate(
            ['pjct_name' => 'Seat Management IAS (Demo)'],
            [
                'pjct_type'   => 'RENT',
                'pjct_status' => 'OG',
                'pjct_div'    => 'TC',
            ]
        );

        // ── Aset PRISM (3 Laptop) + tiket kendala ───────────────────────────────
        $subjects = [
            'Laptop IAS-001 tidak bisa booting setelah update Windows',
            'Laptop IAS-002 layar flickering dan keyboard tidak responsif',
            'Laptop IAS-003 baterai tidak charging, perlu pengecekan hardware',
        ];

        foreach (range(1, 3) as $i) {
            $asset = AstMain::firstOrCreate(
                ['ast_serial' => "SN-IAS-LP-00{$i}"],
                [
                    'ast_type'    => 'Laptop',
                    'ast_brand'   => 'Dell',
                    'ast_brandmodel' => "Latitude IAS-00{$i}",
                    'ast_cond'    => 'Good',
                    'ast_stat'    => 'Aktif',
                    'ast_userloc' => 'Kantor IAS - Lantai ' . ($i + 1),
                    'ast_pjctid'  => $pjctProject->id,
                ]
            );

            // Buat tiket hanya jika aset ini belum punya tiket
            if (Ticket::where('ast_id', $asset->id)->exists()) {
                continue;
            }

            $ticket = Ticket::create([
                'requester_id'       => $client->id,
                'created_by'         => $client->id,
                'assigned_to'        => $teknisi->id,
                'team_id'            => $project->id,
                'ast_id'             => $asset->id,
                'category_id'        => $hardware->id,
                'subcategory_id'     => $laptopCat->id,
                'sla_policy_id'      => $sla->id,
                'subject'            => $subjects[$i - 1],
                'description'        => 'Perangkat mengalami masalah hardware dan memerlukan pengecekan segera oleh teknisi. Mohon bantuan secepatnya karena mengganggu pekerjaan.',
                'status'             => 'resolved',
                'priority'           => 'high',
                'first_responded_at' => now()->subHours(47),
                'last_reply_at'      => now()->subHours(4),
                'resolved_at'        => now()->subHours(3),
            ]);

            TicketMessage::create([
                'ticket_id'   => $ticket->id,
                'user_id'     => $teknisi->id,
                'body'        => 'Halo Budi, laporan sudah diterima. Saya akan segera ke lokasi untuk pengecekan perangkat. Mohon standby ya.',
                'is_internal' => false,
            ]);

            TicketMessage::create([
                'ticket_id'   => $ticket->id,
                'user_id'     => $client->id,
                'body'        => 'Baik Kak Aldo, saya tunggu di meja. Terima kasih sudah cepat ditangani!',
                'is_internal' => false,
            ]);

            TicketMessage::create([
                'ticket_id'   => $ticket->id,
                'user_id'     => $teknisi->id,
                'body'        => 'Pengecekan selesai. Masalah sudah berhasil diperbaiki dan perangkat kembali normal. Tiket ini saya resolved ya, kalau ada kendala lagi silakan buat tiket baru.',
                'is_internal' => false,
            ]);

            DB::table('activity_logs')->insert([
                [
                    'ticket_id'   => $ticket->id,
                    'user_id'     => $client->id,
                    'action'      => 'ticket_created',
                    'description' => 'Ticket dibuat',
                    'properties'  => json_encode(['status' => 'open', 'priority' => 'high', 'assigned_to' => $teknisi->id]),
                    'created_at'  => now()->subHours(48),
                    'updated_at'  => now()->subHours(48),
                ],
                [
                    'ticket_id'   => $ticket->id,
                    'user_id'     => $teknisi->id,
                    'action'      => 'ticket_status_changed',
                    'description' => 'Status ticket berubah dari In Progress ke Resolved',
                    'properties'  => json_encode(['from' => 'in_progress', 'to' => 'resolved']),
                    'created_at'  => now()->subHours(3),
                    'updated_at'  => now()->subHours(3),
                ],
            ]);
        }

        // ── Output credentials ────────────────────────────────────────────────
        $this->command->newLine();
        $this->command->info('E2E Demo Seeder selesai.');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Admin',   $admin?->user_email ?? '(pakai akun admin yang sudah ada)', '-'],
                ['Teknisi', 'aldo@ias.test', 'password'],
                ['Client',  'budi@ias.test', 'password'],
            ]
        );
    }
}
