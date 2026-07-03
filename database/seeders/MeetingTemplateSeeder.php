<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MeetingTemplate;
use Illuminate\Database\Seeder;

class MeetingTemplateSeeder extends Seeder
{
    public function run(): void
    {
        Organization::query()->each(function (Organization $org): void {
            $owner = $org->members()->first();

            if (! $owner) {
                return;
            }

            foreach ($this->templates() as $template) {
                MeetingTemplate::query()->firstOrCreate(
                    [
                        'organization_id' => $org->id,
                        'name' => $template['name'],
                    ],
                    [
                        'created_by' => $owner->id,
                        'description' => $template['description'],
                        'structure' => ['sections' => $template['sections']],
                        'default_settings' => null,
                        'is_shared' => true,
                        'is_default' => false,
                    ]
                );
            }
        });
    }

    /** @return array<int, array<string, mixed>> */
    private function templates(): array
    {
        return [

            // ─── SEKTOR KERAJAAN ───────────────────────────────────────────

            [
                'name' => 'Mesyuarat Jawatankuasa',
                'description' => 'Template mesyuarat formal jawatankuasa sektor kerajaan. Sesuai untuk mesyuarat bulanan, suku tahunan, atau khas.',
                'sections' => [
                    ['title' => 'Perasmian & Pembukaan', 'type' => 'text'],
                    ['title' => 'Pengesahan Minit Mesyuarat Lepas', 'type' => 'decisions'],
                    ['title' => 'Perkara Berbangkit', 'type' => 'list'],
                    ['title' => 'Agenda Utama', 'type' => 'agenda'],
                    ['title' => 'Perbincangan', 'type' => 'text'],
                    ['title' => 'Keputusan Mesyuarat', 'type' => 'decisions'],
                    ['title' => 'Arahan Tindakan', 'type' => 'action_items'],
                    ['title' => 'Lain-lain Hal (AOB)', 'type' => 'text'],
                    ['title' => 'Penutup', 'type' => 'text'],
                ],
            ],

            [
                'name' => 'Mesyuarat Post Mortem',
                'description' => 'Kajian semula selepas tamat sesuatu program, projek, atau acara. Sesuai untuk semua agensi kerajaan.',
                'sections' => [
                    ['title' => 'Ringkasan Program / Projek', 'type' => 'text'],
                    ['title' => 'Pencapaian & Kekuatan', 'type' => 'list'],
                    ['title' => 'Isu & Cabaran', 'type' => 'list'],
                    ['title' => 'Analisis Punca Masalah', 'type' => 'text'],
                    ['title' => 'Cadangan Penambahbaikan', 'type' => 'list'],
                    ['title' => 'Arahan Tindakan', 'type' => 'action_items'],
                    ['title' => 'Rumusan', 'type' => 'text'],
                ],
            ],

            [
                'name' => 'Sesi Taklimat / Briefing',
                'description' => 'Sesi penyampaian maklumat rasmi kepada pegawai atau kakitangan. Sesuai untuk taklimat dasar, program baharu, atau arahan pengurusan.',
                'sections' => [
                    ['title' => 'Tujuan Taklimat', 'type' => 'text'],
                    ['title' => 'Latar Belakang', 'type' => 'text'],
                    ['title' => 'Kandungan Utama', 'type' => 'agenda'],
                    ['title' => 'Perkara Penting / Penekanan', 'type' => 'list'],
                    ['title' => 'Soal Jawab', 'type' => 'qa'],
                    ['title' => 'Tindakan Susulan', 'type' => 'action_items'],
                    ['title' => 'Rumusan & Penutup', 'type' => 'text'],
                ],
            ],

            // ─── GLC (GOVERNMENT-LINKED COMPANIES) ────────────────────────

            [
                'name' => 'Mesyuarat Lembaga Pengarah',
                'description' => 'Template rasmi untuk mesyuarat Lembaga Pengarah GLC. Memenuhi keperluan tadbir urus korporat dan pematuhan.',
                'sections' => [
                    ['title' => 'Pengesahan Kuorum', 'type' => 'text'],
                    ['title' => 'Pengesahan Minit Mesyuarat Lepas', 'type' => 'decisions'],
                    ['title' => 'Perkara Berbangkit', 'type' => 'list'],
                    ['title' => 'Prestasi Kewangan', 'type' => 'text'],
                    ['title' => 'Laporan Pengurusan', 'type' => 'text'],
                    ['title' => 'Perkara Strategik & Pelaburan', 'type' => 'text'],
                    ['title' => 'Risiko & Pematuhan', 'type' => 'list'],
                    ['title' => 'Keputusan Lembaga', 'type' => 'decisions'],
                    ['title' => 'Arahan Tindakan', 'type' => 'action_items'],
                    ['title' => 'Lain-lain Hal', 'type' => 'text'],
                ],
            ],

            [
                'name' => 'Mesyuarat Pengurusan Bulanan',
                'description' => 'Semakan prestasi bulanan peringkat pengurusan GLC. Merangkumi KPI, operasi, isu, dan keputusan.',
                'sections' => [
                    ['title' => 'Semakan KPI & Scorecard', 'type' => 'text'],
                    ['title' => 'Kemaskini Operasi', 'type' => 'text'],
                    ['title' => 'Kemaskini Kewangan', 'type' => 'text'],
                    ['title' => 'Isu & Eskalasi', 'type' => 'list'],
                    ['title' => 'Keputusan', 'type' => 'decisions'],
                    ['title' => 'Arahan Tindakan', 'type' => 'action_items'],
                    ['title' => 'Agenda Mesyuarat Berikutnya', 'type' => 'agenda'],
                ],
            ],

            [
                'name' => 'Kajian Semula Projek',
                'description' => 'Semakan status projek pelaburan atau transformasi GLC. Sesuai untuk projek infrastruktur, digital, atau pembangunan.',
                'sections' => [
                    ['title' => 'Gambaran Keseluruhan Projek', 'type' => 'text'],
                    ['title' => 'Kemajuan & Milestones', 'type' => 'text'],
                    ['title' => 'Status Belanjawan', 'type' => 'text'],
                    ['title' => 'Risiko & Isu', 'type' => 'list'],
                    ['title' => 'Keputusan & Kelulusan', 'type' => 'decisions'],
                    ['title' => 'Langkah Seterusnya', 'type' => 'list'],
                    ['title' => 'Arahan Tindakan', 'type' => 'action_items'],
                ],
            ],

            // ─── SME (PERUSAHAAN KECIL & SEDERHANA) ───────────────────────

            [
                'name' => 'Mesyuarat Pasukan Mingguan',
                'description' => 'Mesyuarat ringkas mingguan untuk pasukan SME. Fokus kepada pencapaian, keutamaan minggu ini, dan halangan.',
                'sections' => [
                    ['title' => 'Pencapaian Minggu Lepas', 'type' => 'list'],
                    ['title' => 'Keutamaan Minggu Ini', 'type' => 'list'],
                    ['title' => 'Halangan / Isu Semasa', 'type' => 'list'],
                    ['title' => 'Kemaskini Jualan & Pelanggan', 'type' => 'text'],
                    ['title' => 'Arahan Tindakan', 'type' => 'action_items'],
                ],
            ],

            [
                'name' => 'Mesyuarat Pelanggan / Klien',
                'description' => 'Template untuk mesyuarat dengan pelanggan, klien, atau rakan kongsi perniagaan. Sesuai untuk pembentangan, rundingan, atau susulan.',
                'sections' => [
                    ['title' => 'Perkenalan & Latar Belakang', 'type' => 'text'],
                    ['title' => 'Objektif Mesyuarat', 'type' => 'list'],
                    ['title' => 'Perbincangan & Keperluan Pelanggan', 'type' => 'text'],
                    ['title' => 'Cadangan & Penyelesaian', 'type' => 'text'],
                    ['title' => 'Soal Jawab', 'type' => 'qa'],
                    ['title' => 'Persetujuan & Keputusan', 'type' => 'decisions'],
                    ['title' => 'Langkah Seterusnya', 'type' => 'action_items'],
                ],
            ],

            [
                'name' => 'Perancangan Perniagaan Tahunan',
                'description' => 'Sesi perancangan strategik tahunan untuk SME. Merangkumi ulasan prestasi, sasaran, strategi, dan bajet tahun hadapan.',
                'sections' => [
                    ['title' => 'Ulasan Prestasi Tahun Semasa', 'type' => 'text'],
                    ['title' => 'Analisis Pasaran & Persaingan', 'type' => 'text'],
                    ['title' => 'Sasaran & KPI Tahun Hadapan', 'type' => 'list'],
                    ['title' => 'Strategi Pertumbuhan', 'type' => 'text'],
                    ['title' => 'Pelan Pemasaran & Jualan', 'type' => 'text'],
                    ['title' => 'Peruntukan Bajet & Sumber', 'type' => 'text'],
                    ['title' => 'Risiko & Pelan Kontingensi', 'type' => 'list'],
                    ['title' => 'Pelan Tindakan', 'type' => 'action_items'],
                    ['title' => 'Tarikh Semakan & Penutup', 'type' => 'text'],
                ],
            ],
        ];
    }
}
