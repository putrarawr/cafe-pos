<div style="font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 13px; color: #e4e4e7; display: flex; flex-direction: column; gap: 20px;">

    {{-- HEADER CARD INFO --}}
    <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; padding: 20px; border-radius: 14px; background: #18181b; border: 1px solid #27272a; box-shadow: 0 4px 20px rgba(0,0,0,0.25);">
        
        {{-- WAKTU AKTIVITAS --}}
        <div>
            <span style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #71717a; display: block; margin-bottom: 6px;">Waktu Aktivitas</span>
            <div style="display: flex; items-center: center; gap: 8px;">
                <div style="width: 28px; height: 28px; border-radius: 8px; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; shrink: 0;">
                    <svg style="width: 15px; height: 15px; color: #a1a1aa;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <span style="font-weight: 700; font-size: 14px; color: #fafafa; letter-spacing: -0.01em;">
                        {{ \Carbon\Carbon::parse($record->created_at)->setTimezone('Asia/Jakarta')->format('d M Y') }}
                    </span>
                    <span style="font-size: 12px; font-weight: 600; color: #a1a1aa; display: block;">
                        {{ \Carbon\Carbon::parse($record->created_at)->setTimezone('Asia/Jakarta')->format('H:i:s') }} WIB
                    </span>
                </div>
            </div>
        </div>

        {{-- PELAKU / CAUSER --}}
        <div>
            <span style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #71717a; display: block; margin-bottom: 6px;">Pelaku (Causer)</span>
            <div style="display: flex; align-items: center; gap: 10px;">
                @php
                    $initial = strtoupper(substr($record->causer?->name ?? $record->causer?->nama_karyawan ?? 'S', 0, 1));
                    $causerTitle = $record->causer?->name ?? $record->causer?->nama_karyawan ?? 'Sistem / Guest';
                    $causerType = $record->causer_type ? class_basename($record->causer_type) . " #{$record->causer_id}" : 'Sistem Otomatis';
                @endphp
                <div style="width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #ffffff; font-weight: 800; font-size: 13px; display: flex; align-items: center; justify-content: center; shrink: 0; box-shadow: 0 2px 8px rgba(99,102,241,0.35);">
                    {{ $initial }}
                </div>
                <div style="min-width: 0;">
                    <p style="font-weight: 700; font-size: 13px; color: #fafafa; margin: 0; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        {{ $causerTitle }}
                    </p>
                    <span style="font-size: 11px; font-weight: 500; color: #818cf8; display: inline-block; margin-top: 2px;">
                        {{ $causerType }}
                    </span>
                </div>
            </div>
        </div>

        {{-- JENIS AKSI --}}
        <div style="padding-top: 10px; border-top: 1px solid #27272a;">
            <span style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #71717a; display: block; margin-bottom: 6px;">Jenis Aksi</span>
            <div>
                @php
                    $event = strtolower($record->event ?? 'default');
                    $badgeStyle = match($event) {
                        'created' => 'background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid rgba(16,185,129,0.35);',
                        'updated' => 'background: rgba(245,158,11,0.15); color: #fbbf24; border: 1px solid rgba(245,158,11,0.35);',
                        'deleted' => 'background: rgba(244,63,94,0.15); color: #f43f5e; border: 1px solid rgba(244,63,94,0.35);',
                        'login', 'logged_in' => 'background: rgba(14,165,233,0.15); color: #38bdf8; border: 1px solid rgba(14,165,233,0.35);',
                        default => 'background: #27272a; color: #a1a1aa; border: 1px solid #3f3f46;',
                    };
                    $label = match($event) {
                        'created' => 'DIBUAT (CREATED)',
                        'updated' => 'DIUBAH (UPDATED)',
                        'deleted' => 'DIHAPUS (DELETED)',
                        'login', 'logged_in' => 'LOGIN',
                        default => strtoupper($event),
                    };
                @endphp
                <span style="display: inline-block; padding: 4px 12px; border-radius: 8px; font-size: 11px; font-weight: 800; tracking: 0.05em; {{ $badgeStyle }}">
                    {{ $label }}
                </span>
            </div>
        </div>

        {{-- OBJEK TARGET --}}
        <div style="padding-top: 10px; border-top: 1px solid #27272a;">
            <span style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #71717a; display: block; margin-bottom: 6px;">Objek Target</span>
            <div>
                @php
                    $subjectType = $record->subject_type ? class_basename($record->subject_type) : '-';
                    $subjectLabel = match($subjectType) {
                        'Penjualan' => 'Penjualan (Barang Keluar)',
                        'Pembelian' => 'Pembelian (Barang Masuk)',
                        'DetailBeli' => 'Detail Barang Masuk',
                        'DetailJual' => 'Detail Barang Keluar',
                        'Barang' => 'Master Barang',
                        'Gudang' => 'Master Gudang',
                        'Karyawan' => 'Data Karyawan',
                        'PerpindahanBarang' => 'Perpindahan Barang',
                        'Supplier' => 'Data Supplier',
                        'JenisBarang' => 'Jenis Barang',
                        'User' => 'Pengguna Admin',
                        default => $subjectType,
                    };
                    $subjectTitle = null;
                    if ($record->subject) {
                        $s = $record->subject;
                        $subjectTitle = $s->nomer_nota ?? $s->nomer_entry ?? $s->nama_barang ?? $s->nama_gudang ?? $s->nama_karyawan ?? $s->nama_supplier ?? $s->nama_jenis ?? $s->name ?? null;
                    }
                @endphp
                <p style="font-weight: 700; font-size: 13px; color: #fafafa; margin: 0;">
                    {{ $subjectLabel }}
                    @if($record->subject_id)
                        <span style="font-size: 11px; font-weight: 600; color: #71717a; margin-left: 4px;">(ID: #{{ $record->subject_id }})</span>
                    @endif
                </p>
                @if($subjectTitle)
                    <p style="font-size: 11px; font-weight: 500; color: #a1a1aa; margin: 3px 0 0 0;">
                        Ref: <span style="font-weight: 700; color: #38bdf8;">{{ $subjectTitle }}</span>
                    </p>
                @endif
            </div>
        </div>

    </div>

    {{-- DESKRIPSI CATATAN --}}
    @if(!empty($record->description) && $record->description !== $record->event)
        <div style="padding: 12px 16px; border-radius: 10px; background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.25); color: #fde047; font-size: 12px; font-weight: 500;">
            <strong style="color: #fbbf24;">Catatan:</strong> {{ $record->description }}
        </div>
    @endif

    {{-- RINCIAN PERUBAHAN DATA (BEFORE VS AFTER) --}}
    <div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
            <h4 style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #a1a1aa; margin: 0;">
                Rincian Perubahan Data (Before vs After)
            </h4>
        </div>

        @php
            $properties = $record->properties;
            $attributes = [];
            $old = [];

            if (is_array($properties) || $properties instanceof \Illuminate\Support\Collection) {
                $attributes = $properties['attributes'] ?? [];
                $old = $properties['old'] ?? [];
            }

            // helper format nilai
            $formatVal = function ($val) {
                if (is_null($val)) return '<span style="font-style:italic;color:#52525b">(Kosong)</span>';
                if (is_bool($val)) return $val ? '<span style="font-weight:700;color:#34d399">Ya</span>' : '<span style="font-weight:700;color:#f43f5e">Tidak</span>';
                if (is_array($val) || is_object($val)) return '<pre style="margin:0;font-family:monospace;font-size:11px;white-space:pre-wrap;word-break:break-all;color:#d4d4d8">' . e(json_encode($val, JSON_PRETTY_PRINT)) . '</pre>';
                return e((string)$val);
            };

            $allKeys = array_unique(array_merge(array_keys($old), array_keys($attributes)));
        @endphp

        @if(!empty($allKeys))
            <div style="border-radius: 12px; border: 1px solid #27272a; overflow: hidden; background: #18181b;">
                <table style="width: 100%; border-collapse: collapse; font-size: 12px; text-align: left;">
                    <thead>
                        <tr style="background: #09090b; border-bottom: 2px solid #27272a;">
                            <th style="padding: 10px 14px; font-weight: 700; color: #71717a; text-transform: uppercase; font-size: 10px; letter-spacing: 0.06em; width: 30%;">Atribut / Field</th>
                            <th style="padding: 10px 14px; font-weight: 700; color: #71717a; text-transform: uppercase; font-size: 10px; letter-spacing: 0.06em; width: 35%;">Nilai Sebelum (Old)</th>
                            <th style="padding: 10px 14px; font-weight: 700; color: #71717a; text-transform: uppercase; font-size: 10px; letter-spacing: 0.06em; width: 35%;">Nilai Sesudah (New)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($allKeys as $key)
                            @php
                                $hasOld = array_key_exists($key, $old);
                                $hasNew = array_key_exists($key, $attributes);
                                $oldVal = $hasOld ? $old[$key] : null;
                                $newVal = $hasNew ? $attributes[$key] : null;
                                $isDifferent = $hasOld && $hasNew && $oldVal !== $newVal;
                            @endphp
                            <tr style="border-bottom: 1px solid #27272a;">
                                <td style="padding: 10px 14px; font-weight: 700; color: #fafafa; font-family: monospace; font-size: 11px;">
                                    {{ $key }}
                                </td>
                                <td style="padding: 10px 14px;">
                                    @if($hasOld)
                                        <div style="padding: 6px 10px; border-radius: 6px; font-size: 12px; {{ $isDifferent ? 'background: rgba(244,63,94,0.12); color: #fda4af; border: 1px solid rgba(244,63,94,0.25);' : 'color: #a1a1aa;' }}">
                                            {!! $formatVal($oldVal) !!}
                                        </div>
                                    @else
                                        <span style="color: #52525b; font-style: italic;">-</span>
                                    @endif
                                </td>
                                <td style="padding: 10px 14px;">
                                    @if($hasNew)
                                        <div style="padding: 6px 10px; border-radius: 6px; font-size: 12px; {{ $isDifferent || !$hasOld ? 'background: rgba(16,185,129,0.12); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.25);' : 'color: #fafafa;' }}">
                                            {!! $formatVal($newVal) !!}
                                        </div>
                                    @else
                                        <span style="color: #52525b; font-style: italic;">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div style="padding: 24px; border-radius: 12px; border: 1px dashed #27272a; background: rgba(24,24,27,0.5); text-align: center; color: #71717a; font-size: 12px;">
                <svg style="width: 32px; height: 32px; color: #3f3f46; margin: 0 auto 8px auto; display: block;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p style="font-weight: 600; color: #a1a1aa; margin: 0 0 2px 0;">Tidak ada perubahan atribut yang tercatat</p>
                <span style="font-size: 11px; color: #52525b;">Aktivitas ini dibuat sebagai catatan kejadian tanpa modifikasi field data.</span>
            </div>
        @endif
    </div>

</div>
