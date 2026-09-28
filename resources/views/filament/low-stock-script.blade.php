@php
    use App\Models\Barang;
    use Illuminate\Database\Eloquent\Builder;

    // JANGAN jalankan jika user belum login / di halaman login
    if (! auth()->check()) {
        return;
    }

    // Query barang stok menipis (Total stok <= global stok_minimum ATAU stok gudang <= stok_minimum gudang)
    $lowStockItems = Barang::query()
        ->where('tipe_barang', '!=', 'barang_jadi')
        ->with(['gudangs', 'jenisBarang'])
        ->where(function (Builder $query) {
            $query->where(function (Builder $q) {
                $q->where('barang.stok_minimum', '>', 0)
                    ->whereRaw('(SELECT COALESCE(SUM(stok), 0) FROM barang_gudang WHERE barang_gudang.barang_id = barang.id) <= barang.stok_minimum');
            })->orWhereHas('gudangs', function (Builder $q) {
                $q->where('barang_gudang.stok_minimum', '>', 0)
                    ->whereColumn('barang_gudang.stok', '<=', 'barang_gudang.stok_minimum');
            });
        })
        ->get();

    $jsonData = [];
    $totalAlertBadges = 0;

    foreach ($lowStockItems as $b) {
        $badges = [];
        $totalStok = (int) $b->gudangs->sum('pivot.stok');
        $globalMin = (int) ($b->stok_minimum ?? 20);
        $isGlobalMenipis = $globalMin > 0 && $totalStok <= $globalMin;

        if ($isGlobalMenipis) {
            $badges[] = [
                'type' => 'global',
                'label' => 'Total Toko',
                'stok' => $totalStok,
                'stok_formatted' => $b->formatStokBerantai($totalStok),
                'stok_min' => $globalMin,
                'stok_min_formatted' => $b->formatStokBerantai($globalMin),
            ];
            $totalAlertBadges++;
        }

        foreach ($b->gudangs as $g) {
            $stokVal = (int) $g->pivot->stok;
            $minVal = (int) ($g->pivot->stok_minimum ?? $globalMin);
            if ($minVal > 0 && $stokVal <= $minVal) {
                $badges[] = [
                    'type' => 'gudang',
                    'label' => $g->nama_gudang,
                    'stok' => $stokVal,
                    'stok_formatted' => $b->formatStokBerantai($stokVal),
                    'stok_min' => $minVal,
                    'stok_min_formatted' => $b->formatStokBerantai($minVal),
                ];
                $totalAlertBadges++;
            }
        }

        $jsonData[] = [
            'nama' => $b->nama_barang,
            'jenis' => $b->jenisBarang->nama_jenis ?? '-',
            'harga' => 'Rp ' . number_format($b->harga_jual, 0, ',', '.'),
            'badges' => $badges,
        ];
    }

    // Cek halaman dashboard & status pernah tampil di sesi ini
    $isDashboard = request()->routeIs('filament.admin.pages.dashboard');
    $alreadyShown = session()->get('low_stock_swal_shown', false);
    $shouldAutoShow = $isDashboard && ! $alreadyShown && count($jsonData) > 0;

    if ($shouldAutoShow) {
        session()->put('low_stock_swal_shown', true);
    }
@endphp

@if(count($jsonData) > 0)
<script>
(function () {
    var lowStockData = {!! json_encode($jsonData) !!};
    var totalAlertBadges = {{ $totalAlertBadges }};

    function buildLowStockHtml(items) {
        var rows = '';
        items.forEach(function (item, index) {
            var statusBadges = '';
            item.badges.forEach(function (badge) {
                var cls = badge.type === 'global'
                    ? 'background:#451a03;color:#fed7aa;border:1px solid #c2410c'
                    : 'background:#450a0a;color:#fecaca;border:1px solid #991b1b';
                statusBadges += '<span style="display:inline-block;padding:3px 8px;border-radius:4px;font-size:11px;font-weight:600;' + cls + '">'
                    + badge.label + ': ' + badge.stok_formatted + ' (Min: ' + badge.stok_min_formatted + ')</span>';
            });

            var bgRow = (index % 2 === 0) ? '#111113' : '#18181b';

            rows += '<tr style="background:' + bgRow + ';border-bottom:1px solid #27272a">'
                + '<td style="padding:12px 14px;font-weight:600;color:#ffffff;text-align:left;font-size:13px;line-height:1.45;border-right:1px solid #27272a;vertical-align:middle">' + item.nama + '</td>'
                + '<td style="padding:12px 14px;text-align:center;border-right:1px solid #27272a;vertical-align:middle"><span style="display:inline-block;padding:3px 10px;border-radius:4px;font-size:11px;font-weight:500;background:#202024;border:1px solid #3f3f46;color:#d4d4d8;white-space:nowrap">' + item.jenis + '</span></td>'
                + '<td style="padding:12px 14px;text-align:left;border-right:1px solid #27272a;vertical-align:middle"><div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">' + statusBadges + '</div></td>'
                + '<td style="padding:12px 14px;font-weight:700;color:#ffffff;text-align:right;white-space:nowrap;font-size:13px;vertical-align:middle">' + item.harga + '</td>'
                + '</tr>';
        });

        return '<div style="max-height:52vh;overflow-y:auto;margin-top:14px;border-radius:8px;border:1px solid #3f3f46">'
            + '<table style="width:100%;border-collapse:collapse;font-size:12px;text-align:left;table-layout:fixed">'
            + '<colgroup>'
            + '<col style="width:30%">'
            + '<col style="width:16%">'
            + '<col style="width:40%">'
            + '<col style="width:14%">'
            + '</colgroup>'
            + '<thead><tr style="background:#202024;border-bottom:2px solid #3f3f46;position:sticky;top:0;z-index:2">'
            + '<th style="padding:11px 14px;font-weight:700;color:#e4e4e7;text-transform:uppercase;font-size:11px;letter-spacing:0.05em;background:#202024;border-right:1px solid #3f3f46;text-align:left">Barang</th>'
            + '<th style="padding:11px 14px;font-weight:700;color:#e4e4e7;text-transform:uppercase;font-size:11px;letter-spacing:0.05em;background:#202024;border-right:1px solid #3f3f46;text-align:center">Kategori</th>'
            + '<th style="padding:11px 14px;font-weight:700;color:#e4e4e7;text-transform:uppercase;font-size:11px;letter-spacing:0.05em;background:#202024;border-right:1px solid #3f3f46;text-align:left">Status Menipis &amp; Sisa Stok</th>'
            + '<th style="padding:11px 14px;font-weight:700;color:#e4e4e7;text-transform:uppercase;font-size:11px;letter-spacing:0.05em;background:#202024;text-align:right">Harga Jual</th>'
            + '</tr></thead>'
            + '<tbody>' + rows + '</tbody>'
            + '</table></div>';
    }

    window.__showLowStockAlert = function () {
        if (typeof Swal === 'undefined') return;
        var subText = totalAlertBadges > lowStockData.length
            ? 'Ada <strong style="color:#f87171">' + lowStockData.length + ' barang</strong> yang perlu segera di-restok (terdeteksi di batas global &amp; lokasi gudang).'
            : 'Ada <strong style="color:#f87171">' + lowStockData.length + ' barang</strong> perlu segera di-restok.';

        Swal.fire({
            icon: 'warning',
            title: '<span style="color:#fafafa;font-size:20px;font-weight:700">Stok Menipis!</span>',
            html: '<p style="color:#a1a1aa;font-size:13.5px;margin-bottom:4px">' + subText + '</p>'
                + buildLowStockHtml(lowStockData),
            width: 'min(980px, 94vw)',
            padding: '1.75rem',
            showCloseButton: true,
            confirmButtonText: 'Mengerti',
            confirmButtonColor: '#ea580c',
            background: '#09090b',
            color: '#fafafa',
            customClass: {
                popup: 'swal-low-stock-compact',
                closeButton: 'swal-close-dark',
            },
        });
    };

    @if($shouldAutoShow)
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () { window.__showLowStockAlert(); });
        } else {
            setTimeout(function () { window.__showLowStockAlert(); }, 100);
        }
    @endif
})();
</script>
@endif
