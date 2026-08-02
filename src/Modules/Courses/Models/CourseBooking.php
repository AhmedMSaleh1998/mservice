<?php

namespace Modules\Courses\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Modules\Core\Models\CustomModel;
use Modules\Core\Models\Order;
use Modules\Courses\Builders\CourseBookingQueryBuilder;
use Modules\Users\Models\User;

class CourseBooking extends CustomModel
{
    protected $fillable = [
        'user_id',
        'course_id',
        'price',
        'total_amount',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function newEloquentBuilder($query): CourseBookingQueryBuilder
    {
        return new CourseBookingQueryBuilder($query);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function order(): MorphOne
    {
        return $this->morphOne(Order::class, 'orderable');
    }
}
