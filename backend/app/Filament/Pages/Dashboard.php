<?php

namespace App\Filament\Pages;

use App\Models\Booking;
use App\Models\Kapal;
use App\Models\Manifest;
use App\Models\Voyage;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string $view = 'filament.pages.dashboard';

    protected static ?string $navigationIcon = 'heroicon-m-home';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Dashboard Admin';

    protected function getViewData(): array
    {
        $activeVoyageStatuses = ['dijadwalkan', 'direncanakan', 'berlayar', 'sandar'];
        $bookingCounts = Booking::query()
            ->whereBetween('tgl_booking', [today()->subDays(6)->toDateString(), today()->toDateString()])
            ->selectRaw('DATE(tgl_booking) as booking_date, COUNT(*) as total')
            ->groupByRaw('DATE(tgl_booking)')
            ->pluck('total', 'booking_date');

        $bookingTrend = collect(range(6, 0))
            ->map(function (int $daysAgo) use ($bookingCounts): array {
                $date = today()->subDays($daysAgo);

                return [
                    'label' => $date->translatedFormat('D'),
                    'date' => $date->format('d M'),
                    'total' => (int) ($bookingCounts[$date->toDateString()] ?? 0),
                ];
            })
            ->values();
        $maxBookingCount = max(1, (int) $bookingTrend->max('total'));
        $bookingTrend = $bookingTrend->map(function (array $point, int $index) use ($maxBookingCount): array {

                return [
                    ...$point,
                    'x' => 30 + ($index * 63),
                    'y' => 145 - ((int) $point['total'] / $maxBookingCount * 105),
                ];
            });

        return [
            'stats' => [
                [
                    'label' => 'Booking hari ini',
                    'value' => Booking::query()->whereDate('tgl_booking', today())->count(),
                    'description' => 'Permintaan muatan masuk',
                    'icon' => 'heroicon-m-clipboard-document-list',
                    'tone' => 'amber',
                    'url' => route('filament.admin.resources.bookings.index'),
                ],
                [
                    'label' => 'Perlu diproses',
                    'value' => Booking::query()->whereIn('status', ['menunggu', 'diproses'])->count(),
                    'description' => 'Booking menunggu tindak lanjut',
                    'icon' => 'heroicon-m-clock',
                    'tone' => 'blue',
                    'url' => route('filament.admin.resources.bookings.index'),
                ],
                [
                    'label' => 'Voyage aktif',
                    'value' => Voyage::query()->whereIn('status', $activeVoyageStatuses)->count(),
                    'description' => 'Jadwal berjalan dan berlayar',
                    'icon' => 'heroicon-m-map',
                    'tone' => 'emerald',
                    'url' => route('filament.admin.resources.voyages.index'),
                ],
                [
                    'label' => 'Menunggu verifikasi',
                    'value' => Manifest::query()->where('status', 'menunggu_verifikasi')->count(),
                    'description' => 'Manifest perlu ditinjau',
                    'icon' => 'heroicon-m-document-check',
                    'tone' => 'violet',
                    'url' => route('filament.admin.resources.manifests.index'),
                ],
            ],
            'bookingTrend' => $bookingTrend,
            'totalShips' => Kapal::query()->count(),
            'latestBookings' => Booking::query()
                ->with('pelanggan:id_pelanggan,nama')
                ->latest('created_at')
                ->limit(6)
                ->get(),
            'upcomingVoyages' => Voyage::query()
                ->with('kapal:id_kapal,nama_kapal')
                ->whereIn('status', $activeVoyageStatuses)
                ->orderBy('tgl_berangkat')
                ->limit(4)
                ->get(),
        ];
    }
}
