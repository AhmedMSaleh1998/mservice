<?php

namespace App\Filament\Resources\Blogs\Pages;

use App\Filament\Resources\Blogs\BlogResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBlog extends CreateRecord
{
    protected static string $resource = BlogResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Generate slug from title if not provided
        if (!isset($data['slug']) || $data['slug'] === false || empty($data['slug'])) {
            $data['slug'] = [];
            if (isset($data['title']) && is_array($data['title'])) {
                foreach ($data['title'] as $locale => $title) {
                    if (!empty($title)) {
                        $data['slug'][$locale] = \Illuminate\Support\Str::slug($title);
                    }
                }
            }
        } elseif (!is_array($data['slug'])) {
            // If it's a string, convert to array with default locale
            $data['slug'] = [app()->getLocale() => \Illuminate\Support\Str::slug($data['slug'])];
        } else {
            // Ensure all slug values are properly slugified
            foreach ($data['slug'] as $locale => $slugValue) {
                if (!empty($slugValue)) {
                    $data['slug'][$locale] = \Illuminate\Support\Str::slug($slugValue);
                }
            }
        }

        // Set user_id if not provided
        if (!isset($data['user_id']) || $data['user_id'] === false) {
            $data['user_id'] = auth()->id();
        }

        return $data;
    }


    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
