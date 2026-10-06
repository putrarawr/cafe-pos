<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dua migrasi yang pernah ada di mesin ini -
 * 2026_10_06_000003_converge_meja_legacy_to_global dan
 * 2026_10_06_000004_pasang_fk_dan_index_meja_id - sempat jalan ke database dan
 * mengubah tabel `meja` jadi desain global: kolom `gudang_id`, `area`,
 * `durasi_menit`, dan `status_aktif` dibuang, digantikan `status` + `sort_order`,
 * lalu `kode_meja` dibikin unik global. Berkas kedua migrasi itu sudah dihapus,
 * tapi efeknya tertinggal di database.
 *
 * Migrasi repo (create_meja_table + make_kode_meja_unik_per_gudang) sudah
 * tercatat sebagai "sudah jalan" di tabel `migrations`, jadi `artisan migrate`
 * tidak akan pernah menjalankannya lagi. Akibatnya kode, seeder, dan halaman admin
 * yang tetap memakai desain per-gudang tidak cocok dengan skema yang ada, dan
 * halaman /admin/mejas gagal dengan "column gudang_id does not exist".
 *
 * Migrasi ini mengembalikan `meja` ke desain per-gudang sesuai spec. Baris yang
 * sekarang ada (dari iterasi global) tetap dipertahankan supaya order pending
 * yang masih menunjuk ke sebuah meja tidak kehilangan referensinya.
 *
 * Idempoten: di database yang dibangun dari nol oleh create_meja_table, semua
 * kolom dan index per-gudang sudah ada dari sananya, jadi migrasi ini tidak
 * melakukan apa-apa.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->kembalikanKolom();
        $this->backfillBarisLama();
        $this->kembalikanIndex();
        $this->buangKolomSisa();
        $this->sinkronSequence();
    }

    public function down(): void
    {
        Schema::table('meja', function (Blueprint $table) {
            if (Schema::hasIndex('meja', 'meja_gudang_kode_unique')) {
                $table->dropUnique('meja_gudang_kode_unique');
            }
            if (Schema::hasIndex('meja', 'meja_gudang_status_index')) {
                $table->dropIndex('meja_gudang_status_index');
            }
        });

        Schema::table('meja', function (Blueprint $table) {
            $table->dropForeign(['gudang_id']);
            $table->dropColumn(['gudang_id', 'area', 'durasi_menit', 'status_aktif']);

            $table->string('status', 20)->default('tersedia')->after('updated_at');
            $table->integer('sort_order')->default(0)->after('status');
            $table->unique('kode_meja');
        });

        DB::table('meja')->update(['status' => 'tersedia']);
    }

    private function kembalikanKolom(): void
    {
        // Nullable dulu supaya baris lama bisa di-backfill sebelum constraint ditegakkan.
        if (! Schema::hasColumn('meja', 'gudang_id')) {
            Schema::table('meja', fn (Blueprint $t) => $t->foreignId('gudang_id')->nullable()->after('id'));
        }
        if (! Schema::hasColumn('meja', 'area')) {
            Schema::table('meja', fn (Blueprint $t) => $t->string('area', 100)->nullable()->after('nama_meja'));
        }
        if (! Schema::hasColumn('meja', 'durasi_menit')) {
            Schema::table('meja', fn (Blueprint $t) => $t->unsignedSmallInteger('durasi_menit')->default(60)->after('kapasitas'));
        }
        if (! Schema::hasColumn('meja', 'status_aktif')) {
            Schema::table('meja', fn (Blueprint $t) => $t->boolean('status_aktif')->default(true)->after('durasi_menit'));
        }
    }

    /**
     * Baris hasil iterasi global tidak punya cabang, jadi dikaitkan ke gudang
     * pertama yang tersedia. Baris ini juga sudah rename kodenya supaya
     * MejaSeeder (firstOrCreate per gudang) memakainya, bukan membuat duplikat.
     */
    private function backfillBarisLama(): void
    {
        $lama = DB::table('meja')->whereNull('gudang_id')->orderBy('id')->get();
        if ($lama->isEmpty()) {
            return;
        }

        $gudangAwal = DB::table('gudang')->orderBy('id')->value('id');

        foreach ($lama->values() as $nomor => $meja) {
            $kode = $this->kode($nomor + 1);
            $bentrok = DB::table('meja')
                ->where('gudang_id', $gudangAwal)
                ->where('kode_meja', $kode)
                ->exists();

            DB::table('meja')->where('id', $meja->id)->update([
                'gudang_id' => $gudangAwal,
                'kode_meja' => $bentrok ? $meja->kode_meja : $kode,
                'area' => $meja->area ?: $meja->nama_meja,
                'durasi_menit' => $this->durasiDefault((string) $meja->nama_meja, (int) $meja->kapasitas),
                'status_aktif' => true,
            ]);
        }
    }

    private function kembalikanIndex(): void
    {
        if (Schema::hasIndex('meja', 'meja_kode_meja_unique')) {
            Schema::table('meja', fn (Blueprint $t) => $t->dropUnique('meja_kode_meja_unique'));
        }

        if (! $this->punyaForeign('meja', 'gudang_id')) {
            Schema::table('meja', fn (Blueprint $t) => $t->foreign('gudang_id')->references('id')->on('gudang')->cascadeOnDelete());
        }

        if (! Schema::hasIndex('meja', 'meja_gudang_kode_unique')) {
            Schema::table('meja', fn (Blueprint $t) => $t->unique(['gudang_id', 'kode_meja'], 'meja_gudang_kode_unique'));
        }

        if (! Schema::hasIndex('meja', 'meja_gudang_status_index')) {
            Schema::table('meja', fn (Blueprint $t) => $t->index(['gudang_id', 'status_aktif'], 'meja_gudang_status_index'));
        }

        // Iterasi global sempat membongkar ulang tabel `meja`, dan cascade FK dari
        // sesi_meja ikut hilang bersama tabel tuanya. Dipasang lagi supaya hapus
        // meja ikut membersihkan sesinya.
        if (! $this->punyaForeign('sesi_meja', 'meja_id')) {
            Schema::table('sesi_meja', fn (Blueprint $t) => $t->foreign('meja_id')->references('id')->on('meja')->cascadeOnDelete());
        }
    }

    /** Kolom sisa iterasi global yang tidak dibaca kode mana pun. */
    private function buangKolomSisa(): void
    {
        $buang = array_values(array_filter(
            ['status', 'sort_order'],
            fn ($kolom) => Schema::hasColumn('meja', $kolom)
        ));

        if ($buang !== []) {
            Schema::table('meja', fn (Blueprint $t) => $t->dropColumn($buang));
        }
    }

    /**
     * Sequence sempat berada di 60 setelah MejaSeeder lama dihapus, jadi
     * disinkronkan lagi dengan id terbesar yang benar-benar ada.
     */
    private function sinkronSequence(): void
    {
        $terbesar = (int) DB::table('meja')->max('id');
        if ($terbesar > 0) {
            DB::statement("SELECT setval(pg_get_serial_sequence('meja', 'id'), {$terbesar})");
        }
    }

    private function punyaForeign(string $tabel, string $kolom): bool
    {
        $ada = DB::selectOne(
            'SELECT c.conname
               FROM pg_constraint c
               JOIN unnest(c.conkey) AS k(attnum) ON true
               JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = k.attnum
              WHERE c.conrelid = to_regclass(?)
                AND c.contype = \'f\'
                AND a.attname = ?',
            [$tabel, $kolom]
        );

        return $ada !== null;
    }

    private function kode(int $nomor): string
    {
        return 'Meja '.str_pad((string) $nomor, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Sama dengan pola MejaSeeder, tapi area diturunkan dari nama_meja yang
     * sekarang mengisi kolom `area` (Indoor / Teras / VIP).
     */
    private function durasiDefault(string $area, int $kapasitas): int
    {
        return match (strtolower($area)) {
            'teras' => 45,
            'vip' => $kapasitas >= 8 ? 120 : 90,
            default => 60,
        };
    }
};