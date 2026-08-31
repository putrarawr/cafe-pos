<div style="font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #18181b; border: 1px solid #27272a; border-radius: 16px; padding: 18px 24px; color: #fafafa; display: flex; align-items: center; justify-content: space-between; gap: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.25); height: 100%;">

    {{-- KIRI: STATUS & WAKTU SERVER --}}
    <div style="display: flex; flex-direction: column; gap: 6px;">
        <div style="font-size: 15px; font-weight: 800; color: #ffffff;">
            {{ $tanggal }}
        </div>
        <div style="font-size: 13px; color: #a1a1aa; font-weight: 500;">
            Waktu Server: <span style="font-weight: 700; color: #38bdf8;">{{ $waktu }}</span>
        </div>
    </div>

    {{-- KANAN: SHORTCUT BUTTON TERMINAL KASIR --}}
    <div style="display: flex; align-items: center; gap: 10px; shrink: 0;">
        <a href="/kasir" target="_blank" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 10px; background: #ffffff; color: #09090b; font-weight: 800; font-size: 12px; text-decoration: none; box-shadow: 0 2px 10px rgba(255,255,255,0.15);">
            <svg style="width: 15px; height: 15px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            Terminal Kasir
        </a>
    </div>
</div>