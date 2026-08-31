<?php

namespace App\Filament\Pages;

use App\Models\DetailJual;
use App\Models\Karyawan;
use App\Models\Penjualan;
use App\Models\User;
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

class RekapanKasir extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string | array $routePath = 'rekapan-kasir';

    protected static ?string $title = 'Rekapan Kasir';

    protected static ?string $navigationLabel = 'Rekapan Kasir';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 5;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected string $view = 'filament.pages.rekapan-kasir';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'dari_tanggal' => now()->format('Y-m-d'),
            'sampai_tanggal' => now()->format('Y-m-d'),
            'kasir' => null,
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

                            Select::make('kasir')
                                ->label('Filter Kasir')
                                ->placeholder('Semua Kasir')
                                ->options(function () {
                                    $options = [];
                                    $karyawans = Karyawan::orderBy('nama_karyawan')->get();
                                    foreach ($karyawans as $k) {
                                        $options['karyawan_' . $k->getKey()] = $k->nama_karyawan . ' [Kasir]';
                                    }
                                    $users = User::orderBy('name')->get();
                                    foreach ($users as $u) {
                                        $options['user_' . $u->getKey()] = $u->name . ' [Admin]';
                                    }
                                    return $options;
                                })
                                ->searchable()
                                ->preload()
                                ->live()
                                ->afterStateUpdated(fn () => $this->resetTable()),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function getOverviewStatsProperty(): array
    {
        $dariTanggal = $this->data['dari_tanggal'] ?? now()->format('Y-m-d');
        $sampaiTanggal = $this->data['sampai_tanggal'] ?? now()->format('Y-m-d');
        $kasirFilter = $this->data['kasir'] ?? null;

        $query = Penjualan::query()
            ->whereDate('tanggal', '>=', $dariTanggal)
            ->whereDate('tanggal', '<=', $sampaiTanggal);

        if (!empty($kasirFilter)) {
            if (str_starts_with($kasirFilter, 'karyawan_')) {
                $kId = (int) substr($kasirFilter, 9);
                $query->where('karyawan_id', $kId);
            } elseif (str_starts_with($kasirFilter, 'user_')) {
                $uId = (int) substr($kasirFilter, 5);
                $query->where('user_id', $uId);
            }
        }

        $stat = $query->reorder()->selectRaw("
            COUNT(*) as count_transaksi,
            COALESCE(COUNT(CASE WHEN LOWER(jenis_pembayaran) = 'tunai' THEN 1 END), 0) as count_tunai,
            COALESCE(COUNT(CASE WHEN LOWER(jenis_pembayaran) = 'qris' THEN 1 END), 0) as count_qris,
            COALESCE(COUNT(CASE WHEN LOWER(jenis_pembayaran) IN ('transfer', 'bank', 'debit') THEN 1 END), 0) as count_transfer,
            COALESCE(SUM(CASE WHEN LOWER(jenis_pembayaran) = 'tunai' THEN neto ELSE 0 END), 0) as sum_tunai,
            COALESCE(SUM(CASE WHEN LOWER(jenis_pembayaran) = 'qris' THEN neto ELSE 0 END), 0) as sum_qris,
            COALESCE(SUM(CASE WHEN LOWER(jenis_pembayaran) IN ('transfer', 'bank', 'debit') THEN neto ELSE 0 END), 0) as sum_transfer,
            COALESCE(SUM(neto), 0) as sum_omset
        ")->first();

        return [
            'count_transaksi' => (int) ($stat->count_transaksi ?? 0),
            'count_tunai' => (int) ($stat->count_tunai ?? 0),
            'count_qris' => (int) ($stat->count_qris ?? 0),
            'count_transfer' => (int) ($stat->count_transfer ?? 0),
            'sum_tunai' => (float) ($stat->sum_tunai ?? 0),
            'sum_qris' => (float) ($stat->sum_qris ?? 0),
            'sum_transfer' => (float) ($stat->sum_transfer ?? 0),
            'sum_omset' => (float) ($stat->sum_omset ?? 0),
        ];
    }

    public function table(Table $table): Table
    {
        $dariTanggal = $this->data['dari_tanggal'] ?? now()->format('Y-m-d');
        $sampaiTanggal = $this->data['sampai_tanggal'] ?? now()->format('Y-m-d');
        $kasirFilter = $this->data['kasir'] ?? null;

        return $table
            ->query(function () use ($dariTanggal, $sampaiTanggal, $kasirFilter): Builder {
                $subQuery = Penjualan::query()
                    ->select('penjualan.karyawan_id', 'penjualan.user_id')
                    ->selectRaw('MIN(penjualan.id) as id')
                    ->selectRaw('COUNT(*) as total_transaksi')
                    ->selectRaw('COALESCE((
                        SELECT SUM(dj.jumlah)
                        FROM detail_jual dj
                        JOIN penjualan p2 ON p2.id = dj.penjualan_id
                        WHERE (
                            (penjualan.karyawan_id IS NOT NULL AND p2.karyawan_id = penjualan.karyawan_id)
                            OR (penjualan.karyawan_id IS NULL AND p2.karyawan_id IS NULL AND p2.user_id = penjualan.user_id)
                        )
                        AND p2.tanggal >= ? AND p2.tanggal <= ?
                    ), 0) as total_qty_item', [$dariTanggal, $sampaiTanggal])
                    ->selectRaw("SUM(CASE WHEN LOWER(penjualan.jenis_pembayaran) = 'tunai' THEN penjualan.neto ELSE 0 END) as total_tunai")
                    ->selectRaw("SUM(CASE WHEN LOWER(penjualan.jenis_pembayaran) = 'qris' THEN penjualan.neto ELSE 0 END) as total_qris")
                    ->selectRaw("SUM(CASE WHEN LOWER(penjualan.jenis_pembayaran) IN ('transfer', 'bank', 'debit') THEN penjualan.neto ELSE 0 END) as total_transfer")
                    ->selectRaw('SUM(penjualan.neto) as total_omset')
                    ->whereDate('penjualan.tanggal', '>=', $dariTanggal)
                    ->whereDate('penjualan.tanggal', '<=', $sampaiTanggal)
                    ->groupBy('penjualan.karyawan_id', 'penjualan.user_id');

                if (!empty($kasirFilter)) {
                    if (str_starts_with($kasirFilter, 'karyawan_')) {
                        $kId = (int) substr($kasirFilter, 9);
                        $subQuery->where('penjualan.karyawan_id', $kId);
                    } elseif (str_starts_with($kasirFilter, 'user_')) {
                        $uId = (int) substr($kasirFilter, 5);
                        $subQuery->where('penjualan.user_id', $uId);
                    }
                }

                return Penjualan::query()
                    ->fromSub($subQuery, 'penjualan')
                    ->with(['karyawan', 'user']);
            })
            ->columns([
                TextColumn::make('nama_kasir')
                    ->label('Nama Kasir')
                    ->state(fn (Penjualan $record) => $record->nama_kasir)
                    ->weight('bold'),

                TextColumn::make('total_transaksi')
                    ->label('Total Transaksi')
                    ->formatStateUsing(fn ($state) => number_format($state, 0, ',', '.') . ' Transaksi')
                    ->alignCenter()
                    ->summarize(Sum::make()->label('')->formatStateUsing(fn ($state) => number_format($state, 0, ',', '.') . ' Transaksi')),

                TextColumn::make('total_qty_item')
                    ->label('Total Qty Item')
                    ->formatStateUsing(fn ($state) => number_format($state, 0, ',', '.'))
                    ->alignCenter()
                    ->summarize(Sum::make()->label('')->formatStateUsing(fn ($state) => number_format($state, 0, ',', '.'))),

                TextColumn::make('total_tunai')
                    ->label('Total Tunai')
                    ->money('IDR', locale: 'id')
                    ->alignRight()
                    ->summarize(Sum::make()->label('')->money('IDR', locale: 'id')),

                TextColumn::make('total_qris')
                    ->label('Total QRIS')
                    ->money('IDR', locale: 'id')
                    ->alignRight()
                    ->summarize(Sum::make()->label('')->money('IDR', locale: 'id')),

                TextColumn::make('total_transfer')
                    ->label('Total Bank / Transfer')
                    ->money('IDR', locale: 'id')
                    ->alignRight()
                    ->summarize(Sum::make()->label('')->money('IDR', locale: 'id')),

                TextColumn::make('total_omset')
                    ->label('Total Omset Kasir')
                    ->money('IDR', locale: 'id')
                    ->alignRight()
                    ->weight('bold')
                    ->summarize(Sum::make()->label('')->money('IDR', locale: 'id')),
            ])
            ->recordAction('detail')
            ->recordActions([
                Action::make('detail')
                    ->extraAttributes(['class' => 'hidden', 'style' => 'display:none'])
                    ->modalHeading(fn (Penjualan $record) => "Rincian Faktur Penjualan - {$record->nama_kasir}")
                    ->modalWidth('7xl')
                    ->modalContent(function (Penjualan $record) use ($dariTanggal, $sampaiTanggal): View {
                        $invoicesQuery = Penjualan::query()
                            ->whereDate('tanggal', '>=', $dariTanggal)
                            ->whereDate('tanggal', '<=', $sampaiTanggal)
                            ->with(['details.barang', 'karyawan', 'user'])
                            ->orderBy('created_at', 'desc');

                        if ($record->karyawan_id) {
                            $invoicesQuery->where('karyawan_id', $record->karyawan_id);
                        } else {
                            $invoicesQuery->whereNull('karyawan_id')->where('user_id', $record->user_id);
                        }

                        $invoices = $invoicesQuery->get();

                        return view('filament.pages.modal-detail-rekapan-kasir', [
                            'kasirName' => $record->nama_kasir,
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
