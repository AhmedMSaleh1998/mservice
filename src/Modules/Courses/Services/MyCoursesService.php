<?php

namespace Modules\Courses\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Modules\Core\Models\PaymentMethod;
use Modules\Courses\Models\Course;
use Modules\Courses\Models\CourseBooking;
use Modules\Users\Models\User;

class MyCoursesService
{
    public function listForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $bookings = CourseBooking::query()
            ->where('user_id', $user->id)
            ->where('status', 'paid_successfully')
            ->with(['course', 'order'])
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->get();

        $items = $bookings
            ->map(fn (CourseBooking $courseBooking): array => $this->buildListItem($courseBooking))
            ->filter(fn (array $item): bool => $this->matchesFilters($item, $filters))
            ->values();

        $perPage = max(1, min(50, (int) ($filters['per_page'] ?? 15)));
        $currentPage = max(1, (int) ($filters['page'] ?? 1));
        $total = $items->count();
        $offset = ($currentPage - 1) * $perPage;

        return new LengthAwarePaginator(
            $items->slice($offset, $perPage)->values()->all(),
            $total,
            $perPage,
            $currentPage,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]
        );
    }

    public function detailForBooking(CourseBooking $courseBooking): array
    {
        $courseBooking->loadMissing('course', 'order');
        $paymentMethodLabels = $this->paymentMethodLabels();
        $course = $courseBooking->course;
        $order = $courseBooking->order;

        return [
            'id' => $courseBooking->id,
            'status' => $courseBooking->status,
            'title' => (string) ($course?->title ?? __('Course')),
            'image_url' => $this->resolveImageUrl($course),
            'type' => [
                'key' => $course?->type,
                'label' => $course?->type ? __($course->type) : null,
            ],
            'start_date' => optional($course?->start_date)->format('Y-m-d'),
            'end_date' => optional($course?->end_date)->format('Y-m-d'),
            'description' => (string) ($course?->description ?? ''),
            'price' => $this->formatMoney($courseBooking->total_amount),
            'currency' => (string) ($order?->currency ?? config('checkout.currency', 'EGP')),
            'paid_at' => optional($courseBooking->paid_at ?: $order?->paid_at ?: $courseBooking->created_at)->format('Y-m-d H:i:s'),
            'payment' => [
                'order_id' => $order?->id,
                'amount' => $this->formatMoney($order?->amount ?? $courseBooking->total_amount),
                'currency' => (string) ($order?->currency ?? config('checkout.currency', 'EGP')),
                'paid_at' => optional($order?->paid_at ?: $courseBooking->paid_at)->format('Y-m-d H:i:s'),
                'reference_number' => $order ? $this->resolveReferenceNumber($order) : null,
                'payment_method' => [
                    'key' => $order?->payment_method,
                    'label' => $order?->payment_method
                        ? ($paymentMethodLabels[$order->payment_method] ?? $order->payment_method)
                        : null,
                ],
            ],
        ];
    }

    public function typeFilters(): array
    {
        return [
            [
                'key' => 'all',
                'label' => __('All'),
            ],
            [
                'key' => 'attend',
                'label' => __('attend'),
            ],
            [
                'key' => 'online',
                'label' => __('online'),
            ],
            [
                'key' => 'hybrid',
                'label' => __('hybrid'),
            ],
        ];
    }

    private function buildListItem(CourseBooking $courseBooking): array
    {
        $courseBooking->loadMissing('course', 'order');
        $course = $courseBooking->course;
        $order = $courseBooking->order;

        return [
            'id' => $courseBooking->id,
            'course_id' => $course?->id,
            'title' => (string) ($course?->title ?? __('Course')),
            'image_url' => $this->resolveImageUrl($course),
            'type' => [
                'key' => $course?->type,
                'label' => $course?->type ? __($course->type) : null,
            ],
            'start_date' => optional($course?->start_date)->format('Y-m-d'),
            'end_date' => optional($course?->end_date)->format('Y-m-d'),
            'price' => $this->formatMoney($courseBooking->total_amount),
            'currency' => (string) ($order?->currency ?? config('checkout.currency', 'EGP')),
            'paid_at' => optional($courseBooking->paid_at ?: $order?->paid_at ?: $courseBooking->created_at)->format('Y-m-d H:i:s'),
        ];
    }

    private function matchesFilters(array $item, array $filters): bool
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $haystack = Str::lower(implode(' ', array_filter([
                $item['title'] ?? '',
                $item['type']['label'] ?? '',
                $item['type']['key'] ?? '',
            ])));

            if (! str_contains($haystack, Str::lower($search))) {
                return false;
            }
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '' && $type !== 'all' && ($item['type']['key'] ?? null) !== $type) {
            return false;
        }

        return true;
    }

    private function paymentMethodLabels(): array
    {
        return PaymentMethod::query()
            ->get()
            ->mapWithKeys(fn (PaymentMethod $paymentMethod): array => [
                $paymentMethod->key => (string) $paymentMethod->name,
            ])
            ->all();
    }

    private function resolveReferenceNumber(object $order): string
    {
        return (string) ($order->gateway_reference ?: $order->merchant_ref_num ?: $order->id);
    }

    private function resolveImageUrl(?Course $course): ?string
    {
        return $course?->getFirstMedia('image')?->getUrl();
    }

    private function formatMoney(float|int|string|null $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
