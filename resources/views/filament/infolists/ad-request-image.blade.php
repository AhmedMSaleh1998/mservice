@php
    $record = $getRecord();
    $media = $record?->getFirstMedia('design_image');
    $url = $media?->getUrl();
@endphp

<div class="space-y-4">
    @if($url)
        <div class="border rounded-lg p-4 bg-white dark:bg-gray-800">
            <img
                src="{{ $url }}"
                alt="{{ __('Design Image') }}"
                class="max-w-md rounded border cursor-pointer hover:shadow-lg transition"
                loading="lazy"
                onclick="window.open(this.src, '_blank')"
            />
            <div class="mt-2 flex gap-2">
                <a href="{{ $url }}"
                   target="_blank"
                   class="text-blue-600 hover:underline text-sm">
                    {{ __('View Full Size') }}
                </a>
                <span class="text-gray-400">|</span>
                <a href="{{ $url }}"
                   download
                   class="text-blue-600 hover:underline text-sm">
                    {{ __('Download') }}
                </a>
            </div>
        </div>
    @else
        <p class="text-sm text-gray-500">{{ __('No image uploaded.') }}</p>
    @endif
</div>
