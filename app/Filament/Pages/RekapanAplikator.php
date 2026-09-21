<?php

namespace App\Filament\Pages;

use App\Models\Aplikator;
use App\Models\Penjualan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class RekapanAplikator extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string | array $routePath = 'rekapan-aplikator';

    protected static ?string $title = 'Rekapan Aplikator';

    protected static ?string $navigationLabel = 'Rekapan Aplikator';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 6;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected string $view = 'filament.pages.rekapan-aplikator';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'dari_tanggal' => now()->startOfMonth()->format('Y-m-d'),
            'sampai_tanggal' => now()->format('Y-m-d'),
            'aplikator_id' => null,
        ]);
    }

    public function updatedData(): void
    {
        $this->resetTable();
    }

    public function pilihAplikator(?int $aplikatorId = null): void
    {
        $this->data['aplikator_id'] = $aplikatorId;
        $this->resetTable();
    }

    public function hapusFilterAplikator(): void
    {
        $this->data['aplikator_id'] = null;
        $this->resetTable();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make()
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])->schema([
                            DatePicker::make('dari_tanggal')
                                ->label('Dari Tanggal')
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn () => $this->resetTable()),

                            DatePicker::make('sampai_tanggal')
                                ->label('Sampai Tanggal')
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn () => $this->resetTable()),

                            Select::make('aplikator_id')
                                ->label('Filter Aplikator')
                                ->placeholder('Semua Aplikator')
                                ->options(fn () => Aplikator::orderBy('nama_aplikator')->pluck('nama_aplikator', 'id')->all())
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(fn () => $this->resetTable()),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function getOverviewStatsProperty(): array
    {
        $dariTanggal = $this->data['dari_tanggal'] ?? now()->startOfMonth()->format('Y-m-d');
        $sampaiTanggal = $this->data['sampai_tanggal'] ?? now()->format('Y-m-d');
        $aplikatorId = $this->data['aplikator_id'] ?? null;

        $query = Penjualan::query()
            ->whereNotNull('aplikator_id')
            ->whereDate('tanggal', '>=', $dariTanggal)
            ->whereDate('tanggal', '<=', $sampaiTanggal);

        if (!empty($aplikatorId)) {
            $query->where('aplikator_id', (int) $aplikatorId);
        }

        // Total keseluruhan
        $stat = (clone $query)->reorder()->selectRaw('
            COUNT(*) as count_transaksi,
            COALESCE(SUM(neto), 0) as sum_omset,
            COALESCE(SUM(komisi_aplikator), 0) as sum_komisi,
            COALESCE(SUM(biaya_kirim), 0) as sum_kirim
        ')->first();

        $omset = (float) ($stat->sum_omset ?? 0);
        $komisi = (float) ($stat->sum_komisi ?? 0);
        $kirim = (float) ($stat->sum_kirim ?? 0);
        $countTransaksi = (int) ($stat->count_transaksi ?? 0);
        $bersih = $omset - $kirim - $komisi;

        $totalQty = (int) (clone $query)->reorder()
            ->join('detail_jual', 'penjualan.id', '=', 'detail_jual.penjualan_id')
            ->sum('detail_jual.jumlah');

        $avgOrder = $countTransaksi > 0 ? (int) round($omset / $countTransaksi) : 0;
        $komisiPersen = $omset > 0 ? round(($komisi / $omset) * 100, 1) : 0;
        $marginBersihPersen = $omset > 0 ? round(($bersih / $omset) * 100, 1) : 0;

        // Rincian per aplikator
        $perAplikator = (clone $query)
            ->reorder()
            ->selectRaw('aplikator_id, COUNT(*) as count_transaksi, COALESCE(SUM(neto), 0) as sum_omset, COALESCE(SUM(komisi_aplikator), 0) as sum_komisi, COALESCE(SUM(biaya_kirim), 0) as sum_kirim')
            ->groupBy('aplikator_id')
            ->get()
            ->keyBy('aplikator_id');

        $aplikatorsQuery = Aplikator::query()->orderBy('nama_aplikator');
        if (!empty($aplikatorId)) {
            $aplikatorsQuery->where('id', (int) $aplikatorId);
        }

        $aplikators = $aplikatorsQuery->get()->map(fn (Aplikator $a) => [
            'id' => $a->id,
            'nama_aplikator' => $a->nama_aplikator,
            'kode_aplikator' => $a->kode_aplikator,
            'count_transaksi' => (int) ($perAplikator[$a->id]->count_transaksi ?? 0),
            'sum_omset' => (float) ($perAplikator[$a->id]->sum_omset ?? 0),
            'sum_komisi' => (float) ($perAplikator[$a->id]->sum_komisi ?? 0),
            'sum_kirim' => (float) ($perAplikator[$a->id]->sum_kirim ?? 0),
        ])->all();

        $activeAplikator = !empty($aplikatorId) ? Aplikator::find($aplikatorId) : null;

        return [
            'aplikators' => $aplikators,
            'count_transaksi' => $countTransaksi,
            'sum_omset' => $omset,
            'sum_komisi' => $komisi,
            'sum_bersih' => $bersih,
            'sum_kirim' => $kirim,
            'total_qty' => $totalQty,
            'avg_order' => $avgOrder,
            'komisi_persen' => $komisiPersen,
            'margin_bersih_persen' => $marginBersihPersen,
            'is_filtered' => !empty($aplikatorId),
            'filtered_aplikator_nama' => $activeAplikator?->nama_aplikator,
        ];
    }

    public function table(Table $table): Table
    {
        $dariTanggal = $this->data['dari_tanggal'] ?? now()->startOfMonth()->format('Y-m-d');
        $sampaiTanggal = $this->data['sampai_tanggal'] ?? now()->format('Y-m-d');
        $aplikatorId = $this->data['aplikator_id'] ?? null;

        $penjualanSub = fn (string $select) => "COALESCE((SELECT {$select} FROM penjualan p WHERE p.aplikator_id = aplikator.id AND p.tanggal::date >= ? AND p.tanggal::date <= ?), 0)";

        return $table
            ->query(function () use ($dariTanggal, $sampaiTanggal, $aplikatorId, $penjualanSub): Builder {
                $query = Aplikator::query()
                    ->select('aplikator.*')
                    ->selectRaw($penjualanSub('COUNT(*)') . ' as total_transaksi', [$dariTanggal, $sampaiTanggal])
                    ->selectRaw($penjualanSub('SUM(neto)') . ' as total_omset', [$dariTanggal, $sampaiTanggal])
                    ->selectRaw($penjualanSub('SUM(komisi_aplikator)') . ' as total_komisi', [$dariTanggal, $sampaiTanggal])
                    ->selectRaw($penjualanSub('SUM(biaya_kirim)') . ' as total_kirim', [$dariTanggal, $sampaiTanggal])
                    ->selectRaw(
                        $penjualanSub('SUM(neto)') . ' - ' . $penjualanSub('SUM(biaya_kirim)') . ' - ' . $penjualanSub('SUM(komisi_aplikator)') . ' as total_bersih',
                        [$dariTanggal, $sampaiTanggal, $dariTanggal, $sampaiTanggal, $dariTanggal, $sampaiTanggal]
                    )
                    ->selectRaw(
                        'COALESCE((SELECT SUM(dj.jumlah) FROM detail_jual dj JOIN penjualan p ON p.id = dj.penjualan_id WHERE p.aplikator_id = aplikator.id AND p.tanggal::date >= ? AND p.tanggal::date <= ?), 0) as total_qty_item',
                        [$dariTanggal, $sampaiTanggal]
                    );

                if (!empty($aplikatorId)) {
                    $query->where('aplikator.id', (int) $aplikatorId);
                }

                return $query;
            })
            ->columns([
                TextColumn::make('nama_aplikator')
                    ->label('Aplikator')
                    ->weight('bold')
                    ->description(fn (Aplikator $record) => $record->kode_aplikator ?: null),

                TextColumn::make('total_transaksi')
                    ->label('Transaksi')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 0, ',', '.') . ' Transaksi')
                    ->alignCenter()
                    ->summarize(Sum::make()->label('')->formatStateUsing(fn ($state) => number_format((float) $state, 0, ',', '.') . ' Transaksi')),

                TextColumn::make('total_qty_item')
                    ->label('Qty Item')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 0, ',', '.'))
                    ->alignCenter()
                    ->summarize(Sum::make()->label('')->formatStateUsing(fn ($state) => number_format((float) $state, 0, ',', '.'))),

                TextColumn::make('total_omset')
                    ->label('Omset')
                    ->money('IDR', locale: 'id')
                    ->alignRight()
                    ->summarize(Sum::make()->label('')->money('IDR', locale: 'id')),

                TextColumn::make('total_komisi')
                    ->label('Komisi Aplikator')
                    ->money('IDR', locale: 'id')
                    ->alignRight()
                    ->color('warning')
                    ->summarize(Sum::make()->label('')->money('IDR', locale: 'id')),

                TextColumn::make('total_bersih')
                    ->label('Estimasi Bersih')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format((float) $state, 0, ',', '.'))
                    ->alignRight()
                    ->weight('bold')
                    ->color(fn ($state): string => (float) $state >= 0 ? 'success' : 'danger')
                    ->summarize(Sum::make()->label('')->money('IDR', locale: 'id')),

                TextColumn::make('total_kirim')
                    ->label('Biaya Kirim')
                    ->money('IDR', locale: 'id')
                    ->alignRight()
                    ->summarize(Sum::make()->label('')->money('IDR', locale: 'id')),
            ])
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('total_transaksi')->orderBy('nama_aplikator'))
            ->recordAction('detail')
            ->recordActions([
                Action::make('detail')
                    ->extraAttributes(['class' => 'hidden', 'style' => 'display:none'])
                    ->modalHeading(fn (Aplikator $record) => "Rincian Penjualan - {$record->nama_aplikator}")
                    ->modalWidth('7xl')
                    ->modalContent(function (Aplikator $record) use ($dariTanggal, $sampaiTanggal): View {
                        $invoices = Penjualan::query()
                            ->where('aplikator_id', $record->id)
                            ->whereDate('tanggal', '>=', $dariTanggal)
                            ->whereDate('tanggal', '<=', $sampaiTanggal)
                            ->with(['details.barang', 'karyawan', 'user', 'aplikator'])
                            ->orderBy('created_at', 'desc')
                            ->get();

                        return view('filament.pages.modal-detail-rekapan-aplikator', [
                            'aplikator' => $record,
                            'dariTanggal' => $dariTanggal,
                            'sampaiTanggal' => $sampaiTanggal,
                            'invoices' => $invoices,
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
            ])
            ->actions([])
            ->bulkActions([]);
    }
}
