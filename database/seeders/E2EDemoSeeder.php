<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Device;
use App\Models\SlaPolicy;
use App\Models\Team;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class E2EDemoSeeder extends Seeder
{
    public function run(): void
    {
        // ── Company ───────────────────────────────────────────────────────────
        // Gunakan company yang sudah ada agar admin bisa lihat semua data
        $company = Company::first() ?? Company::create([
            'name'            => 'IAS',
            'code'            => 'IAS-ID',
            'contact_email'   => 'hello@ias.test',
            'auto_close_days' => 3,
        ]);

        // ── Users ─────────────────────────────────────────────────────────────
        // Admin: pakai yang sudah ada di company ini
        $admin = User::where('company_id', $company->id)->where('role', 'admin')->first();

        $teknisi = User::firstOrCreate(
            ['email' => 'aldo@ias.test'],
            [
                'company_id' => $company->id,
                'name'      => 'Aldo Teknisi',
                'role'      => 'agent',
                'job_title' => 'IT Support Technician',
                'password'  => 'password',
            ]
        );

        $client = User::firstOrCreate(
            ['email' => 'budi@ias.test'],
            [
                'company_id' => $company->id,
                'name'      => 'Budi Client',
                'role'      => 'client',
                'job_title' => 'Staff Operasional',
                'password'  => 'password',
            ]
        );

        // ── SLA Policy ────────────────────────────────────────────────────────
        $sla = SlaPolicy::where('company_id', $company->id)->where('is_default', true)->first()
            ?? SlaPolicy::create([
                'company_id'          => $company->id,
                'name'               => 'Standard',
                'response_minutes'   => 60,
                'resolution_minutes' => 480,
                'is_default'         => true,
            ]);

        // ── Project ───────────────────────────────────────────────────────────
        $project = Team::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'SEAT-IAS'],
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
            ['company_id' => $company->id, 'slug' => 'hardware-ias'],
            [
                'team_id' => $project->id,
                'name'    => 'Hardware',
                'color'   => '#0284c7',
            ]
        );

        $laptopCat = Category::firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'laptop-ias'],
            [
                'parent_id' => $hardware->id,
                'team_id'   => $project->id,
                'name'      => 'Laptop',
                'color'     => '#0369a1',
            ]
        );

        // ── Devices (3 Laptop) ────────────────────────────────────────────────
        $subjects = [
            'Laptop IAS-001 tidak bisa booting setelah update Windows',
            'Laptop IAS-002 layar flickering dan keyboard tidak responsif',
            'Laptop IAS-003 baterai tidak charging, perlu pengecekan hardware',
        ];

        foreach (range(1, 3) as $i) {
            $device = Device::firstOrCreate(
                ['company_id' => $company->id, 'asset_code' => "IAS-LP-00{$i}"],
                [
                    'team_id'       => $project->id,
                    'name'          => "Laptop IAS-00{$i}",
                    'device_type'   => 'Laptop',
                    'serial_number' => "SN-IAS-LP-00{$i}",
                    'location'      => 'Kantor IAS - Lantai ' . ($i + 1),
                    'is_active'     => true,
                ]
            );

            // Buat tiket hanya jika device belum punya tiket
            if (Ticket::where('device_id', $device->id)->exists()) {
                continue;
            }

            $ticket = Ticket::create([
                'company_id'          => $company->id,
                'requester_id'       => $client->id,
                'created_by'         => $client->id,
                'assigned_to'        => $teknisi->id,
                'team_id'            => $project->id,
                'device_id'          => $device->id,
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
                    'company_id'  => $company->id,
                    'ticket_id'   => $ticket->id,
                    'user_id'     => $client->id,
                    'action'      => 'ticket_created',
                    'description' => 'Ticket dibuat',
                    'properties'  => json_encode(['status' => 'open', 'priority' => 'high', 'assigned_to' => $teknisi->id]),
                    'created_at'  => now()->subHours(48),
                    'updated_at'  => now()->subHours(48),
                ],
                [
                    'company_id'  => $company->id,
                    'ticket_id'   => $ticket->id,
                    'user_id'     => $client->id,
                    'action'      => 'ticket_status_changed',
                    'description' => 'Status awal ticket: Open',
                    'properties'  => json_encode(['from' => null, 'to' => 'open']),
                    'created_at'  => now()->subHours(48),
                    'updated_at'  => now()->subHours(48),
                ],
                [
                    'company_id'  => $company->id,
                    'ticket_id'   => $ticket->id,
                    'user_id'     => $teknisi->id,
                    'action'      => 'reply_added',
                    'description' => 'Reply added',
                    'properties'  => json_encode([]),
                    'created_at'  => now()->subHours(47),
                    'updated_at'  => now()->subHours(47),
                ],
                [
                    'company_id'  => $company->id,
                    'ticket_id'   => $ticket->id,
                    'user_id'     => $teknisi->id,
                    'action'      => 'ticket_status_changed',
                    'description' => 'Status ticket berubah dari Open ke In Progress',
                    'properties'  => json_encode(['from' => 'open', 'to' => 'in_progress']),
                    'created_at'  => now()->subHours(47),
                    'updated_at'  => now()->subHours(47),
                ],
                [
                    'company_id'  => $company->id,
                    'ticket_id'   => $ticket->id,
                    'user_id'     => $client->id,
                    'action'      => 'reply_added',
                    'description' => 'Reply added',
                    'properties'  => json_encode([]),
                    'created_at'  => now()->subHours(4),
                    'updated_at'  => now()->subHours(4),
                ],
                [
                    'company_id'  => $company->id,
                    'ticket_id'   => $ticket->id,
                    'user_id'     => $teknisi->id,
                    'action'      => 'reply_added',
                    'description' => 'Reply added',
                    'properties'  => json_encode([]),
                    'created_at'  => now()->subHours(3),
                    'updated_at'  => now()->subHours(3),
                ],
                [
                    'company_id'  => $company->id,
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
        $this->command->info("E2E Demo Seeder selesai — Company: {$company->name}");
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Admin',   $admin?->email ?? '(pakai akun admin yang sudah ada)', '-'],
                ['Teknisi', 'aldo@ias.test', 'password'],
                ['Client',  'budi@ias.test', 'password'],
            ]
        );
    }
}
