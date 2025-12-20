@if(isset($imageUrl) && $imageUrl)
<div class="mb-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
        Current Image
    </label>
    <div class="flex items-center space-x-4">
        <img 
            src="{{ $imageUrl }}" 
            alt="{{ $title ?? 'Current Image' }}"
            class="w-24 h-24 object-cover rounded-lg border border-gray-300 dark:border-gray-600"
        />
        <div class="flex-1">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Upload a new image below to replace the current one.
            </p>
        </div>
    </div>
</div>
@endif

