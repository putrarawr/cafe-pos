<?php

namespace Tests\Concerns;

use App\Models\Gudang;
use App\Models\Meja;
use App\Models\Penjualan;
use App\Models\SesiMeja;

/**
 * Helper meja untuk test kasir.
 *
 * Meja WAJIB dipilih untuk setiap item dine in, jadi test yang mem-post payload
 * kasir perlu menyuntik meja_id-nya sendiri. Pakai denganMeja() di builder
 * payload masing-masing file supaya tidak ada payload dine in tanpa meja.
 */
trait PunyaMeja
{
    private static int $urutanMeja = 0;

    private ?Meja $mejaDefault = null;

    /**
     * Meja baru yang selalu unik dalam satu proses test, supaya beberapa order
     * pending di satu test bisa memegang meja yang berbeda.
     */
    protected function buatMejaAktif(?Gudang $gudang = null, array $atribut = []): Meja
    {
        $gudang ??= Gudang::first();

        return Meja::create(array_merge([
            'kode_meja' => 'A'.str_pad((string) ++self::$urutanMeja, 4, '0', STR_PAD_LEFT),
            'nama_meja' => 'Meja Test',
            'gudang_id' => $gudang->id,
            'area' => 'Indoor',
            'kapasitas' => 4,
            'durasi_menit' => 60,
            'status_aktif' => true,
        ], $atribut));
    }

    /**
     * Satu meja yang sama dipakai ulang, untuk kasus yang butuh id yang sama
     * di beberapa payload dalam satu test.
     */
    protected function mejaDefault(?Gudang $gudang = null): Meja
    {
        return $this->mejaDefault ??= $this->buatMejaAktif($gudang);
    }

    /**
     * Suntik meja_id ke payload kasir. Kalau payload tidak punya item dine_in
     * (take away / delivery), meja dibiarkan null supaya menguji aturan
     * "meja tidak relevan untuk non-dine-in".
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function denganMeja(array $payload, ?Meja $meja = null): array
    {
        $hasDineIn = collect($payload['details'] ?? [])->contains(
            fn ($d) => ($d['jenis_pesanan'] ?? 'dine_in') === 'dine_in'
        );

        if (! $hasDineIn) {
            return $payload;
        }

        $meja ??= $this->mejaDefault($this->gudangDari($payload));

        return array_merge($payload, ['meja_id' => $meja->id]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function gudangDari(array $payload): ?Gudang
    {
        $id = $payload['gudang_id'] ?? null;

        return $id ? Gudang::find($id) : null;
    }

    /**
     * Sesi meja terisi yang sedang mengunci satu meja.
     */
    protected function sesiMejaTerisi(Meja $meja, ?int $orderPendingId = null): SesiMeja
    {
        return SesiMeja::create([
            'meja_id' => $meja->id,
            'gudang_id' => $meja->gudang_id,
            'status' => SesiMeja::STATUS_TERISI,
            'mulai' => now(),
            'batas_menit' => $meja->durasi_menit,
            'order_pending_id' => $orderPendingId,
        ]);
    }

    /**
     * Seberapa banyak meja yang sedang terkunci di gudang tertentu.
     */
    protected function jumlahMejaTerkunci(?int $gudangId = null): int
    {
        return SesiMeja::where('status', SesiMeja::STATUS_TERISI)
            ->when($gudangId !== null, fn ($q) => $q->where('gudang_id', $gudangId))
            ->count();
    }

    protected function mejaDariPenjualan(Penjualan $penjualan): ?Meja
    {
        return $penjualan->fresh()?->meja;
    }
}
