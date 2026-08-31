<?php

namespace Tests\Feature;

use App\Filament\Widgets\MetodePembayaranChartWidget;
use App\Filament\Widgets\PembelianChartWidget;
use App\Filament\Widgets\PenjualanChartWidget;
use App\Filament\Widgets\QuickInfoWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\TopBarangTerlarisChartWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_widgets_can_render_properly(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(QuickInfoWidget::class)
            ->assertSuccessful();

        Livewire::test(StatsOverviewWidget::class)
            ->assertSuccessful();

        Livewire::test(PenjualanChartWidget::class)
            ->assertSuccessful();

        Livewire::test(PembelianChartWidget::class)
            ->assertSuccessful();

        Livewire::test(MetodePembayaranChartWidget::class)
            ->assertSuccessful();

        Livewire::test(TopBarangTerlarisChartWidget::class)
            ->assertSuccessful();
    }
}
