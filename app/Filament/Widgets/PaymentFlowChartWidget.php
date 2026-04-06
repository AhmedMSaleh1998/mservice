<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Modules\Core\Models\Order;

class PaymentFlowChartWidget extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    public function getHeading(): ?string
    {
        return __('Payment flow');
    }

    public function getDescription(): ?string
    {
        return __('New checkout attempts and successful payments by day.');
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
                    'label' => __('Paid Successfully'),
                    'data' => $this->aggregateOrdersByDate(
                        Order::query()->where('status', 'paid_successfully'),
                        $days,
                        'paid_at',
                    ),
                    'backgroundColor' => 'rgba(5, 150, 105, 0.8)',
                    'borderColor' => '#059669',
                    'borderRadius' => 6,
                ],
                [
                    'label' => __('Pending Payment'),
                    'data' => $this->aggregateOrdersByDate(
                        Order::query()->where('status', 'pending_payment'),
                        $days,
                    ),
                    'backgroundColor' => 'rgba(245, 158, 11, 0.8)',
                    'borderColor' => '#f59e0b',
                    'borderRadius' => 6,
                ],
                [
                    'label' => __('Checkout Pending'),
                    'data' => $this->aggregateOrdersByDate(
                        Order::query()->where('status', 'checkout_pending'),
                        $days,
                    ),
                    'backgroundColor' => 'rgba(37, 99, 235, 0.8)',
                    'borderColor' => '#2563eb',
                    'borderRadius' => 6,
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
        return 'bar';
    }

    /**
     * @return array<int, int>
     */
    private function aggregateOrdersByDate($query, int $days, string $dateColumn = 'created_at'): array
    {
        $start = today()->subDays($days - 1)->startOfDay();
        $end = today()->endOfDay();

        $counts = $query
            ->selectRaw("DATE({$dateColumn}) as date, COUNT(*) as aggregate")
            ->whereBetween($dateColumn, [$start, $end])
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
