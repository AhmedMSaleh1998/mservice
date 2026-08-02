@php
    $isArabic = ($locale ?? 'ar') === 'ar';
    $dir = $isArabic ? 'rtl' : 'ltr';
    $title = $page->getTranslation('title', $locale, false) ?: $page->title;
    $content = $page->getTranslation('content', $locale, false) ?: $page->content;
    $otherLocale = $isArabic ? 'en' : 'ar';
    $otherLocaleLabel = $isArabic ? 'English' : 'العربية';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="index, follow">
    <title>{{ $title }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: {{ $isArabic ? "'Cairo', 'Tajawal', 'Segoe UI', system-ui, sans-serif" : "'Segoe UI', system-ui, -apple-system, sans-serif" }};
            background: #f8fafc;
            color: #1f2937;
            line-height: 1.8;
            -webkit-font-smoothing: antialiased;
        }
        .topbar {
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            padding: 14px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .topbar h2 {
            margin: 0;
            font-size: 16px;
            color: #6b7280;
            font-weight: 500;
        }
        .topbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .topbar-brand img {
            height: 36px;
            width: auto;
            display: block;
        }
        .lang-switch {
            color: #2563eb;
            text-decoration: none;
            font-size: 14px;
            padding: 6px 14px;
            border: 1px solid #2563eb;
            border-radius: 6px;
            transition: all 0.15s ease;
        }
        .lang-switch:hover {
            background: #2563eb;
            color: #fff;
        }
        .container {
            max-width: 820px;
            margin: 40px auto;
            background: #ffffff;
            padding: 48px 56px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.03);
        }
        h1 {
            margin: 0 0 12px;
            font-size: 32px;
            color: #111827;
            font-weight: 700;
        }
        .meta {
            color: #9ca3af;
            font-size: 13px;
            margin-bottom: 32px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f3f4f6;
        }
        .content {
            font-size: 16px;
            color: #374151;
        }
        .content h1, .content h2, .content h3 { color: #111827; margin-top: 32px; }
        .content h2 { font-size: 22px; }
        .content h3 { font-size: 18px; }
        .content p { margin: 12px 0; }
        .content ul, .content ol { padding-{{ $isArabic ? 'right' : 'left' }}: 24px; }
        .content li { margin: 6px 0; }
        .content a { color: #2563eb; text-decoration: underline; }
        .content blockquote {
            border-{{ $isArabic ? 'right' : 'left' }}: 4px solid #2563eb;
            padding: 8px 16px;
            margin: 16px 0;
            background: #f9fafb;
            color: #4b5563;
        }
        @media (max-width: 640px) {
            .container { margin: 16px; padding: 28px 22px; }
            h1 { font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="topbar-brand">
            <img src="{{ asset('assets/ems-logo.png') }}" alt="{{ config('app.name') }}">
            <h2>{{ $isArabic ? 'صفحة قانونية' : 'Legal Page' }}</h2>
        </div>
        <a class="lang-switch" href="{{ url()->current() }}?lang={{ $otherLocale }}">{{ $otherLocaleLabel }}</a>
    </div>

    <div class="container">
        <h1>{{ $title }}</h1>
        <div class="meta">
            {{ $isArabic ? 'آخر تحديث:' : 'Last updated:' }}
            {{ $page->updated_at->format('Y-m-d') }}
        </div>
        <div class="content">
            {!! $content !!}
        </div>
    </div>
</body>
</html>
