<x-filament-panels::page>
    <div class="space-y-6 pb-6">
        <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 px-6 py-7 text-white shadow-xl sm:px-8 sm:py-9">
            <div class="pointer-events-none absolute -right-16 -top-24 h-72 w-72 rounded-full border-[36px] border-white/5"></div>
            <div class="pointer-events-none absolute -bottom-24 right-1/4 h-56 w-56 rounded-full bg-[#b57d45]/10 blur-3xl"></div>

            <div class="relative flex flex-col gap-7 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.16em] text-[#d3b48e]">
                        <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                        Pusat kendali pelabuhan
                    </div>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Selamat datang, {{ auth()->user()?->getFilamentName() ?? 'Admin' }}</h2>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-slate-300 sm:text-base">
                        Pantau booking, jadwal kapal, dan verifikasi manifest dari satu tempat.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('filament.admin.resources.bookings.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-[#b57d45] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#a66f3b] focus:outline-none focus:ring-2 focus:ring-[#d3b48e] focus:ring-offset-2 focus:ring-offset-slate-900">
                        <x-filament::icon icon="heroicon-m-clipboard-document-list" class="h-4 w-4" />
                        Pantau booking
                    </a>
                    <a href="{{ route('filament.admin.resources.voyages.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/70">
                        <x-filament::icon icon="heroicon-m-map" class="h-4 w-4" />
                        Jadwal voyage
                    </a>
                </div>
            </div>

            <div class="relative mt-8 flex flex-wrap gap-x-8 gap-y-3 border-t border-white/10 pt-5 text-sm text-slate-300">
                <div class="flex items-center gap-2">
                    <x-filament::icon icon="heroicon-m-calendar-days" class="h-4 w-4 text-[#c9a77d]" />
                    {{ now()->translatedFormat('l, d F Y') }}
                </div>
                <div class="flex items-center gap-2">
                    <x-filament::icon icon="heroicon-m-truck" class="h-4 w-4 text-[#c9a77d]" />
                    {{ number_format($totalShips, 0, ',', '.') }} kapal terdaftar
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan operasional">
            @foreach ($stats as $stat)
                @php
                    $tone = match ($stat['tone']) {
                        'amber' => ['icon' => 'bg-amber-50 text-amber-700', 'dot' => 'bg-amber-500'],
                        'blue' => ['icon' => 'bg-sky-50 text-sky-700', 'dot' => 'bg-sky-500'],
                        'emerald' => ['icon' => 'bg-emerald-50 text-emerald-700', 'dot' => 'bg-emerald-500'],
                        default => ['icon' => 'bg-violet-50 text-violet-700', 'dot' => 'bg-violet-500'],
                    };
                @endphp
                <a href="{{ $stat['url'] }}" class="group rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-gray-300 hover:shadow-md">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-500">{{ $stat['label'] }}</p>
                            <p class="mt-3 text-3xl font-bold tracking-tight text-gray-950">{{ number_format($stat['value'], 0, ',', '.') }}</p>
                        </div>
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $tone['icon'] }}">
                            <x-filament::icon :icon="$stat['icon']" class="h-5 w-5" />
                        </span>
                    </div>
                    <div class="mt-5 flex items-center gap-2 border-t border-gray-100 pt-4 text-xs text-gray-500">
                        <span class="h-2 w-2 rounded-full {{ $tone['dot'] }}"></span>
                        {{ $stat['description'] }}
                    </div>
                </a>
            @endforeach
        </section>

        @php
            $chartPoints = $bookingTrend->map(fn ($point) => "{$point['x']},{$point['y']}")->implode(' ');
            $chartArea = 'M 30 168 L ' . $chartPoints . ' L 408 168 Z';
        @endphp
        <section class="grid grid-cols-1 gap-6 xl:grid-cols-5" aria-label="Grafik operasional">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm xl:col-span-3">
                <div class="flex flex-wrap items-start justify-between gap-3 px-5 pt-5 sm:px-6">
                    <div>
                        <h3 class="text-base font-semibold text-gray-950">Tren booking</h3>
                        <p class="mt-1 text-sm text-gray-500">Jumlah booking selama tujuh hari terakhir.</p>
                    </div>
                    <div class="rounded-xl bg-[#f3eee7] px-3 py-2 text-right">
                        <p class="text-xs font-medium text-[#87613e]">Total 7 hari</p>
                        <p class="text-lg font-bold leading-tight text-[#45372a]">{{ number_format($bookingTrend->sum('total'), 0, ',', '.') }}</p>
                    </div>
                </div>

                <div class="px-4 pb-5 pt-3 sm:px-6">
                    <div class="flex items-center gap-3 text-xs text-gray-500">
                        <span class="h-2.5 w-2.5 rounded-full bg-[#b57d45]"></span>
                        Booking masuk
                    </div>
                    <div class="mt-2 overflow-hidden">
                        <svg viewBox="0 0 438 190" role="img" aria-label="Grafik booking tujuh hari terakhir" class="h-52 w-full overflow-visible">
                            <defs>
                                <linearGradient id="booking-chart-fill" x1="0" x2="0" y1="0" y2="1">
                                    <stop offset="0%" stop-color="#b57d45" stop-opacity=".2" />
                                    <stop offset="100%" stop-color="#b57d45" stop-opacity="0" />
                                </linearGradient>
                            </defs>
                            <line x1="24" y1="42" x2="414" y2="42" stroke="#e5e7eb" stroke-dasharray="4 5" />
                            <line x1="24" y1="94" x2="414" y2="94" stroke="#e5e7eb" stroke-dasharray="4 5" />
                            <line x1="24" y1="146" x2="414" y2="146" stroke="#e5e7eb" stroke-dasharray="4 5" />
                            <path d="{{ $chartArea }}" fill="url(#booking-chart-fill)" />
                            <polyline points="{{ $chartPoints }}" fill="none" stroke="#a66f3b" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
                            @foreach ($bookingTrend as $point)
                                <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="5" fill="#fff" stroke="#a66f3b" stroke-width="3">
                                    <title>{{ $point['date'] }}: {{ $point['total'] }} booking</title>
                                </circle>
                            @endforeach
                        </svg>
                    </div>
                    <div class="-mt-1 grid grid-cols-7 text-center text-xs font-medium text-gray-500">
                        @foreach ($bookingTrend as $point)
                            <div>
                                <span class="block">{{ $point['label'] }}</span>
                                <span class="mt-1 block text-[11px] font-normal text-gray-400">{{ $point['date'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#244650] via-[#2c5554] to-[#314a43] p-6 text-white shadow-sm xl:col-span-2">
                <div class="pointer-events-none absolute -right-10 -top-12 h-40 w-40 rounded-full border-[22px] border-white/10"></div>
                <div class="relative">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-[#c6d5ce]">Kesiapan operasional</p>
                            <h3 class="mt-1 text-xl font-bold">Pantau aktivitas</h3>
                        </div>
                            <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/15">
                            <x-filament::icon icon="heroicon-m-chart-bar-square" class="h-6 w-6" />
                        </span>
                    </div>

                    <div class="mt-8 space-y-5">
                        @foreach ([
                            ['label' => 'Booking perlu diproses', 'value' => $stats[1]['value'], 'icon' => 'heroicon-m-clock'],
                            ['label' => 'Voyage aktif', 'value' => $stats[2]['value'], 'icon' => 'heroicon-m-map'],
                            ['label' => 'Manifest menunggu verifikasi', 'value' => $stats[3]['value'], 'icon' => 'heroicon-m-document-check'],
                        ] as $activity)
                            <div>
                                <div class="mb-2 flex items-center justify-between gap-3 text-sm">
                                    <span class="flex min-w-0 items-center gap-2 text-[#e1e8e3]">
                                        <x-filament::icon :icon="$activity['icon']" class="h-4 w-4 shrink-0 text-[#c3b08f]" />
                                        <span class="truncate">{{ $activity['label'] }}</span>
                                    </span>
                                    <span class="shrink-0 font-bold">{{ number_format($activity['value'], 0, ',', '.') }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-black/15">
                                    <div class="h-full rounded-full bg-[#c3a37a]" style="width: {{ min(100, $activity['value'] * 12) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <a href="{{ route('filament.admin.resources.bookings.index') }}" class="mt-8 inline-flex items-center gap-2 rounded-xl bg-[#e7e0d5] px-4 py-2.5 text-sm font-semibold text-[#263f42] transition hover:bg-white">
                        Lihat aktivitas
                        <x-filament::icon icon="heroicon-m-arrow-right" class="h-4 w-4" />
                    </a>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm xl:col-span-2">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-5 sm:px-6">
                    <div>
                        <h3 class="text-base font-semibold text-gray-950">Booking terbaru</h3>
                        <p class="mt-1 text-sm text-gray-500">Permintaan muatan yang terakhir dicatat.</p>
                    </div>
                    <a href="{{ route('filament.admin.resources.bookings.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-amber-700 transition hover:text-amber-800">
                        Semua booking
                        <x-filament::icon icon="heroicon-m-arrow-right" class="h-4 w-4" />
                    </a>
                </div>

                @if ($latestBookings->isEmpty())
                    <div class="px-6 py-12 text-center">
                        <span class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-100 text-gray-500">
                            <x-filament::icon icon="heroicon-m-inbox" class="h-6 w-6" />
                        </span>
                        <h4 class="mt-4 text-sm font-semibold text-gray-900">Belum ada booking</h4>
                        <p class="mt-1 text-sm text-gray-500">Booking yang masuk akan ditampilkan di sini.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-left text-sm">
                            <thead class="bg-gray-50/80 text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-5 py-3 font-semibold sm:px-6">Booking</th>
                                    <th class="px-5 py-3 font-semibold">Rute</th>
                                    <th class="px-5 py-3 font-semibold">Tanggal</th>
                                    <th class="px-5 py-3 font-semibold">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($latestBookings as $booking)
                                    @php
                                        $statusClass = match ($booking->status) {
                                            'menunggu' => 'bg-amber-50 text-amber-700 ring-amber-600/15',
                                            'diproses', 'manifest', 'siap_dimuat' => 'bg-sky-50 text-sky-700 ring-sky-600/15',
                                            'dimuat', 'dalam_perjalanan', 'diterima' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/15',
                                            'ditolak', 'dibatalkan' => 'bg-rose-50 text-rose-700 ring-rose-600/15',
                                            default => 'bg-gray-100 text-gray-700 ring-gray-600/10',
                                        };
                                    @endphp
                                    <tr class="transition hover:bg-gray-50/80">
                                        <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                                            <p class="font-semibold text-gray-900">{{ $booking->kode_booking }}</p>
                                            <p class="mt-1 text-xs text-gray-500">{{ $booking->pelanggan?->nama ?? 'Pelanggan umum' }}</p>
                                        </td>
                                        <td class="min-w-44 px-5 py-4">
                                            <p class="font-medium text-gray-800">{{ $booking->asal }}</p>
                                            <p class="mt-1 text-xs text-gray-500">ke {{ $booking->tujuan }}</p>
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-4 text-gray-600">{{ $booking->tgl_booking?->format('d M Y') ?? '—' }}</td>
                                        <td class="whitespace-nowrap px-5 py-4">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusClass }}">
                                                {{ str($booking->status)->replace('_', ' ')->title() }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-5">
                    <div>
                        <h3 class="text-base font-semibold text-gray-950">Jadwal voyage</h3>
                        <p class="mt-1 text-sm text-gray-500">Voyage aktif dan yang akan berangkat.</p>
                    </div>
                    <a href="{{ route('filament.admin.resources.voyages.index') }}" aria-label="Lihat semua voyage" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-900">
                        <x-filament::icon icon="heroicon-m-arrow-up-right" class="h-4 w-4" />
                    </a>
                </div>

                @forelse ($upcomingVoyages as $voyage)
                    <div class="flex gap-4 border-b border-gray-100 px-5 py-4 last:border-b-0">
                        <div class="flex h-11 w-11 shrink-0 flex-col items-center justify-center rounded-xl bg-amber-50 text-amber-800">
                            <span class="text-[10px] font-bold uppercase leading-none">{{ $voyage->tgl_berangkat?->format('M') ?? '—' }}</span>
                            <span class="mt-1 text-base font-bold leading-none">{{ $voyage->tgl_berangkat?->format('d') ?? '—' }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ $voyage->nomor_voyage }}</p>
                            <p class="mt-1 truncate text-xs text-gray-500">{{ $voyage->kapal?->nama_kapal ?? 'Kapal belum dipilih' }}</p>
                            <p class="mt-2 truncate text-xs text-gray-600">{{ $voyage->rute }}</p>
                        </div>
                        <span class="h-fit rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-semibold capitalize text-emerald-700">
                            {{ str($voyage->status)->replace('_', ' ') }}
                        </span>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center">
                        <span class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-100 text-gray-500">
                            <x-filament::icon icon="heroicon-m-map" class="h-6 w-6" />
                        </span>
                        <h4 class="mt-4 text-sm font-semibold text-gray-900">Belum ada jadwal aktif</h4>
                        <p class="mt-1 text-sm text-gray-500">Voyage terjadwal akan muncul di sini.</p>
                        <a href="{{ route('filament.admin.resources.voyages.index') }}" class="mt-4 inline-flex text-sm font-semibold text-amber-700 hover:text-amber-800">Kelola jadwal</a>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-filament-panels::page>
