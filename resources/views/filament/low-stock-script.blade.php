@php
    use App\Models\Barang;
    use Illuminate\Database\Eloquent\Builder;

    // JANGAN jalankan jika user belum login / di halaman login
    if (! auth()->check()) {
        return;
    }

    // Query barang stok menipis (hanya muat gudang yang stoknya <= 5)
    $lowStockItems = Barang::query()
        ->with(['gudangs' => function ($q) {
            $q->where('barang_gudang.stok', '<=', 5);
        }, 'jenisBarang'])
        ->whereHas('gudangs', function (Builder $q) {
            $q->where('barang_gudang.stok', '<=', 5);
        })
        ->get();

    $jsonData = [];
    $totalGudangBadges = 0;

    foreach ($lowStockItems as $b) {
        $gudangs = [];
        foreach ($b->gudangs as $g) {
            $stokVal = (int) $g->pivot->stok;
            $gudangs[] = [
                'nama' => $g->nama_gudang,
                'stok' => $stokVal,
                'stok_formatted' => $b->formatStokBerantai($stokVal),
            ];
            $totalGudangBadges++;
        }
        $jsonData[] = [
            'nama' => $b->nama_barang,
            'jenis' => $b->jenisBarang->nama_jenis ?? '-',
            'harga' => 'Rp ' . number_format($b->harga_jual, 0, ',', '.'),
            'gudangs' => $gudangs,
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
    var totalGudangBadges = {{ $totalGudangBadges }};

    function buildLowStockHtml(items) {
        var rows = '';
        items.forEach(function (item) {
            var gudangBadges = '';
            item.gudangs.forEach(function (g) {
                var cls = 'background:#450a0a;color:#fca5a5;border:1px solid #7f1d1d';
                gudangBadges += '<span style="display:inline-block;padding:2px 7px;border-radius:6px;font-size:11px;font-weight:600;margin:1.5px 2px 1.5px 0;' + cls + '">'
                    + g.nama + ': ' + g.stok_formatted + '</span>';
            });
            rows += '<tr style="border-bottom:1px solid #27272a">'
                + '<td style="padding:7px 8px;font-weight:600;color:#fafafa;text-align:left;font-size:12px">' + item.nama + '</td>'
                + '<td style="padding:7px 8px;text-align:left;white-space:nowrap"><span style="display:inline-block;padding:2px 7px;border-radius:6px;font-size:10px;font-weight:500;background:#27272a;color:#a1a1aa;white-space:nowrap">' + item.jenis + '</span></td>'
                + '<td style="padding:7px 8px;text-align:left">' + gudangBadges + '</td>'
                + '<td style="padding:7px 8px;font-weight:700;color:#fafafa;text-align:right;white-space:nowrap;font-size:12px">' + item.harga + '</td>'
                + '</tr>';
        });

        return '<div style="max-height:42vh;overflow-y:auto;margin-top:10px;border-radius:10px;border:1px solid #27272a">'
            + '<table style="width:100%;border-collapse:collapse;font-size:12px;text-align:left">'
            + '<thead><tr style="background:#18181b;border-bottom:1.5px solid #3f3f46;position:sticky;top:0;z-index:1">'
            + '<th style="padding:8px;font-weight:700;color:#a1a1aa;text-transform:uppercase;font-size:10px;letter-spacing:0.05em;background:#18181b">Barang</th>'
            + '<th style="padding:8px;font-weight:700;color:#a1a1aa;text-transform:uppercase;font-size:10px;letter-spacing:0.05em;white-space:nowrap;background:#18181b">Kategori</th>'
            + '<th style="padding:8px;font-weight:700;color:#a1a1aa;text-transform:uppercase;font-size:10px;letter-spacing:0.05em;background:#18181b">Gudang Bermasalah &amp; Sisa Stok</th>'
            + '<th style="padding:8px;font-weight:700;color:#a1a1aa;text-transform:uppercase;font-size:10px;letter-spacing:0.05em;text-align:right;background:#18181b">Harga Jual</th>'
            + '</tr></thead>'
            + '<tbody>' + rows + '</tbody>'
            + '</table></div>';
    }

    window.__showLowStockAlert = function () {
        if (typeof Swal === 'undefined') return;
        var subText = totalGudangBadges > lowStockData.length
            ? 'Ada <strong style="color:#f87171">' + lowStockData.length + ' barang</strong> (tersebar di <strong style="color:#f87171">' + totalGudangBadges + ' lokasi gudang</strong>) yang perlu segera di-restok.'
            : 'Ada <strong style="color:#f87171">' + lowStockData.length + ' barang</strong> perlu segera di-restok.';

        Swal.fire({
            icon: 'warning',
            title: '<span style="color:#fafafa;font-size:18px;font-weight:700">Stok Menipis!</span>',
            html: '<p style="color:#a1a1aa;font-size:13px;margin-bottom:2px">' + subText + '</p>'
                + buildLowStockHtml(lowStockData),
            width: '600px',
            padding: '1.25rem',
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
