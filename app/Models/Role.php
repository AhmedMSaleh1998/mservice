<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Translatable\HasTranslations;

class Role extends SpatieRole
{
    use HasTranslations;

    protected $fillable = [
        'name',
        'guard_name',
        'translated_name',
    ];

    protected $casts = [
        'translated_name' => 'array',
    ];

    public array $translatable = [
        'translated_name',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $role): void {
            $englishName = static::extractEnglishName($role->translated_name);

            if (blank($englishName)) {
                return;
            }

            $guardName = filled($role->guard_name)
                ? (string) $role->guard_name
                : (string) config('auth.defaults.guard', 'web');

            $role->guard_name = $guardName;
            $role->name = static::makeUniqueSlugFromEnglishName(
                englishName: $englishName,
                guardName: $guardName,
                ignoreId: $role->getKey(),
                teamId: static::resolveTeamId($role),
            );
        });
    }

    public function getDisplayName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $fallbackLocale = (string) config('app.fallback_locale', 'en');

        $displayName = data_get($this->translated_name, $locale)
            ?: data_get($this->translated_name, $fallbackLocale)
            ?: data_get($this->translated_name, 'en');

        return filled($displayName)
            ? (string) $displayName
            : Str::headline($this->name);
    }

    public static function makeUniqueSlugFromEnglishName(
        string $englishName,
        string $guardName,
        int|string|null $ignoreId = null,
        int|string|null $teamId = null,
    ): string {
        $baseSlug = Str::slug($englishName);

        if (blank($baseSlug)) {
            $baseSlug = 'role';
        }

        $query = static::query()->where('guard_name', $guardName);

        if (config('permission.teams', false)) {
            $teamKey = (string) config('permission.column_names.team_foreign_key', 'team_id');

            if (is_null($teamId)) {
                $query->whereNull($teamKey);
            } else {
                $query->where($teamKey, $teamId);
            }
        }

        if (filled($ignoreId)) {
            $query->whereKeyNot($ignoreId);
        }

        $slug = $baseSlug;
        $counter = 2;

        while ((clone $query)->where('name', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    protected static function resolveTeamId(self $role): int|string|null
    {
        if (! config('permission.teams', false)) {
            return null;
        }

        $teamKey = (string) config('permission.column_names.team_foreign_key', 'team_id');

        return $role->getAttribute($teamKey);
    }

    protected static function extractEnglishName(mixed $translatedName): ?string
    {
        if (is_string($translatedName)) {
            $decodedValue = json_decode($translatedName, true);

            if (is_array($decodedValue)) {
                $translatedName = $decodedValue;
            } elseif (filled(trim($translatedName))) {
                return trim($translatedName);
            }
        }

        $englishName = data_get($translatedName, 'en');

        if (! is_string($englishName)) {
            return null;
        }

        $englishName = trim($englishName);

        return filled($englishName) ? $englishName : null;
    }
}
