<?php

namespace App\Filament\Pages;

use App\Filament\Resources\PerpindahanBarangs\PerpindahanBarangResource;
use App\Models\Barang;
use App\Models\BarangGudang;
use App\Models\Gudang;
use App\Models\JenisBarang;
use App\Models\KartuStok;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\Action;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class KontrolStokGudang extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string | array $routePath = 'kontrol-stok-gudang';

    protected static ?string $title = 'Kontrol Stok Gudang';

    protected static ?string $navigationLabel = 'Kontrol Stok Gudang';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected string $view = 'filament.pages.kontrol-stok-gudang';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'gudang_id' => null,
            'barang_id' => null,
            'jenis_barang_id' => null,
            'status_stok' => null,
        ]);
    }

    public function updated($property): void
    {
        if (str_starts_with((string) $property, 'data.')) {
            $this->resetTable();
        }
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
                            'lg' => 4,
                        ])->schema([
                            Select::make('gudang_id')
                                ->label('Filter Gudang')
                                ->placeholder('Semua Gudang')
                                ->options(Gudang::orderBy('nama_gudang')->pluck('nama_gudang', 'id'))
                                ->live()
                                ->afterStateUpdated(fn () => $this->resetTable()),

                            Select::make('barang_id')
                                ->label('Filter Barang')
                                ->placeholder('Semua Barang')
                                ->options(function (Get $get): array {
                                    $query = Barang::query()->where('tipe_barang', '!=', 'barang_jadi');

                                    if ($gudangId = $get('gudang_id')) {
                                        $query->whereHas('gudangs', fn (Builder $q) => $q->where('barang_gudang.gudang_id', $gudangId));
                                    }

                                    if ($jenisId = $get('jenis_barang_id')) {
                                        $query->where('jenis_barang_id', $jenisId);
                                    }

                                    return $query->orderBy('nama_barang')
                                        ->get()
                                        ->mapWithKeys(function (Barang $b): array {
                                            $code = $b->nomer_seri ?: ($b->barcode ?: '-');
                                            $satuan = $b->satuan ?: 'Pcs';
                                            return [$b->id => "{$code} - {$b->nama_barang} ({$satuan})"];
                                        })
                                        ->toArray();
                                })
                                ->getSearchResultsUsing(function (string $search, Get $get): array {
                                    $query = Barang::query()
                                        ->where('tipe_barang', '!=', 'barang_jadi')
                                        ->where(function (Builder $q) use ($search) {
                                            $q->where('nama_barang', 'ilike', "%{$search}%")
                                                ->orWhere('nomer_seri', 'ilike', "%{$search}%")
                                                ->orWhere('barcode', 'ilike', "%{$search}%");
                                        });

                                    if ($gudangId = $get('gudang_id')) {
                                        $query->whereHas('gudangs', fn (Builder $q) => $q->where('barang_gudang.gudang_id', $gudangId));
                                    }

                                    if ($jenisId = $get('jenis_barang_id')) {
                                        $query->where('jenis_barang_id', $jenisId);
                                    }

                                    return $query->orderBy('nama_barang')
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(function (Barang $b): array {
                                            $code = $b->nomer_seri ?: ($b->barcode ?: '-');
                                            $satuan = $b->satuan ?: 'Pcs';
                                            return [$b->id => "{$code} - {$b->nama_barang} ({$satuan})"];
                                        })
                                        ->toArray();
                                })
                                ->getOptionLabelUsing(function ($value): ?string {
                                    $b = Barang::find($value);
                                    if (! $b) {
                                        return null;
                                    }
                                    $code = $b->nomer_seri ?: ($b->barcode ?: '-');
                                    $satuan = $b->satuan ?: 'Pcs';
                                    return "{$code} - {$b->nama_barang} ({$satuan})";
                                })
                                ->searchable()
                                ->preload()
                                ->live()
                                ->afterStateUpdated(fn () => $this->resetTable()),

                            Select::make('jenis_barang_id')
                                ->label('Kategori / Jenis')
                                ->placeholder('Semua Kategori')
                                ->options(JenisBarang::orderBy('nama_jenis')->pluck('nama_jenis', 'id'))
                                ->live()
                                ->afterStateUpdated(fn () => $this->resetTable()),

                            Select::make('status_stok')
                                ->label('Status Stok')
                                ->placeholder('Semua Defisit (Menipis & Habis)')
                                ->options([
                                    'menipis' => 'Hanya Stok Menipis',
                                    'habis' => 'Hanya Stok Habis',
                                    'overstock' => 'Stok Melebihi Maksimum (Overstock)',
                                ])
                                ->live()
                                ->afterStateUpdated(fn () => $this->resetTable()),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }



    public function table(Table $table): Table
    {
        return $table
            ->searchable(false)
            ->query(function (): Builder {
                $gudangId = $this->data['gudang_id'] ?? null;
                $barangId = $this->data['barang_id'] ?? null;
                $jenisBarangId = $this->data['jenis_barang_id'] ?? null;
                $statusStok = $this->data['status_stok'] ?? null;

                $query = BarangGudang::query()
                    ->with(['barang.jenisBarang', 'barang.gudangs', 'gudang'])
                    ->whereHas('barang', fn (Builder $q) => $q->where('tipe_barang', '!=', 'barang_jadi'));

                // Filter Gudang: hanya tampilkan yang gudangnya sama persis seperti di filter
                if (!empty($gudangId)) {
                    $query->where('barang_gudang.gudang_id', $gudangId);
                }

                // Filter Barang Spesifik
                if (!empty($barangId)) {
                    $query->where('barang_gudang.barang_id', $barangId);
                }

                // Filter Kategori
                if (!empty($jenisBarangId)) {
                    $query->whereHas('barang', fn (Builder $q) => $q->where('jenis_barang_id', $jenisBarangId));
                }

                // Filter Status: hanya tampilkan stok menipis dan habis (stok aman tidak dimuat ke tabel), atau overstock
                if ($statusStok === 'habis') {
                    $query->where('barang_gudang.stok', '<=', 0);
                } elseif ($statusStok === 'menipis') {
                    $query->where('barang_gudang.stok_minimum', '>', 0)
                        ->whereColumn('barang_gudang.stok', '<=', 'barang_gudang.stok_minimum')
                        ->where('barang_gudang.stok', '>', 0);
                } elseif ($statusStok === 'overstock') {
                    $query->where('barang_gudang.stok_maksimum', '>', 0)
                        ->whereColumn('barang_gudang.stok', '>', 'barang_gudang.stok_maksimum');
                } else {
                    // Default: hanya tampilkan stok menipis ATAU stok habis
                    $query->where(function (Builder $q) {
                        $q->where('barang_gudang.stok', '<=', 0)
                            ->orWhere(function (Builder $sq) {
                                $sq->where('barang_gudang.stok_minimum', '>', 0)
                                    ->whereColumn('barang_gudang.stok', '<=', 'barang_gudang.stok_minimum');
                            });
                    });
                }

                // Prioritaskan yang selisih batas paling besar, lalu stok terkecil
                return $query
                    ->orderByRaw('(CASE 
                        WHEN barang_gudang.stok_maksimum > 0 AND barang_gudang.stok > barang_gudang.stok_maksimum THEN (barang_gudang.stok - barang_gudang.stok_maksimum)
                        WHEN barang_gudang.stok_minimum > 0 AND barang_gudang.stok <= barang_gudang.stok_minimum THEN (barang_gudang.stok_minimum - barang_gudang.stok) 
                        ELSE 0 END) DESC')
                    ->orderBy('barang_gudang.stok', 'asc');
            })
            ->columns([
                TextColumn::make('index')
                    ->label('No')
                    ->rowIndex()
                    ->width('50px'),

                TextColumn::make('barang.nama_barang')
                    ->label('Nama Barang')
                    ->weight('bold')
                    ->description(fn (BarangGudang $record) => $record->barang?->barcode ?: ($record->barang?->nomer_seri ?: null)),

                TextColumn::make('barang.jenisBarang.nama_jenis')
                    ->label('Kategori')
                    ->placeholder('-')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                TextColumn::make('gudang.nama_gudang')
                    ->label('Lokasi Gudang')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),

                TextColumn::make('stok')
                    ->label('Sisa Stok')
                    ->alignCenter()
                    ->formatStateUsing(fn ($state, BarangGudang $record) => $record->barang ? $record->barang->formatStokBerantai((int) $state) : (string) $state)
                    ->badge()
                    ->color(fn (BarangGudang $record) => match ($record->status_stok) {
                        'habis' => 'danger',
                        'menipis' => 'warning',
                        'overstock' => 'info',
                        default => 'success',
                    }),

                TextColumn::make('stok_minimum')
                    ->label('Batas Min')
                    ->alignCenter()
                    ->formatStateUsing(fn ($state, BarangGudang $record) => $record->barang ? $record->barang->formatStokBerantai((int) $state) : (string) $state)
                    ->color('gray'),

                TextColumn::make('defisit')
                    ->label('Kekurangan Stok')
                    ->alignCenter()
                    ->formatStateUsing(function (BarangGudang $record) {
                        $defisit = $record->defisit;
                        if ($defisit <= 0) {
                            return '-';
                        }
                        return $record->barang ? $record->barang->formatStokBerantai($defisit) : (string) $defisit;
                    })
                    ->color(fn (BarangGudang $record) => $record->defisit > 0 ? 'danger' : 'gray')
                    ->weight('bold'),

                TextColumn::make('stok_gudang_lain')
                    ->label('Stok Gudang Lain')
                    ->html()
                    ->state(function (BarangGudang $record): string {
                        if (! $record->barang) {
                            return '-';
                        }
                        $others = $record->barang->gudangs->filter(fn ($g) => $g->id !== $record->gudang_id);
                        if ($others->isEmpty()) {
                            return '<span style="color:#71717a;font-size:11px">-</span>';
                        }
                        $pills = [];
                        foreach ($others as $og) {
                            $s = (int) $og->pivot->stok;
                            $sFormatted = $record->barang->formatStokBerantai($s);
                            $style = $s > 0
                                ? 'background:#18181b;color:#a1a1aa;border:1px solid #3f3f46;'
                                : 'background:#18181b;color:#52525b;border:1px solid #27272a;';
                            $numColor = $s > 0 ? '#38bdf8' : '#71717a';
                            $pills[] = '<span style="display:inline-block;padding:2px 6px;border-radius:4px;font-size:10.5px;font-weight:600;' . $style . '">'
                                . e($og->nama_gudang) . ': <strong style="color:' . $numColor . '">' . e($sFormatted) . '</strong></span>';
                        }
                        return '<div style="display:flex;flex-direction:column;gap:3px;align-items:flex-start">' . implode('', $pills) . '</div>';
                    }),

                TextColumn::make('status_stok')
                    ->label('Status')
                    ->badge()
                    ->alignCenter()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'habis' => 'Habis',
                        'menipis' => 'Menipis',
                        'overstock' => 'Overstock',
                        default => 'Aman',
                    })
                    ->color(fn ($state) => match ($state) {
                        'habis' => 'danger',
                        'menipis' => 'warning',
                        'overstock' => 'info',
                        default => 'success',
                    }),
            ])
            ->recordAction('detail')
            ->recordActions([
                Action::make('detail')
                    ->extraAttributes(['class' => 'hidden', 'style' => 'display:none'])
                    ->modalHeading(fn (BarangGudang $record) => 'Kontrol Stok: ' . ($record->barang?->nama_barang ?? 'Barang'))
                    ->modalWidth('5xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(function (BarangGudang $record): View {
                        $allGudangStocks = BarangGudang::with('gudang')
                            ->where('barang_id', $record->barang_id)
                            ->get();

                        $recentKartuStok = KartuStok::where('barang_id', $record->barang_id)
                            ->where('gudang_id', $record->gudang_id)
                            ->orderBy('tanggal', 'desc')
                            ->orderBy('id', 'desc')
                            ->limit(5)
                            ->get();

                        $createMutasiUrl = PerpindahanBarangResource::getUrl('create');

                        return view('filament.pages.modal-detail-kontrol-stok-gudang', [
                            'record' => $record,
                            'allGudangStocks' => $allGudangStocks,
                            'recentKartuStok' => $recentKartuStok,
                            'createMutasiUrl' => $createMutasiUrl,
                        ]);
                    }),
            ])
            ->actions([])
            ->bulkActions([]);
    }
}
