<?php

namespace App\Filament\Widgets;

use App\Models\RegistrationRequest;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Modules\Certificates\Models\CertificateRequest;
use Modules\Services\Models\RestUnitBooking;
use Modules\Support\Models\SupportTicket;

class OperationsTrendChartWidget extends ChartWidget
{
    protected static ?int $sort = 0;

    protected int | string | array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    public function getHeading(): ?string
    {
        return __('Operations trends');
    }

    public function getDescription(): ?string
    {
        return __('Daily activity for registrations, certificates, bookings, and support.');
    }

    protected function getFilters(): ?array
    {
        return [
            '7' => __('Last 7 days'),
            '14' => __('Last 14 days'),
            '30' => __('Last 30 days'),
        ];
    }

    protected function getData(): array
    {
        $days = (int) ($this->filter ?: 14);
        $days = in_array($days, [7, 14, 30], true) ? $days : 14;

        return [
            'datasets' => [
                [
                    'label' => __('Registration Requests'),
                    'data' => $this->aggregateByDay(RegistrationRequest::class, $days),
                    'borderColor' => '#2563eb',
                    'backgroundColor' => 'rgba(37, 99, 235, 0.12)',
                    'fill' => false,
                    'tension' => 0.35,
                ],
                [
                    'label' => __('Certificate Requests'),
                    'data' => $this->aggregateByDay(CertificateRequest::class, $days),
                    'borderColor' => '#059669',
                    'backgroundColor' => 'rgba(5, 150, 105, 0.12)',
                    'fill' => false,
                    'tension' => 0.35,
                ],
                [
                    'label' => __('Rest Unit Bookings'),
                    'data' => $this->aggregateByDay(RestUnitBooking::class, $days),
                    'borderColor' => '#d97706',
                    'backgroundColor' => 'rgba(217, 119, 6, 0.12)',
                    'fill' => false,
                    'tension' => 0.35,
                ],
                [
                    'label' => __('Support Tickets'),
                    'data' => $this->aggregateByDay(SupportTicket::class, $days),
                    'borderColor' => '#dc2626',
                    'backgroundColor' => 'rgba(220, 38, 38, 0.12)',
                    'fill' => false,
                    'tension' => 0.35,
                ],
            ],
            'labels' => $this->buildLabels($days),
        ];
    }

    protected function getOptions(): ?array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<int, int>
     */
    private function aggregateByDay(string $modelClass, int $days): array
    {
        $start = today()->subDays($days - 1)->startOfDay();
        $end = today()->endOfDay();

        $counts = $modelClass::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as aggregate')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('aggregate', 'date');

        return collect($this->buildDates($days))
            ->map(fn (string $date): int => (int) ($counts[$date] ?? 0))
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function buildLabels(int $days): array
    {
        return collect($this->buildDates($days))
            ->map(fn (string $date): string => Carbon::parse($date)->translatedFormat('j M'))
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function buildDates(int $days): array
    {
        $dates = [];
        $cursor = today()->subDays($days - 1);

        for ($index = 0; $index < $days; $index++) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $dates;
    }
}
