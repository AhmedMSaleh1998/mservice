<?php

namespace Modules\Courses\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\CustomModel;
use Modules\Courses\Builders\CourseQueryBuilder;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

class Course extends CustomModel implements HasMedia
{
    use InteractsWithMedia, SoftDeletes, HasTranslations;

    protected $fillable = ['title', 'description', 'start_date', 'end_date', 'price', 'available_count', 'type', 'is_active', 'is_featured'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'available_count' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];
    public $translatable = ['title', 'description'];

    public function newEloquentBuilder($query): CourseQueryBuilder
    {
        return new CourseQueryBuilder($query);
    }
}
