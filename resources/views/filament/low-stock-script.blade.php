@php
    use App\Models\Barang;
    use Illuminate\Database\Eloquent\Builder;

    // JANGAN jalankan jika user belum login / di halaman login
    if (! auth()->check()) {
        return;
    }

    // Query barang stok menipis Global (Alarm Belanja Supplier: Total Toko <= stok_minimum global)
    $lowStockItems = Barang::query()
        ->where('tipe_barang', '!=', 'barang_jadi')
        ->where('barang.stok_minimum', '>', 0)
        ->whereRaw('(SELECT COALESCE(SUM(stok), 0) FROM barang_gudang WHERE barang_gudang.barang_id = barang.id) <= barang.stok_minimum')
        ->with(['gudangs', 'jenisBarang'])
        ->get();

    $jsonData = [];

    foreach ($lowStockItems as $b) {
        $totalStok = (int) $b->gudangs->sum('pivot.stok');
        $globalMin = (int) ($b->stok_minimum ?? 0);
        $defisit = max(0, $globalMin - $totalStok);

        $jsonData[] = [
            'nama' => $b->nama_barang,
            'jenis' => $b->jenisBarang->nama_jenis ?? '-',
            'stok' => $totalStok,
            'stok_formatted' => $b->formatStokBerantai($totalStok),
            'stok_min' => $globalMin,
            'stok_min_formatted' => $b->formatStokBerantai($globalMin),
            'defisit_formatted' => $b->formatStokBerantai($defisit),
            'harga' => 'Rp ' . number_format($b->harga_jual, 0, ',', '.'),
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

    function buildLowStockHtml(items) {
        var rows = '';
        items.forEach(function (item, index) {
            var bgRow = (index % 2 === 0) ? '#111113' : '#18181b';
            var statusBadge = item.stok <= 0
                ? '<span style="display:inline-block;padding:3px 8px;border-radius:4px;font-size:11px;font-weight:600;background:#450a0a;color:#fecaca;border:1px solid #991b1b">Habis (' + item.stok_formatted + ')</span>'
                : '<span style="display:inline-block;padding:3px 8px;border-radius:4px;font-size:11px;font-weight:600;background:#451a03;color:#fed7aa;border:1px solid #c2410c">Sisa: ' + item.stok_formatted + '</span>';

            rows += '<tr style="background:' + bgRow + ';border-bottom:1px solid #27272a">'
                + '<td style="padding:12px 14px;font-weight:600;color:#ffffff;text-align:left;font-size:13px;line-height:1.45;border-right:1px solid #27272a;vertical-align:middle">' + item.nama + '</td>'
                + '<td style="padding:12px 14px;text-align:center;border-right:1px solid #27272a;vertical-align:middle"><span style="display:inline-block;padding:3px 10px;border-radius:4px;font-size:11px;font-weight:500;background:#202024;border:1px solid #3f3f46;color:#d4d4d8;white-space:nowrap">' + item.jenis + '</span></td>'
                + '<td style="padding:12px 14px;text-align:left;border-right:1px solid #27272a;vertical-align:middle">'
                + '<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">' + statusBadge
                + '<span style="color:#a1a1aa;font-size:11px">(Batas: ' + item.stok_min_formatted + ')</span>'
                + '</div>'
                + '</td>'
                + '<td style="padding:12px 14px;font-weight:700;color:#f87171;text-align:right;white-space:nowrap;font-size:12px;vertical-align:middle">' + item.defisit_formatted + '</td>'
                + '</tr>';
        });

        return '<div style="max-height:50vh;overflow-y:auto;margin-top:14px;border-radius:8px;border:1px solid #3f3f46">'
            + '<table style="width:100%;border-collapse:collapse;font-size:12px;text-align:left;table-layout:fixed">'
            + '<colgroup>'
            + '<col style="width:34%">'
            + '<col style="width:18%">'
            + '<col style="width:32%">'
            + '<col style="width:16%">'
            + '</colgroup>'
            + '<thead><tr style="background:#202024;border-bottom:2px solid #3f3f46;position:sticky;top:0;z-index:2">'
            + '<th style="padding:11px 14px;font-weight:700;color:#e4e4e7;text-transform:uppercase;font-size:11px;letter-spacing:0.05em;background:#202024;border-right:1px solid #3f3f46;text-align:left">Barang</th>'
            + '<th style="padding:11px 14px;font-weight:700;color:#e4e4e7;text-transform:uppercase;font-size:11px;letter-spacing:0.05em;background:#202024;border-right:1px solid #3f3f46;text-align:center">Kategori</th>'
            + '<th style="padding:11px 14px;font-weight:700;color:#e4e4e7;text-transform:uppercase;font-size:11px;letter-spacing:0.05em;background:#202024;border-right:1px solid #3f3f46;text-align:left">Total Toko vs Batas Min</th>'
            + '<th style="padding:11px 14px;font-weight:700;color:#e4e4e7;text-transform:uppercase;font-size:11px;letter-spacing:0.05em;background:#202024;text-align:right">Defisit Belanja</th>'
            + '</tr></thead>'
            + '<tbody>' + rows + '</tbody>'
            + '</table></div>'
            + '<div style="margin-top:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;border-top:1px solid #27272a;padding-top:12px">'
            + '<span style="color:#71717a;font-size:12px">Untuk mutasi antar-gudang, buka modul Kontrol Stok Gudang.</span>'
            + '<a href="/admin/kontrol-stok-gudang" style="display:inline-flex;align-items:center;gap:6px;background:#202024;border:1px solid #3f3f46;color:#e4e4e7;padding:6px 12px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;transition:all 0.2s">Kontrol Stok Gudang &rarr;</a>'
            + '</div>';
    }

    window.__showLowStockAlert = function () {
        if (typeof Swal === 'undefined') return;
        var subText = 'Ada <strong style="color:#f87171">' + lowStockData.length + ' barang</strong> yang stok total seluruh cafe menipis dan perlu order ke supplier.';

        Swal.fire({
            icon: 'warning',
            title: '<span style="color:#fafafa;font-size:20px;font-weight:700">Alarm Belanja: Stok Toko Menipis</span>',
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
