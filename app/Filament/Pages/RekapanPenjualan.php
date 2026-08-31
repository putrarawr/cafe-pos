<?php

namespace App\Filament\Pages;

use App\Models\Barang;
use App\Models\DetailJual;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class RekapanPenjualan extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?string $navigationLabel = 'Rekapan Penjualan';

    protected static ?string $title = 'Rekapan Penjualan Produk';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.rekapan-penjualan';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'dari_tanggal' => now()->startOfMonth()->format('Y-m-d'),
            'sampai_tanggal' => now()->format('Y-m-d'),
            'barang_id' => null,
        ]);
    }

    public function updatedData(): void
    {
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

                            Select::make('barang_id')
                                ->label('Pilih Barang')
                                ->placeholder('Semua Barang')
                                ->searchable()
                                ->getSearchResultsUsing(function (string $search): array {
                                    return Barang::query()
                                        ->where('nama_barang', 'ilike', "%{$search}%")
                                        ->orWhere('nomer_seri', 'ilike', "%{$search}%")
                                        ->orderBy('nama_barang')
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(function ($b) {
                                            $prefix = $b->nomer_seri ? "[{$b->nomer_seri}] " : '';
                                            return [(string) $b->id => "{$prefix}{$b->nama_barang}"];
                                        })
                                        ->all();
                                })
                                ->getOptionLabelUsing(function ($value): ?string {
                                    if (!$value) {
                                        return null;
                                    }
                                    $b = Barang::find($value);
                                    if (!$b) {
                                        return null;
                                    }
                                    $prefix = $b->nomer_seri ? "[{$b->nomer_seri}] " : '';
                                    return "{$prefix}{$b->nama_barang}";
                                })
                                ->options(function () {
                                    return Barang::orderBy('nama_barang')->get()->mapWithKeys(function ($b) {
                                        $prefix = $b->nomer_seri ? "[{$b->nomer_seri}] " : '';
                                        return [(string) $b->id => "{$prefix}{$b->nama_barang}"];
                                    })->all();
                                })
                                ->live()
                                ->afterStateUpdated(fn () => $this->resetTable()),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        $dariTanggal = $this->data['dari_tanggal'] ?? now()->startOfMonth()->format('Y-m-d');
        $sampaiTanggal = $this->data['sampai_tanggal'] ?? now()->format('Y-m-d');
        $barangId = $this->data['barang_id'] ?? null;

        return $table
            ->query(function () use ($dariTanggal, $sampaiTanggal, $barangId): Builder {
                $query = Barang::query()
                    ->select('barang.*')
                    ->selectRaw('COALESCE((
                        SELECT SUM(dj.jumlah)
                        FROM detail_jual dj
                        WHERE dj.barang_id = barang.id
                          AND dj.is_bonus = false
                          AND dj.created_at::date >= ?
                          AND dj.created_at::date <= ?
                    ), 0) as total_terjual_reguler', [$dariTanggal, $sampaiTanggal])
                    ->selectRaw('COALESCE((
                        SELECT SUM(COALESCE(dj.bonus_qty, 0))
                        FROM detail_jual dj
                        WHERE dj.bonus_barang_id = barang.id
                          AND dj.is_bonus = false
                          AND dj.created_at::date >= ?
                          AND dj.created_at::date <= ?
                    ), 0) as total_keluar_bonus', [$dariTanggal, $sampaiTanggal])
                    ->selectRaw('COALESCE((
                        SELECT SUM(dj.subtotal)
                        FROM detail_jual dj
                        WHERE dj.barang_id = barang.id
                          AND dj.is_bonus = false
                          AND dj.created_at::date >= ?
                          AND dj.created_at::date <= ?
                    ), 0) as total_omset', [$dariTanggal, $sampaiTanggal])
                    ->selectRaw('COALESCE((
                        SELECT SUM(dj.jumlah * COALESCE(dj.hpp, 0))
                        FROM detail_jual dj
                        WHERE dj.barang_id = barang.id
                          AND dj.is_bonus = false
                          AND dj.created_at::date >= ?
                          AND dj.created_at::date <= ?
                    ), 0) as total_hpp_reguler', [$dariTanggal, $sampaiTanggal])
                    ->selectRaw('COALESCE((
                        SELECT SUM(COALESCE(dj.bonus_qty, 0) * COALESCE(dj.bonus_hpp, 0))
                        FROM detail_jual dj
                        WHERE dj.bonus_barang_id = barang.id
                          AND dj.is_bonus = false
                          AND dj.created_at::date >= ?
                          AND dj.created_at::date <= ?
                    ), 0) as total_hpp_bonus', [$dariTanggal, $sampaiTanggal])
                    ->where(function ($q) use ($dariTanggal, $sampaiTanggal) {
                        $q->whereRaw('(
                            SELECT COUNT(*)
                            FROM detail_jual dj
                            WHERE (dj.barang_id = barang.id OR dj.bonus_barang_id = barang.id)
                              AND dj.created_at::date >= ?
                              AND dj.created_at::date <= ?
                        ) > 0', [$dariTanggal, $sampaiTanggal]);
                    });

                if (!empty($barangId)) {
                    $query->where('barang.id', (int) $barangId);
                }

                return $query;
            })
            ->columns([
                TextColumn::make('nama_barang')
                    ->label('Nama Barang')
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Barang $record) => $record->nomer_seri ?: null),

                TextColumn::make('total_terjual_reguler')
                    ->label('Terjual')
                    ->formatStateUsing(fn ($state, Barang $record) => number_format((float) $state, 0, ',', '.') . ' ' . ($record->satuan ?? 'Pcs'))
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('total_keluar_bonus')
                    ->label('Bonus')
                    ->formatStateUsing(fn ($state, Barang $record) => (float) $state > 0 ? number_format((float) $state, 0, ',', '.') . ' ' . ($record->satuan ?? 'Pcs') : '-')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('total_qty_berkurang')
                    ->label('Total Pengurangan Fisik')
                    ->state(fn (Barang $record): float => (float) ($record->total_terjual_reguler ?? 0) + (float) ($record->total_keluar_bonus ?? 0))
                    ->formatStateUsing(fn ($state, Barang $record) => number_format($state, 0, ',', '.') . ' ' . ($record->satuan ?? 'Pcs'))
                    ->alignCenter()
                    ->weight('bold'),

                TextColumn::make('total_omset')
                    ->label('Total Omset')
                    ->money('IDR', locale: 'id')
                    ->alignRight()
                    ->sortable(),

                TextColumn::make('estimasi_laba_kotor')
                    ->label('Estimasi Laba Kotor')
                    ->state(function (Barang $record): float {
                        $omset = (float) ($record->total_omset ?? 0);
                        $hppReguler = (float) ($record->total_hpp_reguler ?? 0);
                        $hppBonus = (float) ($record->total_hpp_bonus ?? 0);
                        return $omset - $hppReguler - $hppBonus;
                    })
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->alignRight()
                    ->weight('bold')
                    ->color(fn ($state): string => $state >= 0 ? 'success' : 'danger'),
            ])
            ->defaultSort('nama_barang', 'asc')
            ->recordAction('detail')
            ->recordActions([
                Action::make('detail')
                    ->extraAttributes(['class' => 'hidden', 'style' => 'display:none'])
                    ->modalHeading(fn (Barang $record) => "Rincian Transaksi - {$record->nama_barang}")
                    ->modalWidth('7xl')
                    ->modalContent(function (Barang $record) use ($dariTanggal, $sampaiTanggal): View {
                        $penjualanReguler = DetailJual::where('barang_id', $record->id)
                            ->where('is_bonus', false)
                            ->whereDate('created_at', '>=', $dariTanggal)
                            ->whereDate('created_at', '<=', $sampaiTanggal)
                            ->with(['penjualan.user', 'penjualan.karyawan'])
                            ->orderBy('created_at', 'desc')
                            ->get();

                        $keluarBonus = DetailJual::where('bonus_barang_id', $record->id)
                            ->where('is_bonus', false)
                            ->whereDate('created_at', '>=', $dariTanggal)
                            ->whereDate('created_at', '<=', $sampaiTanggal)
                            ->with(['penjualan.user', 'penjualan.karyawan', 'barang'])
                            ->orderBy('created_at', 'desc')
                            ->get();

                        return view('filament.pages.modal-detail-rekapan', [
                            'barang' => $record,
                            'dariTanggal' => $dariTanggal,
                            'sampaiTanggal' => $sampaiTanggal,
                            'penjualanReguler' => $penjualanReguler,
                            'keluarBonus' => $keluarBonus,
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
            ])
            ->actions([])
            ->bulkActions([]);
    }
}
