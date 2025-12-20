<?php

namespace Modules\Blog\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Blog\Builders\BlogQueryBuilder;
use Modules\Core\Models\CustomModel;
use Modules\Users\Models\User;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

class Blog extends CustomModel implements HasMedia
{
    use SoftDeletes, HasTranslations, InteractsWithMedia;

    protected $fillable = ['user_id', 'title', 'description', 'is_published', 'slug', 'content'];

    public $translatable = ['title', 'description', 'slug', 'content'];

    protected $casts = [
        'is_published' => 'boolean',
        'created_at' => 'datetime:Y-m-d H:i:s', // Custom format
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'published_at' => 'datetime:Y-m-d',
    ];

    public function newEloquentBuilder($query): BlogQueryBuilder
    {
        return new BlogQueryBuilder($query);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
