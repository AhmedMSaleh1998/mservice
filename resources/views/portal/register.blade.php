@php
    $locale = in_array(request('lang'), ['ar', 'en'], true) ? request('lang') : 'ar';
    app()->setLocale($locale);
    $isArabic = $locale === 'ar';
    $countryCodes = \App\Support\CountryCodeOptions::options();
    $mode = $mode ?? 'create';
    $isEditMode = $mode === 'edit';
    $prefillData = $prefillData ?? [];
    $missingDocumentKeys = array_values($missingDocumentKeys ?? []);
    $existingDocumentKeys = array_values($existingDocumentKeys ?? []);
    $documentLabels = $documentLabels ?? \App\Support\RegistrationRequestDocuments::requiredDocuments();
    $submitUrl = $submitUrl ?? url('/api/v1/register-request');
    $registrationPortalText = $isArabic ? 'بوابة التسجيل' : __('Registration Portal');
    $retrieveDocumentsText = $isArabic ? 'استرجاع المستندات' : __('Retrieve Documents');
    $personalInformationText = $isArabic ? 'البيانات الشخصية' : __('Personal Information');
    $residenceInformationText = $isArabic ? 'بيانات مقر الإقامة' : __('Residence Information');
    $academicInformationText = $isArabic ? 'البيانات الأكاديمية' : __('Academic Information');
    $submittedDocumentsText = $isArabic ? 'المستندات المقدمة' : __('Submitted Documents');
    $smartSummaryText = $isArabic ? 'ملخص ذكي' : __('Smart Summary');
    $pageTitle = $isEditMode
        ? ($isArabic ? 'استكمال طلب التسجيل' : __('Update Registration Request'))
        : ($isArabic ? 'طلب التسجيل' : __('Registration Request'));
    $heroTitle = $isEditMode
        ? ($isArabic ? 'راجع بيانات الطلب وارفع المستندات الناقصة.' : __('Review your request data and upload the missing documents.'))
        : ($isArabic ? 'استكمل الخطوات التالية لإرسال طلب التسجيل.' : __('Complete the steps below to submit your request.'));
    $summaryFootText = $isEditMode
        ? ($isArabic ? 'راجع بياناتك المحفوظة وارفع أي مستندات ناقصة قبل إعادة الإرسال.' : __('Review your saved data and upload any missing documents before resubmitting.'))
        : ($isArabic ? 'سيتم مسح البيانات عند تحديث الصفحة.' : __('Data will be cleared on refresh.'));
    $selectFileText = $isArabic ? 'اختر ملفًا' : __('Select file');
    $replaceFileText = $isArabic ? 'تم رفعه مسبقًا، اختر ملفًا فقط إذا أردت استبداله.' : __('Already uploaded - choose a file only if you want to replace it.');
    $alreadyUploadedText = $isArabic ? 'تم رفعه مسبقًا' : __('Already uploaded');
    $missingFileText = $isArabic ? 'ناقص، يرجى رفع هذا الملف مرة أخرى.' : __('Missing, please upload this file again.');
    $missingNoticeTitle = $isArabic ? 'تنبيه بخصوص المستندات الناقصة' : __('Missing documents notice');
    $missingNoticeBody = $isArabic
        ? 'تم فتح هذه الصفحة من رابط آمن أُرسل إلى بريدك الإلكتروني حتى تتمكن من استكمال المستندات الناقصة وتحديث بيانات الطلب.'
        : __('This form was opened from a secure email link so you can complete the missing documents and update your saved request data.');
    $birthHintText = $isArabic ? 'يتم تعبئة تاريخ الميلاد تلقائيًا للجنسية المصرية فقط.' : __('Auto-filled for Egyptian nationality only.');
    $mobilePlaceholderText = $isArabic ? 'أدخل رقم موبايل مكونًا من 11 رقمًا.' : __('Enter 11-digit mobile number.');
    $uploadHintText = $isArabic ? 'ارفع صورًا واضحة بحد أقصى 5 ميجابايت.' : __('Upload clear images (max 5MB).');
    $declarationTitleText = $isArabic ? 'إقرار صحة البيانات والمسؤولية القانونية' : __('Data Accuracy Declaration and Legal Responsibility');
    $declarationParagraphOneText = $isArabic
        ? 'أقر أنا مقدم الطلب بأن جميع البيانات والمستندات التي أدخلتها ورفعتها من خلال الموقع صحيحة وسارية ومطابقة للحقيقة، وقد قمت بمراجعتها بعناية قبل الإرسال.'
        : __('I, the applicant, acknowledge that all data and documents I entered and uploaded through the website are accurate, valid, and true, and that I reviewed them carefully before submission.');
    $declarationParagraphTwoText = $isArabic
        ? 'وأتحمل كامل المسؤولية القانونية عن صحة هذه البيانات والمستندات، وألتزم بإخطار النقابة فورًا إذا تبين وجود أي خطأ أو لزم إجراء أي تعديل.'
        : __('I bear full legal responsibility for the accuracy of this data and these documents, and I undertake to notify the syndicate immediately if any error is discovered or any amendment is required.');
    $declarationParagraphThreeText = $isArabic
        ? 'وأوافق على أنه إذا ثبت عدم صحة أي بيانات أو مستندات مقدمة، يكون للنقابة الحق في اتخاذ ما تراه من إجراءات قانونية وإدارية وفقًا للوائح والقوانين المعمول بها.'
        : __('I agree that if any submitted data or documents are proven incorrect, the syndicate has the right to take any legal and administrative actions in accordance with applicable laws and regulations.');
    $declarationAgreeText = $isArabic
        ? 'أوافق على هذا الإقرار وأتحمل المسؤولية القانونية عن صحة البيانات.'
        : __('I agree to the declaration of data accuracy and bear legal responsibility for it.');
    $backButtonText = $isArabic ? 'السابق' : __('Back');
    $nextButtonText = $isArabic ? 'التالي' : __('Next');
    $submitButtonText = $isEditMode
        ? ($isArabic ? 'تحديث الطلب' : __('Update Registration'))
        : ($isArabic ? 'إرسال الطلب' : __('Submit Registration'));
    $sendingButtonText = $isArabic ? 'جارٍ الإرسال...' : __('Sending...');
    $fixFieldsText = $isArabic ? 'يرجى مراجعة الحقول المظللة.' : __('Please fix the highlighted fields.');
    $selectRequiredText = $isArabic ? 'يرجى اختيار قيمة.' : __('Please select a value.');
    $selectedForUploadText = $isArabic ? 'تم اختيار الملف للرفع' : __('Selected for upload');
    $getDocumentState = static function (string $key) use ($existingDocumentKeys, $missingDocumentKeys, $isEditMode, $replaceFileText, $selectFileText, $alreadyUploadedText, $missingFileText): array {
        $hasExisting = in_array($key, $existingDocumentKeys, true);
        $isMissing = in_array($key, $missingDocumentKeys, true);

        return [
            'required' => ! $isEditMode || $isMissing,
            'defaultLabel' => $hasExisting
                ? $replaceFileText
                : $selectFileText,
            'status' => $isEditMode
                ? ($hasExisting ? $alreadyUploadedText : $missingFileText)
                : '',
            'statusClass' => $hasExisting ? 'existing' : ($isEditMode ? 'missing' : ''),
        ];
    };
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&family=Space+Grotesk:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: light;
            --ink: #0b1c1f;
            --muted: #54646a;
            --soft: #8ea2a8;
            --primary: #1f8f6d;
            --primary-strong: #157356;
            --accent: #ff7a59;
            --bg: #f5f7f7;
            --panel: rgba(255, 255, 255, 0.92);
            --panel-strong: #ffffff;
            --border: rgba(15, 23, 42, 0.14);
            --shadow: 0 30px 60px rgba(15, 23, 42, 0.12);
            --radius: 18px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Cairo", "Space Grotesk", sans-serif;
            background: radial-gradient(circle at top left, #e8f5f0 0%, transparent 55%),
                radial-gradient(circle at 80% 10%, #fff0e6 0%, transparent 50%),
                var(--bg);
            color: var(--ink);
            min-height: 100vh;
        }

        html[lang="en"] body {
            font-family: "Space Grotesk", "Cairo", sans-serif;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='140' height='140' viewBox='0 0 140 140'><g fill='none' stroke='%23e1e7ea' stroke-width='1.2' opacity='0.55'><path d='M22 18v10M17 23h10'/><circle cx='94' cy='30' r='9'/><path d='M90 30h8M94 26v8'/><path d='M36 88c8-10 18-10 26 0 2 2 4 4 8 4 6 0 10-4 10-10s-4-10-10-10c-6 0-10 4-10 10 0 6 4 10 10 10'/><path d='M96 96v12M90 102h12'/><path d='M20 112h18'/><path d='M112 116c8 0 12-6 12-12s-4-12-12-12c-6 0-10 4-10 10 0 8 6 14 14 14'/></g></svg>");
            opacity: 0.45;
            pointer-events: none;
            z-index: 0;
        }

        .page {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            gap: 32px;
            padding: 28px clamp(20px, 4vw, 60px) 48px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-mark {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #ffffff;
            border: 1px solid rgba(15, 23, 42, 0.12);
            display: grid;
            place-items: center;
            position: relative;
            overflow: hidden;
            padding: 6px;
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.12);
        }

        .brand-logo {
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter: drop-shadow(0 6px 10px rgba(15, 23, 42, 0.1));
        }

        .brand-badge {
            position: absolute;
            inset: 8px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), #54c59c);
            display: none;
            place-items: center;
            color: #fff;
            font-weight: 700;
            box-shadow: 0 15px 30px rgba(31, 143, 109, 0.3);
        }

        .brand h1 {
            font-size: clamp(20px, 3vw, 28px);
            margin: 0;
        }

        .brand p {
            margin: 2px 0 0;
            color: var(--muted);
            font-size: 14px;
        }

        .lang-toggle {
            display: inline-flex;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 6px;
            gap: 6px;
            box-shadow: var(--shadow);
        }

        .lang-toggle a {
            text-decoration: none;
            color: var(--muted);
            padding: 6px 14px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 13px;
        }

        .lang-toggle a.active {
            background: var(--ink);
            color: #fff;
        }

        .topbar-actions {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .topbar-actions a {
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 8px 14px;
            border-radius: 999px;
            border: 1px solid rgba(15, 23, 42, 0.18);
            background: rgba(255, 255, 255, 0.95);
            color: var(--ink);
            font-size: 13px;
            font-weight: 700;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
        }

        .topbar-actions a:hover {
            border-color: rgba(31, 143, 109, 0.45);
            color: var(--primary-strong);
        }

        .layout {
            display: grid;
            grid-template-columns: minmax(240px, 320px) minmax(0, 1fr);
            grid-template-areas: "side form";
            gap: 26px;
            align-items: start;
        }

        .card {
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(15, 23, 42, 0.1);
            border-radius: var(--radius);
            padding: 22px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(14px);
            position: relative;
            overflow: hidden;
        }

        .form-card {
            grid-area: form;
            padding: 28px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(255, 255, 255, 0.93) 100%);
            box-shadow: 0 28px 70px rgba(15, 23, 42, 0.12);
        }

        .form-card::before {
            content: "";
            position: absolute;
            inset: 0 0 auto 0;
            height: 4px;
            background: linear-gradient(120deg, var(--primary), #6ad2b1, var(--accent));
            opacity: 0.9;
            z-index: 2;
            pointer-events: none;
        }

        .card::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(140deg, rgba(31, 143, 109, 0.08), transparent 45%, rgba(255, 122, 89, 0.08));
            opacity: 0.6;
            pointer-events: none;
            z-index: 0;
        }

        .card > * {
            position: relative;
            z-index: 1;
        }

        .side-card {
            grid-area: side;
            position: sticky;
            top: 24px;
            align-self: start;
        }

        .hero {
            display: grid;
            gap: 12px;
            padding-bottom: 16px;
            border-bottom: 1px dashed rgba(15, 23, 42, 0.12);
        }

        .hero .pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(31, 143, 109, 0.1);
            color: var(--primary);
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            width: fit-content;
        }

        .hero h2 {
            margin: 0;
            font-size: clamp(22px, 2.6vw, 30px);
        }

        .hero p {
            margin: 0;
            color: var(--muted);
            line-height: 1.7;
        }

        .progress {
            margin-top: 20px;
        }

        .progress-track {
            height: 10px;
            background: rgba(15, 23, 42, 0.08);
            border-radius: 999px;
            overflow: hidden;
        }

        .progress-fill {
            display: block;
            height: 100%;
            width: 0;
            background: linear-gradient(120deg, var(--primary), #62d3aa);
            border-radius: inherit;
            transition: width 0.4s ease;
        }

        .step-list {
            display: grid;
            gap: 12px;
            margin-top: 16px;
            padding: 12px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.65);
            border: 1px solid rgba(15, 23, 42, 0.08);
        }

        .step-chip {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 14px;
            padding: 10px 12px;
            color: var(--muted);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .step-chip.active {
            background: #fff;
            color: var(--ink);
            border-color: rgba(31, 143, 109, 0.2);
            box-shadow: 0 12px 20px rgba(31, 143, 109, 0.12);
        }

        .step-chip.done {
            color: var(--primary-strong);
            border-color: rgba(31, 143, 109, 0.2);
            background: rgba(31, 143, 109, 0.08);
        }

        .summary {
            margin-top: 18px;
            display: grid;
            gap: 14px;
            padding: 14px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.68);
            border: 1px solid rgba(15, 23, 42, 0.08);
        }

        .summary-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .summary-header h3 {
            margin: 0;
            font-size: 16px;
        }

        .summary-grid {
            display: grid;
            gap: 10px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 13px;
            color: var(--muted);
        }

        .summary-item strong {
            color: var(--ink);
            font-weight: 600;
            text-align: end;
        }

        .summary-foot {
            font-size: 12px;
            color: var(--soft);
        }

        form {
            display: grid;
            gap: 22px;
        }

        .form-step {
            display: none;
        }

        .form-step.active {
            display: grid;
            gap: 18px;
        }

        .step-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .step-title h2 {
            margin: 0;
            font-size: 20px;
        }

        .step-title span {
            color: var(--soft);
            font-size: 13px;
            font-weight: 600;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .grid-two {
            grid-template-columns: repeat(2, minmax(220px, 1fr));
        }

        .name-grid {
            margin-bottom: 16px;
        }

        .field {
            display: grid;
            gap: 6px;
        }

        .field label {
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
        }

        .field input,
        .field select,
        .field textarea {
            width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid rgba(15, 23, 42, 0.14);
            background: rgba(255, 255, 255, 0.92);
            font-size: 14px;
            font-family: inherit;
            box-shadow: 0 1px 0 rgba(15, 23, 42, 0.03);
            min-height: 46px;
            line-height: 1.4;
        }

        .field input,
        .field select,
        .select-input {
            height: 46px;
        }

        .field select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: none;
        }

        .field input[type="date"] {
            appearance: none;
            -webkit-appearance: none;
            padding: 12px 14px;
            line-height: 1.4;
            padding-inline-end: 42px;
        }

        .field input[type="date"]::-webkit-calendar-picker-indicator {
            cursor: pointer;
            opacity: 0.75;
        }

        .field input[type="date"]::-webkit-datetime-edit,
        .field input[type="date"]::-webkit-datetime-edit-fields-wrapper,
        .field input[type="date"]::-webkit-datetime-edit-text,
        .field input[type="date"]::-webkit-datetime-edit-month-field,
        .field input[type="date"]::-webkit-datetime-edit-day-field,
        .field input[type="date"]::-webkit-datetime-edit-year-field {
            padding: 0;
            line-height: 1.4;
        }

        .field-pair {
            display: grid;
            grid-template-columns: minmax(110px, 0.35fr) minmax(0, 1fr);
            gap: 12px;
            align-items: start;
        }

        .field-pair .field {
            margin: 0;
        }

        .combo-pair {
            gap: 0;
        }

        .combo-pair .field:first-child input,
        .combo-pair .field:first-child select,
        .combo-pair .field:first-child .select-input {
            border-start-end-radius: 0;
            border-end-end-radius: 0;
            border-inline-end: 0;
        }

        .combo-pair .field:last-child input,
        .combo-pair .field:last-child select,
        .combo-pair .field:last-child .select-input {
            border-start-start-radius: 0;
            border-end-start-radius: 0;
        }

        .combo-pair .field input:focus,
        .combo-pair .field select:focus,
        .combo-pair .select-input:focus {
            position: relative;
            z-index: 2;
        }

        .select-hidden {
            position: absolute !important;
            opacity: 0 !important;
            pointer-events: none !important;
            height: 0 !important;
            width: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            border: 0 !important;
        }

        .select-shell {
            position: relative;
            width: 100%;
        }

        .select-input {
            width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid rgba(15, 23, 42, 0.14);
            background: rgba(255, 255, 255, 0.92);
            font-size: 14px;
            font-family: inherit;
            box-shadow: 0 1px 0 rgba(15, 23, 42, 0.03);
            min-height: 46px;
            line-height: 1.4;
        }

        .select-input:focus {
            outline: none;
            border-color: rgba(31, 143, 109, 0.6);
            box-shadow: 0 0 0 3px rgba(31, 143, 109, 0.15);
        }

        .select-shell.open .select-input {
            border-bottom-left-radius: 6px;
            border-bottom-right-radius: 6px;
        }

        .select-panel {
            position: absolute;
            top: calc(100% + 6px);
            inset-inline: 0;
            background: #fff;
            border: 1px solid rgba(15, 23, 42, 0.12);
            border-radius: 12px;
            box-shadow: 0 16px 36px rgba(15, 23, 42, 0.12);
            max-height: 240px;
            overflow: auto;
            display: none;
            z-index: 30;
        }

        .select-shell.open .select-panel {
            display: block;
        }

        .select-shell.is-locked .select-input {
            background: rgba(148, 163, 184, 0.12);
            color: var(--muted);
            cursor: not-allowed;
        }

        .select-option {
            width: 100%;
            text-align: start;
            padding: 10px 12px;
            background: transparent;
            border: none;
            font-size: 14px;
            cursor: pointer;
            color: var(--ink);
        }

        .select-option:hover,
        .select-option:focus {
            background: rgba(31, 143, 109, 0.08);
            outline: none;
        }

        .select-option.is-selected {
            background: rgba(31, 143, 109, 0.12);
            color: var(--primary-strong);
            font-weight: 600;
        }

        .select-option.is-hidden {
            display: none;
        }

        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            outline: none;
            border-color: rgba(31, 143, 109, 0.6);
            box-shadow: 0 0 0 3px rgba(31, 143, 109, 0.15);
        }

        .field .hint {
            font-size: 12px;
            color: var(--soft);
        }

        .hint {
            font-size: 12px;
            color: var(--soft);
        }

        .field .error {
            font-size: 12px;
            color: #c23d3d;
            min-height: 14px;
        }

        .field-hint-float {
            position: relative;
        }

        .field-hint-float .hint {
            position: absolute;
            inset-inline-start: 0;
            top: calc(100% + 6px);
            bottom: auto;
            max-width: 100%;
            pointer-events: none;
        }

        .field input.is-invalid,
        .field select.is-invalid,
        .select-input.is-invalid {
            border-color: #c23d3d;
            box-shadow: 0 0 0 3px rgba(194, 61, 61, 0.12);
        }

        .span-2 {
            grid-column: span 2;
        }

        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }

        .file-card {
            border: 1px dashed rgba(15, 23, 42, 0.2);
            border-radius: 14px;
            padding: 14px;
            display: grid;
            gap: 10px;
            position: relative;
            cursor: pointer;
            background: rgba(255, 255, 255, 0.6);
            min-height: 140px;
            transition: border-color 0.2s ease, transform 0.2s ease;
        }

        .file-card:hover {
            border-color: rgba(31, 143, 109, 0.5);
            transform: translateY(-2px);
        }

        .file-card input[type="file"] {
            display: none;
        }

        .file-card strong {
            font-size: 13px;
            color: var(--ink);
        }

        .file-card span {
            font-size: 12px;
            color: var(--soft);
        }

        .file-status {
            display: block;
            font-size: 12px;
            font-weight: 700;
        }

        .file-status.existing {
            color: var(--primary-strong);
        }

        .file-status.missing {
            color: #b45309;
        }

        .file-card img {
            width: 100%;
            max-height: 80px;
            object-fit: cover;
            border-radius: 10px;
            display: none;
        }

        .file-card img.visible {
            display: block;
        }

        .declaration-field {
            margin-top: 16px;
            border: 1px solid rgba(15, 23, 42, 0.14);
            border-radius: 14px;
            padding: 14px 16px;
            background: rgba(255, 255, 255, 0.86);
        }

        .declaration-field h3 {
            margin: 0 0 10px;
            font-size: 15px;
        }

        .declaration-field p {
            margin: 0 0 8px;
            color: var(--muted);
            line-height: 1.7;
            font-size: 13px;
        }

        .declaration-consent {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-top: 12px;
            font-weight: 700;
            cursor: pointer;
            color: var(--ink);
        }

        .declaration-consent input[type="checkbox"] {
            margin-top: 2px;
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
            cursor: pointer;
        }

        .declaration-consent input[type="checkbox"].is-invalid {
            outline: 2px solid rgba(194, 61, 61, 0.7);
            outline-offset: 2px;
            border-radius: 4px;
        }

        .declaration-field .error {
            margin-top: 8px;
        }

        .actions {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-top: 12px;
        }

        .btn {
            border: none;
            border-radius: 999px;
            padding: 12px 24px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            font-size: 14px;
        }

        .btn.secondary {
            background: transparent;
            color: var(--muted);
            border: 1px solid rgba(15, 23, 42, 0.2);
        }

        .btn.primary {
            background: linear-gradient(120deg, var(--primary), #49c39a);
            color: #fff;
            box-shadow: 0 15px 30px rgba(31, 143, 109, 0.25);
        }

        .btn.primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            box-shadow: none;
        }

        .alert {
            display: none;
            padding: 12px 14px;
            border-radius: 12px;
            background: rgba(255, 122, 89, 0.15);
            color: #9b3d28;
            font-size: 13px;
        }

        .alert.visible {
            display: block;
        }

        .notice-box {
            margin-bottom: 18px;
            padding: 16px 18px;
            border-radius: 14px;
            border: 1px solid rgba(31, 143, 109, 0.22);
            background: rgba(31, 143, 109, 0.09);
        }

        .notice-box h3 {
            margin: 0 0 10px;
            font-size: 15px;
        }

        .notice-box p,
        .notice-box li {
            margin: 0;
            color: var(--muted);
            line-height: 1.8;
            font-size: 13px;
        }

        .notice-box ul {
            margin: 10px 0 0;
            padding-inline-start: 18px;
        }

        .success {
            display: none;
            padding: 16px;
            border-radius: 14px;
            background: rgba(31, 143, 109, 0.15);
            color: var(--primary-strong);
            font-weight: 600;
        }

        .success.visible {
            display: grid;
            gap: 8px;
        }

        .success strong {
            font-size: 16px;
        }

        .success-downloads {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .success-downloads a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            border: 1px solid rgba(21, 115, 86, 0.25);
            background: rgba(255, 255, 255, 0.75);
            color: var(--primary-strong);
        }

        .success-downloads a:hover {
            background: #ffffff;
            border-color: rgba(21, 115, 86, 0.45);
        }

        html[dir="rtl"] .layout {
            grid-template-columns: minmax(240px, 320px) minmax(0, 1fr);
            grid-template-areas: "side form";
        }

        html[dir="rtl"] .field-pair {
            grid-template-columns: minmax(110px, 0.35fr) minmax(0, 1fr);
        }

        @media (max-width: 960px) {
            .layout {
                grid-template-columns: 1fr;
                grid-template-areas: "form" "side";
                justify-items: stretch;
            }

            html[dir="rtl"] .layout {
                grid-template-columns: 1fr;
                grid-template-areas: "form" "side";
            }

            .summary-item strong {
                text-align: start;
            }

            .side-card {
                position: static;
            }

            .page {
                padding: 20px 16px 32px;
            }

            .card,
            .form-card {
                width: 100%;
                max-width: 100%;
            }

            .form-card {
                padding: 22px;
            }
        }

        @media (max-width: 640px) {
            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .topbar-actions {
                width: 100%;
            }

            .topbar-actions a {
                width: 100%;
            }

            .actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .grid-two {
                grid-template-columns: 1fr;
            }

            .lang-toggle {
                width: 100%;
                justify-content: space-between;
            }

            .layout {
                gap: 18px;
            }

            .field-pair {
                grid-template-columns: 1fr;
            }

            .combo-pair {
                grid-template-columns: minmax(96px, 0.4fr) minmax(0, 1fr);
            }
        }
    </style>
</head>
<body>
<div class="page">
    <header class="topbar">
        <div class="brand">
            <div class="brand-mark">
                <img src="{{ asset('assets/ems-logo.png') }}" alt="{{ $pageTitle }}" class="brand-logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';">
                <div class="brand-badge">MS</div>
            </div>
            <div>
                <h1>{{ $pageTitle }}</h1>
                <p>{{ $registrationPortalText }}</p>
            </div>
        </div>
        <nav class="topbar-actions">
            <a href="{{ route('portal.register.retrieve', ['lang' => $locale]) }}">{{ $retrieveDocumentsText }}</a>
        </nav>
    </header>

    <main class="layout">
        <aside class="card side-card">
            <div class="hero">
                <span class="pill">{{ $pageTitle }}</span>
                <h2>{{ $heroTitle }}</h2>
                <p>{{ $personalInformationText }} · {{ $residenceInformationText }} · {{ $academicInformationText }} · {{ $submittedDocumentsText }}</p>
            </div>

            <div class="progress">
                <div class="progress-track">
                    <span class="progress-fill" data-progress-fill></span>
                </div>
                <div class="step-list">
                    <button class="step-chip active" type="button" data-step-btn="0">1. {{ $personalInformationText }}</button>
                    <button class="step-chip" type="button" data-step-btn="1">2. {{ $residenceInformationText }}</button>
                    <button class="step-chip" type="button" data-step-btn="2">3. {{ $academicInformationText }}</button>
                    <button class="step-chip" type="button" data-step-btn="3">4. {{ $submittedDocumentsText }}</button>
                </div>
            </div>

            <div class="summary">
                <div class="summary-header">
                    <h3>{{ $smartSummaryText }}</h3>
                </div>
                <div class="summary-grid">
                    <div class="summary-item">
                        <span>{{ __('Full Name (AR)') }}</span>
                        <strong data-preview="full_name_ar">—</strong>
                    </div>
                    <div class="summary-item">
                        <span>{{ __('Full Name (EN)') }}</span>
                        <strong data-preview="full_name_en">—</strong>
                    </div>
                    <div class="summary-item">
                        <span>{{ __('National ID') }}</span>
                        <strong data-preview="national_id">—</strong>
                    </div>
                    <div class="summary-item">
                        <span>{{ __('Birth Date') }}</span>
                        <strong data-preview="birth_date">—</strong>
                    </div>
                    <div class="summary-item">
                        <span>{{ __('Email') }}</span>
                        <strong data-preview="email">—</strong>
                    </div>
                    <div class="summary-item">
                        <span>{{ __('Mobile 1') }}</span>
                        <strong data-preview="residence_mobile_1">—</strong>
                    </div>
                </div>
                <div class="summary-foot">{{ $summaryFootText }}</div>
            </div>
        </aside>

        <section class="card form-card">
            <form id="registration-form" novalidate>
                <div class="alert" id="form-alert"></div>
                <div class="success" id="form-success"></div>

                @if ($isEditMode)
                    <div class="notice-box">
                        <h3>{{ $missingNoticeTitle }}</h3>
                        <p>{{ $missingNoticeBody }}</p>
                        @if ($missingDocumentKeys !== [])
                            <ul>
                                @foreach ($missingDocumentKeys as $documentKey)
                                    <li>{{ $documentLabels[$documentKey] ?? $documentKey }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif

                <div class="form-step active" data-step="0">
                    <div class="step-title">
                        <h2>{{ $personalInformationText }}</h2>
                        <span>1 / 4</span>
                    </div>
                    <div class="grid grid-two name-grid">
                        <div class="field">
                            <label for="full_name_ar">{{ __('Full Name (AR)') }}</label>
                            <input id="full_name_ar" name="full_name_ar" type="text" required maxlength="255" autocomplete="name">
                            <div class="error" data-error-for="full_name_ar"></div>
                        </div>
                        <div class="field">
                            <label for="full_name_en">{{ __('Full Name (EN)') }}</label>
                            <input id="full_name_en" name="full_name_en" type="text" required maxlength="255" autocomplete="name">
                            <div class="error" data-error-for="full_name_en"></div>
                        </div>
                    </div>
                    <div class="grid">
                        <div class="field">
                            <label for="gender">{{ __('Gender') }}</label>
                            <select id="gender" name="gender" required>
                                <option value="">—</option>
                                <option value="male">{{ __('gender.male') }}</option>
                                <option value="female">{{ __('gender.female') }}</option>
                            </select>
                            <div class="error" data-error-for="gender"></div>
                        </div>
                        <div class="field">
                            <label for="nationality">{{ __('Nationality') }}</label>
                            <select id="nationality" name="nationality" required data-source="nationalities">
                                <option value="">—</option>
                            </select>
                            <div class="error" data-error-for="nationality"></div>
                        </div>
                        <div class="field">
                            <label for="religion">{{ __('Religion') }}</label>
                            <select id="religion" name="religion" required data-source="religions">
                                <option value="">—</option>
                            </select>
                            <div class="error" data-error-for="religion"></div>
                        </div>
                        <div class="field">
                            <label for="national_id">{{ __('National ID') }}</label>
                            <input id="national_id" name="national_id" type="text" required maxlength="50" inputmode="text" autocomplete="off">
                            <div class="error" data-error-for="national_id"></div>
                        </div>
                        <div class="field">
                            <label for="issued_from">{{ __('Issued From') }}</label>
                            <input id="issued_from" name="issued_from" type="text" required maxlength="100">
                            <div class="error" data-error-for="issued_from"></div>
                        </div>
                        <div class="field">
                            <label for="governorate">{{ __('Governorate') }}</label>
                            <select id="governorate" name="governorate" required data-source="provinces">
                                <option value="">—</option>
                            </select>
                            <div class="error" data-error-for="governorate"></div>
                        </div>
                        <div class="field">
                            <label for="birth_governorate">{{ __('Birth Governorate') }}</label>
                            <select id="birth_governorate" name="birth_governorate" required data-source="provinces">
                                <option value="">—</option>
                            </select>
                            <div class="error" data-error-for="birth_governorate"></div>
                        </div>
                        <div class="field field-hint-float">
                            <label for="birth_date">{{ __('Birth Date') }}</label>
                            <input id="birth_date" name="birth_date" type="date">
                            <div class="hint" data-birth-hint>{{ $birthHintText }}</div>
                            <div class="error" data-error-for="birth_date"></div>
                        </div>
                    </div>
                </div>

                <div class="form-step" data-step="1">
                    <div class="step-title">
                        <h2>{{ $residenceInformationText }}</h2>
                        <span>2 / 4</span>
                    </div>
                    <div class="grid">
                        <div class="field">
                            <label for="residence_house_number">{{ __('House Number') }}</label>
                            <input id="residence_house_number" name="residence_house_number" type="text" required maxlength="10">
                            <div class="error" data-error-for="residence_house_number"></div>
                        </div>
                        <div class="field">
                            <label for="residence_street">{{ __('Street') }}</label>
                            <input id="residence_street" name="residence_street" type="text" required maxlength="255">
                            <div class="error" data-error-for="residence_street"></div>
                        </div>
                        <div class="field">
                            <label for="residence_center">{{ __('Center') }}</label>
                            <input id="residence_center" name="residence_center" type="text" required maxlength="100">
                            <div class="error" data-error-for="residence_center"></div>
                        </div>
                        <div class="field">
                            <label for="residence_governorate">{{ __('Residence Governorate') }}</label>
                            <select id="residence_governorate" name="residence_governorate" required data-source="provinces">
                                <option value="">—</option>
                            </select>
                            <div class="error" data-error-for="residence_governorate"></div>
                        </div>
                        <div class="field span-2">
                            <label for="residence_phone">{{ __('Residence Phone') }}</label>
                            <input id="residence_phone" name="residence_phone" type="tel" required maxlength="10" pattern="[0-9\s\-\+\(\)]*" inputmode="numeric">
                            <div class="error" data-error-for="residence_phone"></div>
                        </div>
                        <div class="field-pair combo-pair span-2">
                            <div class="field">
                                <label for="residence_mobile_1_country_code">{{ __('Mobile 1 Country Code') }}</label>
                                <select id="residence_mobile_1_country_code" name="residence_mobile_1_country_code" required>
                                    <option value="">—</option>
                                    @foreach ($countryCodes as $code => $label)
                                        <option value="{{ $code }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <div class="error" data-error-for="residence_mobile_1_country_code"></div>
                            </div>
                            <div class="field">
                                <label for="residence_mobile_1">{{ __('Mobile 1 Number') }}</label>
                                <input id="residence_mobile_1" name="residence_mobile_1" type="tel" required minlength="11" maxlength="11" pattern="\d{11}" inputmode="numeric" placeholder="{{ $mobilePlaceholderText }}">
                                <div class="error" data-error-for="residence_mobile_1"></div>
                            </div>
                        </div>
                        <div class="field-pair combo-pair span-2">
                            <div class="field">
                                <label for="residence_mobile_2_country_code">{{ __('Mobile 2 Country Code') }}</label>
                                <select id="residence_mobile_2_country_code" name="residence_mobile_2_country_code">
                                    <option value="">—</option>
                                    @foreach ($countryCodes as $code => $label)
                                        <option value="{{ $code }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <div class="error" data-error-for="residence_mobile_2_country_code"></div>
                            </div>
                            <div class="field">
                                <label for="residence_mobile_2">{{ __('Mobile 2 Number') }}</label>
                                <input id="residence_mobile_2" name="residence_mobile_2" type="tel" minlength="11" maxlength="11" pattern="\d{11}" inputmode="numeric" placeholder="{{ $mobilePlaceholderText }}">
                                <div class="error" data-error-for="residence_mobile_2"></div>
                            </div>
                        </div>
                        <div class="field span-2">
                            <label for="email">{{ __('Email') }}</label>
                            <input id="email" name="email" type="email" required maxlength="50" autocomplete="email" pattern="[^@\s]+@[^@\s]+\.[^@\s]{2,}" placeholder="admin@example.com">
                            <div class="error" data-error-for="email"></div>
                        </div>
                    </div>
                </div>

                <div class="form-step" data-step="2">
                    <div class="step-title">
                        <h2>{{ $academicInformationText }}</h2>
                        <span>3 / 4</span>
                    </div>
                    <div class="grid">
                        <div class="field">
                            <label for="faculty">{{ __('Faculty') }}</label>
                            <input id="faculty" name="faculty" type="text" required maxlength="255" value="الطب والجراحة" readonly>
                            <div class="error" data-error-for="faculty"></div>
                        </div>
                        <div class="field">
                            <label for="university">{{ __('University') }}</label>
                            <select id="university" name="university" required data-source="medical-universities">
                                <option value="">—</option>
                            </select>
                            <div class="error" data-error-for="university"></div>
                        </div>
                        <div class="field">
                            <label for="graduation_year">{{ __('Graduation Year') }}</label>
                            <input id="graduation_year" name="graduation_year" type="text" required maxlength="10" inputmode="numeric">
                            <div class="error" data-error-for="graduation_year"></div>
                        </div>
                        <div class="field">
                            <label for="graduation_month">{{ __('Graduation Month') }}</label>
                            <select id="graduation_month" name="graduation_month" required>
                                <option value="">—</option>
                                @for ($month = 1; $month <= 12; $month++)
                                    <option value="{{ $month }}">{{ $month }}</option>
                                @endfor
                            </select>
                            <div class="error" data-error-for="graduation_month"></div>
                        </div>
                        <div class="field">
                            <label for="grade">{{ __('Grade') }}</label>
                            <select id="grade" name="grade" required data-source="grades">
                                <option value="">—</option>
                            </select>
                            <div class="error" data-error-for="grade"></div>
                        </div>
                        <div class="field">
                            <label for="first_foreign_language">{{ __('First Foreign Language') }}</label>
                            <select id="first_foreign_language" name="first_foreign_language" required data-source="languages" data-locked="true">
                                <option value="">—</option>
                            </select>
                            <div class="error" data-error-for="first_foreign_language"></div>
                        </div>
                        <div class="field">
                            <label for="second_foreign_language">{{ __('Second Foreign Language') }}</label>
                            <select id="second_foreign_language" name="second_foreign_language" data-source="languages">
                                <option value="">—</option>
                            </select>
                            <div class="error" data-error-for="second_foreign_language"></div>
                        </div>
                    </div>
                </div>

                <div class="form-step" data-step="3">
                    <div class="step-title">
                        <h2>{{ $submittedDocumentsText }}</h2>
                        <span>4 / 4</span>
                    </div>
                    <div class="hint">{{ $uploadHintText }}</div>
                    <div class="file-grid">
                        @php($personalImageState = $getDocumentState('personal_image'))
                        <label class="file-card"
                               data-default-file-label="{{ $personalImageState['defaultLabel'] }}"
                               data-default-file-status="{{ $personalImageState['status'] }}"
                               data-default-file-status-class="{{ $personalImageState['statusClass'] }}">
                            <input type="file" name="personal_image" accept="image/png,image/jpeg" {{ $personalImageState['required'] ? 'required' : '' }}>
                            <strong>{{ $documentLabels['personal_image'] ?? __('Personal Photo') }}</strong>
                            <span data-file-name>{{ $personalImageState['defaultLabel'] }}</span>
                            <small class="file-status {{ $personalImageState['statusClass'] }}" data-file-status>{{ $personalImageState['status'] }}</small>
                            <img alt="" data-file-preview>
                            <div class="error" data-error-for="personal_image"></div>
                        </label>
                        @php($nationalIdImageState = $getDocumentState('national_id_image'))
                        <label class="file-card"
                               data-default-file-label="{{ $nationalIdImageState['defaultLabel'] }}"
                               data-default-file-status="{{ $nationalIdImageState['status'] }}"
                               data-default-file-status-class="{{ $nationalIdImageState['statusClass'] }}">
                            <input type="file" name="national_id_image" accept="image/png,image/jpeg" {{ $nationalIdImageState['required'] ? 'required' : '' }}>
                            <strong>{{ $documentLabels['national_id_image'] ?? __('National ID Photo') }}</strong>
                            <span data-file-name>{{ $nationalIdImageState['defaultLabel'] }}</span>
                            <small class="file-status {{ $nationalIdImageState['statusClass'] }}" data-file-status>{{ $nationalIdImageState['status'] }}</small>
                            <img alt="" data-file-preview>
                            <div class="error" data-error-for="national_id_image"></div>
                        </label>
                        @php($graduationCertificateState = $getDocumentState('graduation_certificate_image'))
                        <label class="file-card"
                               data-default-file-label="{{ $graduationCertificateState['defaultLabel'] }}"
                               data-default-file-status="{{ $graduationCertificateState['status'] }}"
                               data-default-file-status-class="{{ $graduationCertificateState['statusClass'] }}">
                            <input type="file" name="graduation_certificate_image" accept="image/png,image/jpeg" {{ $graduationCertificateState['required'] ? 'required' : '' }}>
                            <strong>{{ $documentLabels['graduation_certificate_image'] ?? __('Graduation Certificate') }}</strong>
                            <span data-file-name>{{ $graduationCertificateState['defaultLabel'] }}</span>
                            <small class="file-status {{ $graduationCertificateState['statusClass'] }}" data-file-status>{{ $graduationCertificateState['status'] }}</small>
                            <img alt="" data-file-preview>
                            <div class="error" data-error-for="graduation_certificate_image"></div>
                        </label>
                        @php($internshipCertificateState = $getDocumentState('internship_certificate_image'))
                        <label class="file-card"
                               data-default-file-label="{{ $internshipCertificateState['defaultLabel'] }}"
                               data-default-file-status="{{ $internshipCertificateState['status'] }}"
                               data-default-file-status-class="{{ $internshipCertificateState['statusClass'] }}">
                            <input type="file" name="internship_certificate_image" accept="image/png,image/jpeg" {{ $internshipCertificateState['required'] ? 'required' : '' }}>
                            <strong>{{ $documentLabels['internship_certificate_image'] ?? __('Internship Certificate') }}</strong>
                            <span data-file-name>{{ $internshipCertificateState['defaultLabel'] }}</span>
                            <small class="file-status {{ $internshipCertificateState['statusClass'] }}" data-file-status>{{ $internshipCertificateState['status'] }}</small>
                            <img alt="" data-file-preview>
                            <div class="error" data-error-for="internship_certificate_image"></div>
                        </label>
                        @php($criminalRecordState = $getDocumentState('criminal_record_certificate_image'))
                        <label class="file-card"
                               data-default-file-label="{{ $criminalRecordState['defaultLabel'] }}"
                               data-default-file-status="{{ $criminalRecordState['status'] }}"
                               data-default-file-status-class="{{ $criminalRecordState['statusClass'] }}">
                            <input type="file" name="criminal_record_certificate_image" accept="image/png,image/jpeg" {{ $criminalRecordState['required'] ? 'required' : '' }}>
                            <strong>{{ $documentLabels['criminal_record_certificate_image'] ?? __('Criminal Record Certificate') }}</strong>
                            <span data-file-name>{{ $criminalRecordState['defaultLabel'] }}</span>
                            <small class="file-status {{ $criminalRecordState['statusClass'] }}" data-file-status>{{ $criminalRecordState['status'] }}</small>
                            <img alt="" data-file-preview>
                            <div class="error" data-error-for="criminal_record_certificate_image"></div>
                        </label>
                        @php($dobImageState = $getDocumentState('dob_image'))
                        <label class="file-card"
                               data-default-file-label="{{ $dobImageState['defaultLabel'] }}"
                               data-default-file-status="{{ $dobImageState['status'] }}"
                               data-default-file-status-class="{{ $dobImageState['statusClass'] }}">
                            <input type="file" name="dob_image" accept="image/png,image/jpeg" {{ $dobImageState['required'] ? 'required' : '' }}>
                            <strong>{{ $documentLabels['dob_image'] ?? __('Date of Birth Certificate') }}</strong>
                            <span data-file-name>{{ $dobImageState['defaultLabel'] }}</span>
                            <small class="file-status {{ $dobImageState['statusClass'] }}" data-file-status>{{ $dobImageState['status'] }}</small>
                            <img alt="" data-file-preview>
                            <div class="error" data-error-for="dob_image"></div>
                        </label>
                    </div>
                    <div class="field declaration-field">
                        <h3>{{ $declarationTitleText }}</h3>
                        <p>{{ $declarationParagraphOneText }}</p>
                        <p>{{ $declarationParagraphTwoText }}</p>
                        <p>{{ $declarationParagraphThreeText }}</p>
                        <label class="declaration-consent" for="declaration_accepted">
                            <input id="declaration_accepted" name="declaration_accepted" type="checkbox" value="1" required>
                            <span>{{ $declarationAgreeText }}</span>
                        </label>
                        <div class="error" data-error-for="declaration_accepted"></div>
                    </div>
                </div>

                <div class="actions">
                    <button class="btn secondary" type="button" id="back-btn">{{ $backButtonText }}</button>
                    <button class="btn primary" type="button" id="next-btn">{{ $nextButtonText }}</button>
                </div>
            </form>
        </section>
    </main>
</div>

<script>
    (() => {
        const locale = document.documentElement.lang || 'ar';
        const apiBase = @json(url('/api/v1'));
        const isEditMode = @json($isEditMode);
        const submitUrl = @json($submitUrl);
        const prefillData = @json($prefillData);
        const form = document.getElementById('registration-form');
        const steps = Array.from(document.querySelectorAll('[data-step]'));
        const stepButtons = Array.from(document.querySelectorAll('[data-step-btn]'));
        const progressFill = document.querySelector('[data-progress-fill]');
        const nextBtn = document.getElementById('next-btn');
        const backBtn = document.getElementById('back-btn');
        const alertBox = document.getElementById('form-alert');
        const successBox = document.getElementById('form-success');
        const birthDateInput = document.getElementById('birth_date');
        const birthHint = document.querySelector('[data-birth-hint]');
        const nationalIdInput = document.getElementById('national_id');
        const nationalitySelect = document.getElementById('nationality');
        const facultyInput = document.getElementById('faculty');
        const firstForeignLanguageSelect = document.getElementById('first_foreign_language');
        const mobile2CodeInput = document.getElementById('residence_mobile_2_country_code');
        const mobile2Input = document.getElementById('residence_mobile_2');
        const declarationCheckbox = document.getElementById('declaration_accepted');
        const successPageBaseUrl = @json(route('portal.register.success'));
        const sendingText = @json($sendingButtonText);
        const nextText = @json($nextButtonText);
        const submitText = @json($submitButtonText);
        const fixFieldsText = @json($fixFieldsText);
        const nationalIdBirthDateInvalidText = @json(__('National ID birth date is invalid.'));
        const declarationRequiredText = @json(__('You must agree to the declaration before submitting.'));

        let currentStep = 0;

        const pad = (value) => String(value).padStart(2, '0');

        const extractBirthDate = (nationalId) => {
            if (!/^\d{14}$/.test(nationalId)) {
                return null;
            }
            const centuryCode = parseInt(nationalId[0], 10);
            const baseMap = {1: 1800, 2: 1900, 3: 2000, 4: 2100, 5: 2200, 6: 2300, 7: 2400, 8: 2500, 9: 2600};
            const centuryBase = baseMap[centuryCode];
            if (!centuryBase) {
                return null;
            }
            const year = centuryBase + parseInt(nationalId.slice(1, 3), 10);
            const month = parseInt(nationalId.slice(3, 5), 10);
            const day = parseInt(nationalId.slice(5, 7), 10);
            const date = new Date(Date.UTC(year, month - 1, day));
            if (date.getUTCFullYear() !== year || date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day) {
                return null;
            }
            return `${year}-${pad(month)}-${pad(day)}`;
        };

        const isEgyptNationality = () => {
            const option = nationalitySelect?.selectedOptions?.[0];
            const label = option ? option.textContent.trim() : '';
            const code = Number(option?.dataset?.code ?? NaN);

            return [1, 214].includes(code) || /مصر/i.test(label) || /egypt/i.test(label);
        };

        const applyNationalityRules = () => {
            const egyptian = isEgyptNationality();
            if (egyptian) {
                nationalIdInput.maxLength = 14;
                nationalIdInput.minLength = 14;
                nationalIdInput.pattern = '[0-9]{14}';
                nationalIdInput.inputMode = 'numeric';
                birthDateInput.readOnly = true;
                birthDateInput.required = false;
                birthDateInput.setCustomValidity('');
                if (birthHint) {
                    birthHint.style.display = '';
                }
                updateBirthDate();
            } else {
                nationalIdInput.removeAttribute('pattern');
                nationalIdInput.removeAttribute('minLength');
                nationalIdInput.maxLength = 50;
                nationalIdInput.inputMode = 'text';
                nationalIdInput.setCustomValidity('');
                birthDateInput.readOnly = false;
                birthDateInput.required = true;
                birthDateInput.setCustomValidity('');
                if (birthHint) {
                    birthHint.style.display = '';
                }
                const autoBirthDate = extractBirthDate(nationalIdInput.value.replace(/\D/g, '').slice(0, 14));
                if (autoBirthDate && birthDateInput.value === autoBirthDate) {
                    birthDateInput.value = '';
                    updatePreview('birth_date', '—');
                }
            }
        };

        const updateBirthDate = () => {
            if (!isEgyptNationality()) {
                nationalIdInput.setCustomValidity('');
                return;
            }
            const value = nationalIdInput.value.replace(/\D/g, '').slice(0, 14);
            nationalIdInput.value = value;
            const birthDate = extractBirthDate(value);
            const isInvalidForEgyptianAutoFill = value.length === 14 && !birthDate;
            nationalIdInput.setCustomValidity(isInvalidForEgyptianAutoFill ? nationalIdBirthDateInvalidText : '');
            birthDateInput.value = birthDate || '';
            updatePreview('birth_date', birthDate || '—');
        };

        const syncMobile2Required = () => {
            const hasMobile2 = mobile2Input.value.trim().length > 0;
            const hasCode = mobile2CodeInput.value.trim().length > 0;
            mobile2CodeInput.required = hasMobile2;
            mobile2Input.required = hasCode;
        };

        const syncDeclarationValidity = () => {
            if (! declarationCheckbox) {
                return;
            }

            declarationCheckbox.setCustomValidity(
                declarationCheckbox.checked ? '' : declarationRequiredText
            );
        };

        const isSelectLocked = (select) => select?.dataset.locked === 'true';

        const applyFixedAcademicDefaults = () => {
            if (facultyInput) {
                if (!isEditMode || !prefillData.faculty) {
                    facultyInput.value = 'الطب والجراحة';
                }
                facultyInput.readOnly = true;
            }

            if (!firstForeignLanguageSelect) {
                return;
            }

            if (isEditMode && prefillData.first_foreign_language) {
                firstForeignLanguageSelect.dataset.prefillValue = String(prefillData.first_foreign_language);
                return;
            }

            const options = Array.from(firstForeignLanguageSelect.options).filter((option) => option.value !== '');
            if (options.length === 0) {
                return;
            }

            const englishOption = options.find((option) => {
                const label = (option.textContent || '').trim();
                return /english|الإنجليزية|الانجليزية|إنجليزي|انجليزي/i.test(label);
            });

            const targetOption = englishOption || options[0];
            firstForeignLanguageSelect.value = targetOption.value;
            updatePreview('first_foreign_language', targetOption.textContent || '—');
        };

        const updateProgress = () => {
            const percentage = (currentStep / (steps.length - 1)) * 100;
            progressFill.style.width = `${percentage}%`;
            stepButtons.forEach((btn, index) => {
                btn.classList.toggle('active', index === currentStep);
            });
            backBtn.style.visibility = currentStep === 0 ? 'hidden' : 'visible';
            nextBtn.textContent = currentStep === steps.length - 1 ? submitText : nextText;
        };

        const showStep = (index) => {
            steps.forEach((step, idx) => {
                step.classList.toggle('active', idx === index);
            });
            currentStep = index;
            updateProgress();
        };

        const markStepDone = (index) => {
            const btn = stepButtons[index];
            if (btn) {
                btn.classList.add('done');
            }
        };

        const clearErrors = () => {
            alertBox.classList.remove('visible');
            successBox.classList.remove('visible');
            form.querySelectorAll('.error').forEach((el) => el.textContent = '');
            form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
        };

        const showAlert = (message) => {
            alertBox.textContent = message;
            alertBox.classList.add('visible');
        };

        const escapeName = (value) => {
            if (window.CSS && CSS.escape) {
                return CSS.escape(value);
            }
            return value.replace(/["\\.#:[\\]()>+~*^$|=]/g, '\\$&');
        };

        const findFieldByName = (name) => {
            return form.querySelector(`[name="${escapeName(name)}"]`);
        };

        const findStepIndexForField = (field) => {
            if (!field) {
                return null;
            }
            const step = field.closest('[data-step]');
            return step ? steps.indexOf(step) : null;
        };

        const showFieldError = (name, message) => {
            const field = findFieldByName(name);
            if (!field) {
                return;
            }
            field.classList.add('is-invalid');
            if (field.classList.contains('select-hidden')) {
                const input = field.closest('.field')?.querySelector('.select-input');
                if (input) {
                    input.classList.add('is-invalid');
                }
            }
            const error = field.closest('.field, .file-card')?.querySelector('.error');
            if (error) {
                error.textContent = message;
            }
        };

        const validateStep = (index) => {
            const step = steps[index];
            const fields = Array.from(step.querySelectorAll('input, select, textarea'));
            for (const field of fields) {
                if (field.disabled) {
                    continue;
                }
                if (field === declarationCheckbox) {
                    syncDeclarationValidity();
                }
                if (!field.checkValidity()) {
                    if (field.classList.contains('select-hidden')) {
                        const input = field.closest('.field')?.querySelector('.select-input');
                        if (input) {
                            input.classList.add('is-invalid');
                        }
                        const error = field.closest('.field')?.querySelector('.error');
                        if (error) {
                            error.textContent = selectRequiredText;
                        }
                    } else {
                        field.reportValidity();
                        field.classList.add('is-invalid');
                        const error = field.closest('.field, .file-card')?.querySelector('.error');
                        if (error && field.validationMessage) {
                            error.textContent = field.validationMessage;
                        }
                    }
                    showAlert(fixFieldsText);
                    return false;
                }
            }
            return true;
        };

        const updatePreview = (name, value) => {
            const target = document.querySelector(`[data-preview="${name}"]`);
            if (target) {
                target.textContent = value || '—';
            }
        };

        const applyPrefillData = () => {
            Object.entries(prefillData || {}).forEach(([name, value]) => {
                const field = findFieldByName(name);

                if (!field || value === null || value === undefined) {
                    return;
                }

                if (field.type === 'checkbox') {
                    field.checked = Boolean(value);
                    return;
                }

                if (field.tagName === 'SELECT') {
                    field.dataset.prefillValue = String(value);

                    if (Array.from(field.options).some((option) => option.value === String(value))) {
                        field.value = String(value);
                        updatePreview(field.name, field.selectedOptions[0]?.textContent || '—');
                    }

                    return;
                }

                field.value = String(value);
                updatePreview(field.name, field.value);
            });

            syncMobile2Required();
            syncDeclarationValidity();
            applyNationalityRules();
            updateBirthDate();
        };

        const syncPreview = (event) => {
            const field = event.target;
            if (!field.name) {
                return;
            }
            let value = field.value;
            if (field.tagName === 'SELECT') {
                value = field.selectedOptions[0]?.textContent || '';
            }
            updatePreview(field.name, value);
        };

        const debounce = (fn, delay) => {
            let timer;
            return (...args) => {
                clearTimeout(timer);
                timer = setTimeout(() => fn(...args), delay);
            };
        };

        const searchableSelects = new Map();
        const selectRequiredText = @json($selectRequiredText);

        const closeSearchableSelect = (select) => {
            const state = searchableSelects.get(select);
            if (!state) {
                return;
            }
            state.wrapper.classList.remove('open');
            state.input.setAttribute('aria-expanded', 'false');
            const selected = select.selectedOptions[0];
            state.input.value = selected && selected.value !== '' ? selected.textContent : '';
            if (select === nationalitySelect) {
                applyNationalityRules();
            }
        };

        const openSearchableSelect = (select) => {
            if (isSelectLocked(select)) {
                return;
            }

            const state = searchableSelects.get(select);
            if (!state) {
                return;
            }
            state.wrapper.classList.add('open');
            state.input.setAttribute('aria-expanded', 'true');
            state.panel.scrollTop = 0;
        };

        const refreshSearchableOptions = (select) => {
            const state = searchableSelects.get(select);
            if (!state) {
                return;
            }
            state.list.innerHTML = '';
            Array.from(select.options).forEach((option) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'select-option';
                btn.dataset.value = option.value;
                btn.textContent = option.textContent;
                if (option.disabled) {
                    btn.disabled = true;
                }
                if (option.selected && option.value !== '') {
                    btn.classList.add('is-selected');
                }
                state.list.appendChild(btn);
            });
        };

        const filterSearchableOptions = (select, query) => {
            const state = searchableSelects.get(select);
            if (!state) {
                return;
            }
            const needle = query.trim().toLowerCase();
            const options = state.list.querySelectorAll('.select-option');
            options.forEach((btn) => {
                const label = btn.textContent.toLowerCase();
                const match = !needle || label.includes(needle);
                btn.classList.toggle('is-hidden', !match);
            });
        };

        const updateSearchableValue = (select, value) => {
            const state = searchableSelects.get(select);
            if (!state) {
                return;
            }
            select.value = value;
            const selected = select.selectedOptions[0];
            state.input.value = selected && selected.value !== '' ? selected.textContent : '';
            state.list.querySelectorAll('.select-option').forEach((btn) => {
                btn.classList.toggle('is-selected', btn.dataset.value === select.value && select.value !== '');
            });
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
        };

        const enhanceSearchableSelect = (select) => {
            if (select.dataset.searchableReady) {
                return;
            }
            const field = select.closest('.field');
            if (!field) {
                return;
            }

            const wrapper = document.createElement('div');
            wrapper.className = 'select-shell';

            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'select-input';
            input.placeholder = select.options[0]?.textContent || '—';
            input.autocomplete = 'off';
            input.setAttribute('role', 'combobox');
            input.setAttribute('aria-expanded', 'false');

            const panel = document.createElement('div');
            panel.className = 'select-panel';

            const list = document.createElement('div');
            panel.appendChild(list);

            wrapper.appendChild(input);
            wrapper.appendChild(panel);

            field.insertBefore(wrapper, select);

            select.classList.add('select-hidden');
            select.tabIndex = -1;
            select.dataset.searchableReady = 'true';

            searchableSelects.set(select, { wrapper, input, panel, list });
            refreshSearchableOptions(select);

            input.addEventListener('focus', () => {
                if (isSelectLocked(select)) {
                    return;
                }
                openSearchableSelect(select);
                filterSearchableOptions(select, input.value);
                input.select();
            });

            input.addEventListener('input', () => {
                if (isSelectLocked(select)) {
                    return;
                }
                openSearchableSelect(select);
                filterSearchableOptions(select, input.value);
            });

            input.addEventListener('keydown', (event) => {
                if (isSelectLocked(select)) {
                    return;
                }
                if (event.key === 'Escape') {
                    closeSearchableSelect(select);
                }
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    openSearchableSelect(select);
                    const firstVisible = list.querySelector('.select-option:not(.is-hidden)');
                    if (firstVisible) {
                        firstVisible.focus();
                    }
                }
            });

            list.addEventListener('click', (event) => {
                if (isSelectLocked(select)) {
                    return;
                }
                const target = event.target.closest('.select-option');
                if (!target || target.disabled) {
                    return;
                }
                updateSearchableValue(select, target.dataset.value ?? '');
                closeSearchableSelect(select);
            });

            list.addEventListener('keydown', (event) => {
                if (isSelectLocked(select)) {
                    return;
                }
                if (event.key === 'Enter') {
                    event.preventDefault();
                    const target = event.target.closest('.select-option');
                    if (target && !target.disabled) {
                        updateSearchableValue(select, target.dataset.value ?? '');
                        closeSearchableSelect(select);
                        input.focus();
                    }
                }
            });

            document.addEventListener('click', (event) => {
                if (!wrapper.contains(event.target)) {
                    closeSearchableSelect(select);
                }
            });

            closeSearchableSelect(select);
        };

        const syncSearchableState = (select) => {
            const state = searchableSelects.get(select);
            if (!state) {
                return;
            }
            refreshSearchableOptions(select);
            closeSearchableSelect(select);
            state.input.disabled = select.disabled;
            state.input.readOnly = isSelectLocked(select);
            state.wrapper.classList.toggle('is-locked', isSelectLocked(select));
        };

        const resetPreviews = () => {
            document.querySelectorAll('[data-preview]').forEach((node) => {
                node.textContent = '—';
            });
        };

        const resetFiles = () => {
            document.querySelectorAll('.file-card').forEach((card) => {
                const nameLabel = card.querySelector('[data-file-name]');
                const status = card.querySelector('[data-file-status]');
                const preview = card.querySelector('[data-file-preview]');
                if (nameLabel) {
                    nameLabel.textContent = card.dataset.defaultFileLabel || @json($selectFileText);
                }
                if (status) {
                    status.textContent = card.dataset.defaultFileStatus || '';
                    status.className = 'file-status';
                    if (card.dataset.defaultFileStatusClass) {
                        status.classList.add(card.dataset.defaultFileStatusClass);
                    }
                }
                if (preview) {
                    preview.classList.remove('visible');
                    preview.removeAttribute('src');
                }
            });
        };

        const clearFormOnLoad = () => {
            form.reset();
            resetPreviews();
            resetFiles();
            applyFixedAcademicDefaults();
            applyPrefillData();
            document.querySelectorAll('select').forEach((select) => {
                syncSearchableState(select);
            });
            applyNationalityRules();
            syncMobile2Required();
            syncDeclarationValidity();
            stepButtons.forEach((btn) => btn.classList.remove('done'));
            showStep(0);
        };

        const submitForm = async () => {
            clearErrors();
            if (!validateStep(currentStep)) {
                return;
            }
            nextBtn.disabled = true;
            nextBtn.textContent = sendingText;

            const formData = new FormData(form);

            try {
                const response = await fetch(submitUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'lang': locale,
                    },
                    body: formData,
                });

                const data = await response.json().catch(() => ({}));

                if (response.ok && data.success) {
                    const regCode = (data?.reg_code || data?.data?.reg_code || '').toString().trim();
                    const pdfUrls = data?.data?.pdf_urls || data?.pdf_urls || null;

                    if (pdfUrls && typeof pdfUrls === 'object') {
                        try {
                            sessionStorage.setItem('register_success_payload', JSON.stringify({
                                reg_code: regCode,
                                pdf_urls: pdfUrls,
                                saved_at: Date.now(),
                            }));
                        } catch (storageError) {
                        }
                    }

                    const successUrl = new URL(successPageBaseUrl, window.location.origin);
                    successUrl.searchParams.set('lang', locale);
                    if (regCode) {
                        successUrl.searchParams.set('reg_code', regCode);
                    }
                    window.location.assign(successUrl.toString());
                    return;
                } else if (response.status === 422 && data.errors) {
                    let firstErrorStep = null;
                    let firstErrorField = null;
                    Object.entries(data.errors).forEach(([name, messages]) => {
                        showFieldError(name, messages[0]);
                        const field = findFieldByName(name);
                        const stepIndex = findStepIndexForField(field);
                        if (stepIndex !== null && (firstErrorStep === null || stepIndex < firstErrorStep)) {
                            firstErrorStep = stepIndex;
                            firstErrorField = field;
                        }
                    });
                    showAlert(data.message || fixFieldsText);
                    if (firstErrorStep !== null) {
                        stepButtons.forEach((btn, idx) => {
                            if (idx >= firstErrorStep) {
                                btn.classList.remove('done');
                            }
                        });
                        showStep(firstErrorStep);
                        setTimeout(() => {
                            if (firstErrorField) {
                                firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                firstErrorField.focus({ preventScroll: true });
                            }
                        }, 150);
                    }
                } else {
                    showAlert(data.message || fixFieldsText);
                }
            } catch (error) {
                showAlert(fixFieldsText);
            } finally {
                nextBtn.disabled = false;
                nextBtn.textContent = currentStep === steps.length - 1 ? submitText : nextText;
            }
        };

        const populateSelect = async (select, endpoint) => {
            if (!select) {
                return;
            }
            select.disabled = true;
            try {
                const response = await fetch(`${apiBase}/${endpoint}`, {
                    headers: {
                        'Accept': 'application/json',
                        'lang': locale,
                    }
                });
                const data = await response.json().catch(() => ({}));
                const items = data.data || data || [];
                items.forEach((item) => {
                    const option = document.createElement('option');
                    option.value = item.id;
                    if (item.code !== undefined && item.code !== null) {
                        option.dataset.code = String(item.code);
                    }
                    option.textContent = item.name ?? item.title ?? item.label ?? item.id;
                    select.appendChild(option);
                });
                select.disabled = false;
                const saved = select.dataset.prefillValue ?? form.querySelector(`[name="${select.name}"]`)?.value;
                if (saved) {
                    select.value = saved;
                    const selectedText = select.selectedOptions[0]?.textContent;
                    if (selectedText) {
                        updatePreview(select.name, selectedText);
                    }
                }
                applyFixedAcademicDefaults();
                syncSearchableState(select);
            } catch (error) {
                select.disabled = false;
            }
        };

        const initFiles = () => {
            const fileCards = document.querySelectorAll('.file-card');
            fileCards.forEach((card) => {
                const input = card.querySelector('input[type="file"]');
                const nameLabel = card.querySelector('[data-file-name]');
                const status = card.querySelector('[data-file-status]');
                const preview = card.querySelector('[data-file-preview]');
                if (!input) {
                    return;
                }
                input.addEventListener('change', () => {
                    const file = input.files?.[0];
                    if (file) {
                        nameLabel.textContent = file.name;
                        if (status) {
                            status.textContent = @json($selectedForUploadText);
                            status.className = 'file-status existing';
                        }
                        if (preview && file.type.startsWith('image/')) {
                            preview.src = URL.createObjectURL(file);
                            preview.classList.add('visible');
                        }
                    } else {
                        nameLabel.textContent = card.dataset.defaultFileLabel || @json($selectFileText);
                        if (status) {
                            status.textContent = card.dataset.defaultFileStatus || '';
                            status.className = 'file-status';
                            if (card.dataset.defaultFileStatusClass) {
                                status.classList.add(card.dataset.defaultFileStatusClass);
                            }
                        }
                        if (preview) {
                            preview.classList.remove('visible');
                            preview.removeAttribute('src');
                        }
                    }
                });
            });
        };

        stepButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                const index = parseInt(btn.dataset.stepBtn, 10);
                if (index <= currentStep) {
                    showStep(index);
                }
            });
        });

        nextBtn.addEventListener('click', () => {
            clearErrors();
            if (!validateStep(currentStep)) {
                return;
            }
            markStepDone(currentStep);
            if (currentStep === steps.length - 1) {
                submitForm();
                return;
            }
            showStep(currentStep + 1);
        });

        backBtn.addEventListener('click', () => {
            if (currentStep > 0) {
                showStep(currentStep - 1);
            }
        });

        form.addEventListener('input', syncPreview);
        nationalIdInput.addEventListener('input', updateBirthDate);
        if (nationalitySelect) {
            nationalitySelect.addEventListener('change', applyNationalityRules);
        }
        mobile2Input.addEventListener('input', syncMobile2Required);
        mobile2CodeInput.addEventListener('change', syncMobile2Required);
        if (declarationCheckbox) {
            declarationCheckbox.addEventListener('change', () => {
                syncDeclarationValidity();
                if (declarationCheckbox.checked) {
                    declarationCheckbox.classList.remove('is-invalid');
                    const error = declarationCheckbox.closest('.field')?.querySelector('.error');
                    if (error) {
                        error.textContent = '';
                    }
                }
            });
        }
        updateProgress();
        initFiles();
        document.querySelectorAll('select').forEach((select) => {
            enhanceSearchableSelect(select);
            syncSearchableState(select);
        });
        clearFormOnLoad();
        window.addEventListener('pageshow', () => {
            clearFormOnLoad();
        });

        const sources = new Map([
            ['nationalities', 'nationalities'],
            ['religions', 'religions'],
            ['provinces', 'provinces'],
            ['medical-universities', 'medical-universities'],
            ['grades', 'grades'],
            ['languages', 'languages'],
        ]);

        document.querySelectorAll('select[data-source]').forEach((select) => {
            const key = select.dataset.source;
            const endpoint = sources.get(key);
            if (endpoint) {
                populateSelect(select, endpoint);
            }
        });
    })();
</script>
</body>
</html>
