@php
    use Illuminate\Support\Str;

    $documents = $getState() ?? [];
    $documents = array_filter($documents, fn ($value) => ! empty($value));

    $admin = auth('admin')->user();
    $canViewLicenseDocument = $admin
        && method_exists($admin, 'hasRole')
        && ($admin->hasRole('super_admin') || $admin->hasRole('review-supervisor'));

    if (! $canViewLicenseDocument) {
        unset($documents['license_image']);
    }

    $documentTypes = [];
    $record = $getRecord();
    if ($record && method_exists($record, 'getDocumentTypes')) {
        $documentTypes = $record->getDocumentTypes();
    }
@endphp

<div class="space-y-4">
    @forelse($documents as $key => $path)
        @php
            $label = $documentTypes[$key]
                ?? Str::of($key)->replace('_', ' ')->title()->toString();
        @endphp
        <div class="border rounded-lg p-4 bg-white dark:bg-gray-800">
            <h4 class="font-medium text-gray-900 dark:text-white mb-2">{{ __($label) }}</h4>
            <img
                    src="{{ Storage::disk('public')->url($path) }}"
                    alt="{{ __($label) }}"
                    class="max-w-md rounded border cursor-pointer hover:shadow-lg transition"
                    loading="lazy"
                    onclick="window.open(this.src, '_blank')"
            />
            <div class="mt-2 flex gap-2">
                <a href="{{ Storage::disk('public')->url($path) }}"
                   target="_blank"
                   class="text-blue-600 hover:underline text-sm">
                    {{ __('View Full Size') }}
                </a>
                <span class="text-gray-400">|</span>
                <a href="{{ Storage::disk('public')->url($path) }}"
                   download
                   class="text-blue-600 hover:underline text-sm">
                    {{ __('Download') }}
                </a>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500">{{ __('No documents uploaded.') }}</p>
    @endforelse
</div>
