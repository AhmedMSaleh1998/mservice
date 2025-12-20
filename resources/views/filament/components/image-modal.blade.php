<div class="flex justify-center items-center p-4">
    <div class="max-w-4xl w-full">
        @if(isset($title))
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">{{ $title }}</h3>
        @endif
        <div class="bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden">
            <img 
                src="{{ $imageUrl }}" 
                alt="{{ $title ?? 'Blog Image' }}"
                class="w-full h-auto object-contain max-h-[70vh]"
                loading="lazy"
            />
        </div>
    </div>
</div>

