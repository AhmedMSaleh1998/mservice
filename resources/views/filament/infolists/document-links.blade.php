@php
    $documentTypes = [
        'personal_image' => 'Personal Photo',
        'dob_image' => 'Date of Birth Certificate',
        'graduation_certificate_image' => 'Graduation Certificate',
        'internship_certificate_image' => 'Internship Certificate',
        'practice_exam_result_image' => 'Practice Exam Result',
        'practice_license_form_image' => 'Practice License Form',
        'military_service_status_image' => 'Military Service Status',
        'criminal_record_certificate_image' => 'Criminal Record Certificate',
        'syndicate_registration_form_image' => 'Syndicate Registration Form',
    ];

    $documents = $getState();
@endphp

<div class="space-y-4">
    @foreach($documentTypes as $key => $label)
        @if(isset($documents[$key]) && !empty($documents[$key]))
            <div class="border rounded-lg p-4 bg-white dark:bg-gray-800">
                <h4 class="font-medium text-gray-900 dark:text-white mb-2">{{ $label }}</h4>
                <img
                        src="{{ Storage::disk('public')->url($documents[$key]) }}"
                        alt="{{ $label }}"
                        class="max-w-md rounded border cursor-pointer hover:shadow-lg transition"
                        loading="lazy"
                        onclick="window.open(this.src, '_blank')"
                />
                <div class="mt-2 flex gap-2">
                    <a href="{{ Storage::disk('public')->url($documents[$key]) }}"
                       target="_blank"
                       class="text-blue-600 hover:underline text-sm">
                        View Full Size
                    </a>
                    <span class="text-gray-400">|</span>
                    <a href="{{ Storage::disk('public')->url($documents[$key]) }}"
                       download
                       class="text-blue-600 hover:underline text-sm">
                        Download
                    </a>
                </div>
            </div>
        @endif
    @endforeach
</div>