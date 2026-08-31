<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\Gudang;
use App\Models\JenisBarang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_is_logged_when_barang_is_created_and_updated(): void
    {
        $user = User::factory()->create(['name' => 'Admin Test']);
        $this->actingAs($user);

        $jenis = JenisBarang::create([
            'nama_jenis' => 'Minuman',
            'deskripsi' => 'Kategori minuman segar',
        ]);

        $barang = Barang::create([
            'jenis_barang_id' => $jenis->id,
            'nama_barang' => 'Kopi Latte',
            'harga_jual' => 15000,
            'satuan' => 'btl',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Barang::class,
            'subject_id' => $barang->id,
            'event' => 'created',
        ]);

        $barang->update(['nama_barang' => 'Kopi Latte Special']);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Barang::class,
            'subject_id' => $barang->id,
            'event' => 'updated',
        ]);

        $activity = Activity::where('subject_type', Barang::class)
            ->where('event', 'updated')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('updated', $activity->event);
        $this->assertEquals($user->id, $activity->causer_id);
    }

    public function test_activity_detail_modal_view_renders_properly(): void
    {
        $user = User::factory()->create(['name' => 'Budi Supervisor']);
        $this->actingAs($user);

        $gudang = Gudang::create([
            'nama_gudang' => 'Gudang Utama Test',
            'alamat' => 'Jl. Merdeka No. 1',
        ]);

        $activity = Activity::where('subject_type', Gudang::class)->first();
        $this->assertNotNull($activity);

        $view = $this->view('filament.resources.activity.detail-modal', [
            'record' => $activity->load('causer', 'subject'),
        ]);

        $view->assertSee('Gudang Utama Test');
        $view->assertSee('Budi Supervisor');
        $view->assertSee('DIBUAT (CREATED)');
    }
}
