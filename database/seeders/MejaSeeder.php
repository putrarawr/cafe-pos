<?php

namespace Database\Seeders;

use App\Models\Gudang;
use App\Models\Meja;
use Illuminate\Database\Seeder;

class MejaSeeder extends Seeder
{
    /**
     * Master meja untuk setiap gudang. Durasi default: indoor 60 menit, teras 45 menit,
     * area VIP lebih lama karena customers cenderung duduk lebih lama.
     *
     * @var array<string, array{area:?string, kapasitas:int, durasi:int}>
     */
    private array $pola = [
        ['area' => 'Indoor', 'kapasitas' => 4, 'durasi' => 60],
        ['area' => 'Indoor', 'kapasitas' => 4, 'durasi' => 60],
        ['area' => 'Indoor', 'kapasitas' => 4, 'durasi' => 60],
        ['area' => 'Indoor', 'kapasitas' => 6, 'durasi' => 60],
        ['area' => 'Indoor', 'kapasitas' => 6, 'durasi' => 60],
        ['area' => 'Indoor', 'kapasitas' => 4, 'durasi' => 60],
        ['area' => 'Teras', 'kapasitas' => 4, 'durasi' => 45],
        ['area' => 'Teras', 'kapasitas' => 4, 'durasi' => 45],
        ['area' => 'Teras', 'kapasitas' => 6, 'durasi' => 45],
        ['area' => 'VIP', 'kapasitas' => 6, 'durasi' => 90],
        ['area' => 'VIP', 'kapasitas' => 6, 'durasi' => 90],
        ['area' => 'VIP', 'kapasitas' => 8, 'durasi' => 120],
    ];

    public function run(): void
    {
        foreach (Gudang::orderBy('id')->get() as $gudang) {
            foreach ($this->pola as $index => $meja) {
                // kode_meja unik per cabang, jadi setiap gudang bisa punya set
                // kode yang sama tanpa bentrok.
                $kode = 'Meja '.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);

                Meja::firstOrCreate(
                    ['gudang_id' => $gudang->id, 'kode_meja' => $kode],
                    [
                        'nama_meja' => $meja['area'],
                        'area' => $meja['area'],
                        'kapasitas' => $meja['kapasitas'],
                        'durasi_menit' => $meja['durasi'],
                        'status_aktif' => true,
                    ]
                );
            }
        }
    }
}
