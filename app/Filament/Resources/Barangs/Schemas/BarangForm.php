<?php

namespace App\Filament\Resources\Barangs\Schemas;

use App\Models\Aplikator;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\JenisBarang;
use App\Services\HargaAplikatorService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class BarangForm
{
    public static function getUnitOptions(Get|callable $get, ?Barang $record = null): array
    {
        $getVal = fn (string $key) => $get instanceof Get ? $get($key) : (is_callable($get) ? $get($key) : null);

        $options = [];
        $s1 = $getVal('satuan') ?: ($record?->satuan ?? 'Pcs');
        $options[$s1] = "{$s1} (Level 1)";

        $s2 = $getVal('satuan_2') ?: $record?->satuan_2;
        if (filled($s2)) {
            $options[$s2] = "{$s2} (Level 2)";
        }

        $s3 = $getVal('satuan_3') ?: $record?->satuan_3;
        if (filled($s3)) {
            $options[$s3] = "{$s3} (Level 3)";
        }

        $s4 = $getVal('satuan_4') ?: $record?->satuan_4;
        if (filled($s4)) {
            $options[$s4] = "{$s4} (Level 4)";
        }

        return $options;
    }

    public static function getFaktorForUnit(string $satuanNama, Get|callable $get, ?Barang $record = null): int
    {
        $getVal = fn (string $key) => $get instanceof Get ? $get($key) : (is_callable($get) ? $get($key) : null);

        $s1 = $getVal('satuan') ?: ($record?->satuan ?? 'Pcs');
        if ($satuanNama === $s1 || empty($satuanNama)) {
            return 1;
        }

        $s2 = $getVal('satuan_2') ?: $record?->satuan_2;
        $isi2 = max(1, (int) ($getVal('isi_satuan_2') ?: ($record?->isi_satuan_2 ?? 1)));
        if ($satuanNama === $s2) {
            return $isi2;
        }

        $s3 = $getVal('satuan_3') ?: $record?->satuan_3;
        $isi3 = max(1, (int) ($getVal('isi_satuan_3') ?: ($record?->isi_satuan_3 ?? 1)));
        $faktor3 = !empty($s2) ? ($isi3 * $isi2) : $isi3;
        if ($satuanNama === $s3) {
            return $faktor3;
        }

        $s4 = $getVal('satuan_4') ?: $record?->satuan_4;
        $isi4 = max(1, (int) ($getVal('isi_satuan_4') ?: ($record?->isi_satuan_4 ?? 1)));
        $faktor4 = $isi4 * $faktor3;
        if ($satuanNama === $s4) {
            return $faktor4;
        }

        return 1;
    }

    public static function deconstructBaseQtyToBestUnit(int $baseQty, ?Barang $barang): array
    {
        $baseSatuan = $barang?->satuan ?? 'Pcs';
        if (!$barang || $baseQty <= 0) {
            return [
                'qty' => $baseQty,
                'satuan' => $baseSatuan,
            ];
        }

        $units = $barang->getAvailableUnits();
        usort($units, fn($a, $b) => $b['faktor'] <=> $a['faktor']);

        foreach ($units as $u) {
            if ($u['faktor'] > 1 && $baseQty >= $u['faktor'] && ($baseQty % $u['faktor'] === 0)) {
                return [
                    'qty' => (int) ($baseQty / $u['faktor']),
                    'satuan' => $u['satuan'],
                ];
            }
        }

        return [
            'qty' => $baseQty,
            'satuan' => $baseSatuan,
        ];
    }

    public static function hitungTotalStokMinimumGlobal(Get|callable $get, ?Barang $record = null): int
    {
        $getVal = fn (string $key) => $get instanceof Get ? $get($key) : (is_callable($get) ? $get($key) : null);

        $gudangs = Gudang::all();
        $total = 0;

        foreach ($gudangs as $g) {
            $isActive = $getVal("status_pantau_gudang.{$g->id}");
            if ($isActive === null) {
                $isActive = true;
            }

            if ((bool) $isActive) {
                $qty = (int) ($getVal("stok_minimum_gudang_display.{$g->id}") ?? 0);
                $unit = $getVal("satuan_stok_minimum_gudang.{$g->id}") ?: ($getVal('satuan') ?: ($record?->satuan ?? 'Pcs'));
                $faktor = static::getFaktorForUnit($unit, $get, $record);
                $total += ($qty * $faktor);
            }
        }

        return $total;
    }

    public static function syncGlobalStokMinimum(Set $set, Get|callable $get, ?Barang $record = null): void
    {
        $isGlobalActive = (bool) ($get instanceof Get ? ($get('pantau_stok_global') ?? true) : (($get)('pantau_stok_global') ?? true));
        if (! $isGlobalActive) {
            $set('stok_minimum_display', 0);
            $set('stok_minimum', 0);
            return;
        }

        $total = static::hitungTotalStokMinimumGlobal($get, $record);
        $set('stok_minimum_display', $total);
        $set('stok_minimum', $total);
    }

    public static function hitungTotalStokMaksimumGlobal(Get|callable $get, ?Barang $record = null): int
    {
        $getVal = fn (string $key) => $get instanceof Get ? $get($key) : (is_callable($get) ? $get($key) : null);

        $gudangs = Gudang::all();
        $total = 0;

        foreach ($gudangs as $g) {
            $isActive = $getVal("status_pantau_gudang.{$g->id}");
            if ($isActive === null) {
                $isActive = true;
            }

            if ((bool) $isActive) {
                $qty = (int) ($getVal("stok_maksimum_gudang_display.{$g->id}") ?? 0);
                if ($qty > 0) {
                    $unit = $getVal("satuan_stok_maksimum_gudang.{$g->id}") ?: ($getVal('satuan') ?: ($record?->satuan ?? 'Pcs'));
                    $faktor = static::getFaktorForUnit($unit, $get, $record);
                    $total += ($qty * $faktor);
                }
            }
        }

        return $total;
    }

    public static function syncGlobalStokMaksimum(Set $set, Get|callable $get, ?Barang $record = null): void
    {
        $total = static::hitungTotalStokMaksimumGlobal($get, $record);
        $set('stok_maksimum_display', $total);
        $set('stok_maksimum', $total);
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                static::getInformasiDasarSection(),
                static::getPengaturanCafeSection(),
                static::getHargaDeliverySection(),
                static::getHargaBertingkatSection(),
                static::getTingkatanSatuanSection(),
                static::getStokMinimumSection(),
            ]);
    }

    public static function getInformasiDasarSection(): Section
    {
        return Section::make('Informasi Dasar Barang')
            ->description('Data utama barang dan harga eceran dasar (Level 1)')
            ->columnSpanFull()
            ->schema([
                Grid::make(2)->schema([
                    Select::make('jenis_barang_id')
                        ->label('Jenis Barang')
                        ->relationship('jenisBarang', 'nama_jenis')
                        ->required()
                        ->searchable()
                        ->preload(),
                    TextInput::make('nama_barang')
                        ->label('Nama Barang')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('nomer_seri')
                        ->label('Nomor Seri')
                        ->placeholder('Otomatis digenerate saat simpan (misal: ROK-0001)')
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn ($record) => filled($record?->nomer_seri)),
                    TextInput::make('barcode')
                        ->label('Barcode Produk (Opsional)')
                        ->placeholder('Kosongkan untuk otomatis menggunakan Nomor Seri')
                        ->helperText('Jika barang tidak punya barcode pabrik, otomatis disamakan dengan Nomor Seri.')
                        ->maxLength(255),
                    TextInput::make('satuan')
                        ->label('Satuan Terkecil / Dasar (Level 1)')
                        ->placeholder('Misal: Pcs, Batang, Botol, Saset')
                        ->default('Pcs')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, Set $set, Get $get, ?Barang $record) {
                            BarangForm::syncGlobalStokMinimum($set, $get, $record);
                        }),
                    TextInput::make('harga_beli')
                        ->label('Harga Beli Terakhir (Level 1)')
                        ->numeric()
                        ->required()
                        ->prefix('Rp')
                        ->default(0),
                    TextInput::make('hpp')
                        ->label('HPP Average (Rata-Rata Tertimbang)')
                        ->numeric()
                        ->prefix('Rp')
                        ->readOnly()
                        ->helperText('Otomatis dihitung ulang secara akurat saat ada transaksi Pembelian baru.')
                        ->default(0),
                    TextInput::make('harga_jual')
                        ->label('Harga Jual Eceran (Level 1)')
                        ->numeric()
                        ->required()
                        ->prefix('Rp')
                        ->default(0)
                        ->live(onBlur: true),
                ]),
            ]);
    }

    public static function getPengaturanCafeSection(): Section
    {
        return Section::make('Pengaturan Cafe & POS')
                    ->description('Klasifikasi fungsi produk di cafe, ketersediaan menu, dan integrasi layar kasir')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            FileUpload::make('gambar')
                                ->label('Foto Produk')
                                ->image()
                                ->directory('barang')
                                ->disk('public')
                                ->maxSize(2048)
                                ->automaticallyResizeImagesMode('cover')
                                ->imageAspectRatio('1:1')
                                ->automaticallyCropImagesToAspectRatio()
                                ->helperText('Format JPG, PNG, atau WebP (Maksimal 2MB). Ditampilkan pada katalog kasir.')
                                ->columnSpan(1),

                            Grid::make(1)->schema([
                                Select::make('tipe_barang')
                                    ->label('Tipe Barang / Fungsi Cafe')
                                    ->placeholder('Pilih tipe fungsi barang...')
                                    ->options([
                                        'barang_jadi' => 'Barang Jadi / Menu Olahan (Latte, nasi goreng, dll)',
                                        'barang_dagang' => 'Barang Dagang (Air mineral botol, snack kemasan, dll)',
                                        'kemasan' => 'Kemasan / Pembungkus (Cup takeaway, lunch box, paper bag, dll)',
                                        'barang_pembantu' => 'Barang Pembantu (Sedotan, sendok plastik, tissue, dll)',
                                        'bahan_baku' => 'Bahan Baku (Kopi biji, susu, beras, dll)',
                                        'setengah_jadi' => 'Setengah Jadi (Konsentrat espresso, saus marinasi, dll)',
                                    ])
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if (in_array($state, ['bahan_baku', 'setengah_jadi', 'barang_pembantu', 'kemasan'])) {
                                            $set('bisa_dijual', false);
                                            $set('butuh_proses', false);
                                            $set('kemasan_id', null);
                                        } elseif ($state === 'barang_jadi') {
                                            $set('bisa_dijual', true);
                                            $set('butuh_proses', true);
                                            $set('stok_minimum', 0);
                                        } elseif ($state === 'barang_dagang') {
                                            $set('bisa_dijual', true);
                                            $set('butuh_proses', false);
                                        }
                                    }),

                                Grid::make(3)->schema([
                                    Toggle::make('bisa_dijual')
                                        ->label('Dapat Dijual')
                                        ->helperText('Muncul di kasir')
                                        ->default(true),

                                    Toggle::make('butuh_proses')
                                        ->label('Butuh Proses')
                                        ->helperText('Tiket barista/dapur')
                                        ->default(false),

                                    Toggle::make('status')
                                        ->label('Ketersediaan')
                                        ->helperText('Menu tersedia')
                                        ->formatStateUsing(fn ($state) => $state === 'tersedia' || $state === null || $state === true || $state === 1)
                                        ->dehydrateStateUsing(fn ($state) => $state ? 'tersedia' : 'habis')
                                        ->default(true),
                                ]),

                                Select::make('kemasan_id')
                                    ->label('Kemasan Default (Take Away / Delivery)')
                                    ->placeholder('Pilih kemasan default... (Kosongkan jika tidak ada kemasan)')
                                    ->relationship(
                                        'kemasan',
                                        'nama_barang',
                                        modifyQueryUsing: fn ($query) => $query->whereIn('tipe_barang', ['kemasan', 'barang_pembantu'])->where('status', 'tersedia')
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Kemasan yang otomatis disertakan saat menu ini dipesan untuk Take Away atau Delivery.')
                                    ->hidden(fn ($get) => in_array($get('tipe_barang'), ['kemasan', 'barang_pembantu'])),
                            ])->columnSpan(1),
                        ]),
                    ]);
    }

    public static function getHargaDeliverySection(): Section
    {
        return Section::make('Harga Delivery / Aplikator Online')
                    ->description('Atur harga jual khusus untuk masing-masing platform delivery (GoFood, GrabFood, ShopeeFood, Maxim, dll)')
                    ->collapsible()
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('hargaAplikators')
                            ->relationship('hargaAplikators')
                            ->label('Daftar Harga Delivery per Aplikator')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('aplikator_id')
                                        ->label('Platform / Aplikator')
                                        ->relationship(
                                            'aplikator',
                                            'nama_aplikator',
                                            modifyQueryUsing: fn ($query) => $query->where('status_aktif', true)
                                        )
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->distinct()
                                        ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                        ->live()
                                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                            if (! $state) {
                                                return;
                                            }

                                            $hargaDasar = (int) ($get('../../harga_jual') ?? 0);
                                            $aplikator = Aplikator::find($state);

                                            if ($aplikator) {
                                                $komisi = (float) $aplikator->persentase_komisi;
                                                $hargaKalkulasi = app(HargaAplikatorService::class)->hitungHarga($hargaDasar, $komisi);
                                                $set('harga_jual', $hargaKalkulasi);
                                            }
                                        }),

                                    TextInput::make('harga_jual')
                                        ->label('Harga Jual Delivery')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->required()
                                        ->helperText(function (Get $get) {
                                            $aplikatorId = $get('aplikator_id');
                                            if ($aplikatorId) {
                                                $aplikator = Aplikator::find($aplikatorId);
                                                if ($aplikator && (float) $aplikator->persentase_komisi > 0) {
                                                    return "Otomatis dihitung: Harga dasar + komisi {$aplikator->nama_aplikator} ({$aplikator->persentase_komisi}%). Dapat disesuaikan secara manual.";
                                                }
                                            }
                                            return 'Harga yang berlaku saat menu ini dipesan melalui aplikator tersebut';
                                        }),
                                ]),
                            ])
                            ->columns(1)
                            ->addActionLabel('+ Tambah Harga Aplikator')
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->cloneable(),
                    ]);
    }

    public static function getHargaBertingkatSection(): Section
    {
        return Section::make('Harga Jual Bertingkat (3 Level Quantity)')
                    ->description('Opsional: Atur potongan harga bertingkat berdasarkan kuantitas minimal pembelian.')
                    ->collapsible()
                    ->columnSpanFull()
                    ->schema([
                        Select::make('tipe_harga_bertingkat')
                            ->label('Tipe Potongan Tier')
                            ->options([
                                'persen' => 'Persentase Diskon (%)',
                                'nominal' => 'Nominal Harga Jual per Unit (Rp)',
                            ])
                            ->default('persen')
                            ->required(),

                        Grid::make(3)->schema([
                            Section::make('Tier Level 1')
                                ->schema([
                                    TextInput::make('min_qty_1')
                                        ->label('Minimal Qty Tier 1')
                                        ->numeric()
                                        ->default(1)
                                        ->required(),
                                    TextInput::make('nilai_tier_1')
                                        ->label('Nilai Tier 1 (% atau Rp)')
                                        ->numeric()
                                        ->default(0)
                                        ->helperText('Isi 0 jika tidak ada diskon di Tier 1'),
                                ]),

                            Section::make('Tier Level 2')
                                ->schema([
                                    TextInput::make('min_qty_2')
                                        ->label('Minimal Qty Tier 2')
                                        ->numeric()
                                        ->placeholder('Misal: 6'),
                                    TextInput::make('nilai_tier_2')
                                        ->label('Nilai Tier 2 (% atau Rp)')
                                        ->numeric()
                                        ->default(0),
                                ]),

                            Section::make('Tier Level 3')
                                ->schema([
                                    TextInput::make('min_qty_3')
                                        ->label('Minimal Qty Tier 3')
                                        ->numeric()
                                        ->placeholder('Misal: 20'),
                                    TextInput::make('nilai_tier_3')
                                        ->label('Nilai Tier 3 (% atau Rp)')
                                        ->numeric()
                                        ->default(0),
                                ]),
                        ]),
                ]);
    }

    public static function getTingkatanSatuanSection(): Section
    {
        return Section::make('Tingkatan Satuan & Harga Grosir (Level 2 - 4)')
                    ->description('Opsional: Atur konversi satuan bertingkat terhadap Satuan Pertama (Level 1). Kosongkan jika produk hanya memiliki 1 satuan.')
                    ->collapsible()
                    ->columnSpanFull()
                    ->schema([
                        // Level 2
                        Section::make('Satuan Level 2')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('satuan_2')
                                        ->label('Nama Satuan Level 2')
                                        ->placeholder('Misal: Pack, Renceng'),
                                    TextInput::make('isi_satuan_2')
                                        ->label('Isi Konversi (Jumlah Satuan Pertama / Level 1)')
                                        ->numeric()
                                        ->placeholder('Misal: 20'),
                                    TextInput::make('harga_beli_2')
                                        ->label('Harga Beli Level 2')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->placeholder('Otomatis jika kosong'),
                                    TextInput::make('harga_jual_2')
                                        ->label('Harga Jual Level 2')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->placeholder('Otomatis jika kosong'),
                                ]),
                            ]),

                        // Level 3
                        Section::make('Satuan Level 3')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('satuan_3')
                                        ->label('Nama Satuan Level 3')
                                        ->placeholder('Misal: Slof, Box'),
                                    TextInput::make('isi_satuan_3')
                                        ->label('Isi Konversi (Jumlah Satuan Pertama / Level 1)')
                                        ->numeric()
                                        ->placeholder('Misal: 200'),
                                    TextInput::make('harga_beli_3')
                                        ->label('Harga Beli Level 3')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->placeholder('Otomatis jika kosong'),
                                    TextInput::make('harga_jual_3')
                                        ->label('Harga Jual Level 3')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->placeholder('Otomatis jika kosong'),
                                ]),
                            ]),

                        // Level 4
                        Section::make('Satuan Level 4')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('satuan_4')
                                        ->label('Nama Satuan Level 4')
                                        ->placeholder('Misal: Bal, Karton'),
                                    TextInput::make('isi_satuan_4')
                                        ->label('Isi Konversi (Jumlah Satuan Pertama / Level 1)')
                                        ->numeric()
                                        ->placeholder('Misal: 2000'),
                                    TextInput::make('harga_beli_4')
                                        ->label('Harga Beli Level 4')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->placeholder('Otomatis jika kosong'),
                                    TextInput::make('harga_jual_4')
                                        ->label('Harga Jual Level 4')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->placeholder('Otomatis jika kosong'),
                                ]),
                            ]),
                    ]);
    }

    public static function getStokMinimumSection(): Section
    {
        return Section::make('Ambang Batas Stok Minimum & Maksimum (Global & Gudang)')
                    ->description('Atur batas minimum (alarm stok menipis) dan batas maksimum (pencegahan overstock saat pembelian & mutasi). Nilai global otomatis terakumulasi dari gudang aktif.')
                    ->collapsible()
                    ->columnSpanFull()
                    ->hidden(fn (Get $get) => $get('tipe_barang') === 'barang_jadi')
                    ->schema([
                        Hidden::make('stok_minimum')
                            ->default(fn (Get $get, ?Barang $record) => BarangForm::hitungTotalStokMinimumGlobal($get, $record)),

                        Hidden::make('stok_maksimum')
                            ->default(fn (Get $get, ?Barang $record) => BarangForm::hitungTotalStokMaksimumGlobal($get, $record)),

                        Section::make('Ambang Batas Global (Seluruh Toko)')
                            ->compact()
                            ->schema([
                                Grid::make(3)->schema([
                                    Toggle::make('pantau_stok_global')
                                        ->label(fn (Get $get) => $get('pantau_stok_global') ? 'Pemantauan Global: AKTIF' : 'Pemantauan Global: DIMATIKAN')
                                        ->helperText(fn (Get $get) => $get('pantau_stok_global')
                                            ? 'Peringatan aktif jika akumulasi seluruh stok toko berada di bawah batas minimum.'
                                            : 'Pemantauan stok global toko dinonaktifkan (peringatan toko mati, namun gudang tetap dipantau).')
                                        ->dehydrated(false)
                                        ->live()
                                        ->default(true)
                                        ->afterStateUpdated(function ($state, Set $set, Get $get, ?Barang $record) {
                                            BarangForm::syncGlobalStokMinimum($set, $get, $record);
                                            BarangForm::syncGlobalStokMaksimum($set, $get, $record);
                                        }),

                                    TextInput::make('stok_minimum_display')
                                        ->label('Batas Minimum Global')
                                        ->numeric()
                                        ->readOnly()
                                        ->dehydrated(false)
                                        ->default(fn (Get $get, ?Barang $record) => BarangForm::hitungTotalStokMinimumGlobal($get, $record))
                                        ->suffix(fn (Get $get, ?Barang $record) => ($get('satuan') ?: ($record?->satuan ?? 'Pcs')) . ' (Satuan Dasar)')
                                        ->helperText(function (Get $get, ?Barang $record) {
                                            $baseSat = $get('satuan') ?: ($record?->satuan ?? 'Pcs');
                                            $isGlobalActive = (bool) ($get('pantau_stok_global') ?? true);
                                            if (! $isGlobalActive) {
                                                return "Pemantauan global nonaktif (0 {$baseSat}). Peringatan toko dimatikan.";
                                            }
                                            $total = BarangForm::hitungTotalStokMinimumGlobal($get, $record);
                                            return "Total akumulasi minimum seluruh gudang: {$total} {$baseSat}.";
                                        }),

                                    TextInput::make('stok_maksimum_display')
                                        ->label('Batas Maksimum Global')
                                        ->numeric()
                                        ->readOnly()
                                        ->dehydrated(false)
                                        ->default(fn (Get $get, ?Barang $record) => BarangForm::hitungTotalStokMaksimumGlobal($get, $record))
                                        ->suffix(fn (Get $get, ?Barang $record) => ($get('satuan') ?: ($record?->satuan ?? 'Pcs')) . ' (Satuan Dasar)')
                                        ->helperText(function (Get $get, ?Barang $record) {
                                            $baseSat = $get('satuan') ?: ($record?->satuan ?? 'Pcs');
                                            $total = BarangForm::hitungTotalStokMaksimumGlobal($get, $record);
                                            if ($total <= 0) {
                                                return 'Tanpa batas maksimum toko (unlimited).';
                                            }
                                            return "Total akumulasi maksimum seluruh gudang: {$total} {$baseSat}.";
                                        }),
                                ]),
                            ]),

                        Section::make('Batas Stok Minimum & Maksimum Khusus Per Gudang')
                            ->key('section_gudang')
                            ->description('Tentukan batas minimum dan batas maksimum untuk masing-masing gudang fisik.')
                            ->headerActions([
                                Action::make('samakan_ke_semua_gudang')
                                    ->label('Terapkan Nilai Gudang Pertama ke Semua')
                                    ->icon('heroicon-m-arrows-pointing-out')
                                    ->color('gray')
                                    ->action(function (Set $set, Get $get, ?Barang $record) {
                                        $gudangs = Gudang::all();
                                        if ($gudangs->isEmpty()) {
                                            return;
                                        }
                                        $firstGudang = $gudangs->first();
                                        $firstStatus = (bool) ($get("status_pantau_gudang.{$firstGudang->id}") ?? true);
                                        $firstQtyMin = (int) ($get("stok_minimum_gudang_display.{$firstGudang->id}") ?? 20);
                                        $firstUnitMin = $get("satuan_stok_minimum_gudang.{$firstGudang->id}") ?: ($get('satuan') ?: ($record?->satuan ?? 'Pcs'));
                                        $firstQtyMax = (int) ($get("stok_maksimum_gudang_display.{$firstGudang->id}") ?? 0);
                                        $firstUnitMax = $get("satuan_stok_maksimum_gudang.{$firstGudang->id}") ?: ($get('satuan') ?: ($record?->satuan ?? 'Pcs'));

                                        foreach ($gudangs as $g) {
                                            $set("status_pantau_gudang.{$g->id}", $firstStatus);
                                            $set("stok_minimum_gudang_display.{$g->id}", $firstQtyMin);
                                            $set("satuan_stok_minimum_gudang.{$g->id}", $firstUnitMin);
                                            $set("stok_maksimum_gudang_display.{$g->id}", $firstQtyMax);
                                            $set("satuan_stok_maksimum_gudang.{$g->id}", $firstUnitMax);
                                        }

                                        BarangForm::syncGlobalStokMinimum($set, $get, $record);
                                        BarangForm::syncGlobalStokMaksimum($set, $get, $record);
                                    }),
                            ])
                            ->schema([
                                Grid::make(2)->schema(function () {
                                    $gudangs = Gudang::all();
                                    $fields = [];
                                    foreach ($gudangs as $gudang) {
                                        $fields[] = Section::make($gudang->nama_gudang)
                                            ->compact()
                                            ->schema([
                                                Toggle::make("status_pantau_gudang.{$gudang->id}")
                                                    ->label(fn (Get $get) => $get("status_pantau_gudang.{$gudang->id}") ? "Pemantauan {$gudang->nama_gudang}: AKTIF" : "Pemantauan {$gudang->nama_gudang}: DIMATIKAN")
                                                    ->helperText(fn (Get $get) => $get("status_pantau_gudang.{$gudang->id}")
                                                        ? "Batas stok aktif khusus {$gudang->nama_gudang}."
                                                        : "Pemantauan khusus {$gudang->nama_gudang} sedang DIMATIKAN.")
                                                    ->dehydrated(false)
                                                    ->live()
                                                    ->default(true)
                                                    ->afterStateUpdated(function ($state, Set $set, Get $get, ?Barang $record) {
                                                        BarangForm::syncGlobalStokMinimum($set, $get, $record);
                                                        BarangForm::syncGlobalStokMaksimum($set, $get, $record);
                                                    }),

                                                Grid::make(2)
                                                    ->visible(fn (Get $get) => (bool) $get("status_pantau_gudang.{$gudang->id}"))
                                                    ->schema([
                                                        TextInput::make("stok_minimum_gudang_display.{$gudang->id}")
                                                            ->label('Batas Minimum')
                                                            ->numeric()
                                                            ->minValue(0)
                                                            ->default(20)
                                                            ->dehydrated(false)
                                                            ->live()
                                                            ->afterStateUpdated(function ($state, Set $set, Get $get, ?Barang $record) {
                                                                BarangForm::syncGlobalStokMinimum($set, $get, $record);
                                                            })
                                                            ->helperText(function (Get $get, ?Barang $record) use ($gudang) {
                                                                $qty = (int) ($get("stok_minimum_gudang_display.{$gudang->id}") ?? 0);
                                                                $unit = $get("satuan_stok_minimum_gudang.{$gudang->id}") ?: ($get('satuan') ?: ($record?->satuan ?? 'Pcs'));
                                                                $faktor = BarangForm::getFaktorForUnit($unit, $get, $record);
                                                                $baseSat = $get('satuan') ?: ($record?->satuan ?? 'Pcs');
                                                                $total = $qty * $faktor;
                                                                return "Min: {$total} {$baseSat}";
                                                            }),

                                                        Select::make("satuan_stok_minimum_gudang.{$gudang->id}")
                                                            ->label('Satuan Min')
                                                            ->options(fn (Get $get, ?Barang $record) => BarangForm::getUnitOptions($get, $record))
                                                            ->placeholder(fn (Get $get, ?Barang $record) => ($get('satuan') ?: ($record?->satuan ?? 'Pcs')) . ' (Dasar)')
                                                            ->nullable()
                                                            ->dehydrated(false)
                                                            ->live()
                                                            ->afterStateUpdated(function ($state, Set $set, Get $get, ?Barang $record) {
                                                                BarangForm::syncGlobalStokMinimum($set, $get, $record);
                                                            }),

                                                        TextInput::make("stok_maksimum_gudang_display.{$gudang->id}")
                                                            ->label('Batas Maksimum (0 = Bebas)')
                                                            ->numeric()
                                                            ->minValue(0)
                                                            ->default(0)
                                                            ->dehydrated(false)
                                                            ->live()
                                                            ->afterStateUpdated(function ($state, Set $set, Get $get, ?Barang $record) {
                                                                BarangForm::syncGlobalStokMaksimum($set, $get, $record);
                                                            })
                                                            ->helperText(function (Get $get, ?Barang $record) use ($gudang) {
                                                                $qty = (int) ($get("stok_maksimum_gudang_display.{$gudang->id}") ?? 0);
                                                                if ($qty <= 0) {
                                                                    return 'Kapasitas tanpa batas';
                                                                }
                                                                $unit = $get("satuan_stok_maksimum_gudang.{$gudang->id}") ?: ($get('satuan') ?: ($record?->satuan ?? 'Pcs'));
                                                                $faktor = BarangForm::getFaktorForUnit($unit, $get, $record);
                                                                $baseSat = $get('satuan') ?: ($record?->satuan ?? 'Pcs');
                                                                $total = $qty * $faktor;
                                                                return "Maks: {$total} {$baseSat}";
                                                            }),

                                                        Select::make("satuan_stok_maksimum_gudang.{$gudang->id}")
                                                            ->label('Satuan Maks')
                                                            ->options(fn (Get $get, ?Barang $record) => BarangForm::getUnitOptions($get, $record))
                                                            ->placeholder(fn (Get $get, ?Barang $record) => ($get('satuan') ?: ($record?->satuan ?? 'Pcs')) . ' (Dasar)')
                                                            ->nullable()
                                                            ->dehydrated(false)
                                                            ->live()
                                                            ->afterStateUpdated(function ($state, Set $set, Get $get, ?Barang $record) {
                                                                BarangForm::syncGlobalStokMaksimum($set, $get, $record);
                                                            }),
                                                    ]),
                                            ]);
                                    }
                                    return $fields;
                                }),
                            ]),
                    ]);
    }
}
