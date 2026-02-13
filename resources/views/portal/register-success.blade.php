@php
    $locale = in_array(request('lang'), ['ar', 'en'], true) ? request('lang') : 'ar';
    app()->setLocale($locale);

    $regCode = trim((string) request('reg_code'));

    $registrationPdfUrl = $regCode !== ''
        ? route('register-pdf-document', ['reg_code' => $regCode, 'document' => 'registration-request'])
        : null;
    $licensePdfUrl = $regCode !== ''
        ? route('register-pdf-document', ['reg_code' => $regCode, 'document' => 'license-request'])
        : null;

    $registerUrl = url('/register') . '?lang=' . $locale;
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Registration Complete') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Space+Grotesk:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: light;
            --ink: #0d1f24;
            --muted: #5a6b72;
            --primary: #1f8f6d;
            --primary-strong: #17684f;
            --bg: #f4f7f6;
            --panel: #ffffff;
            --line: rgba(15, 23, 42, 0.12);
            --soft: rgba(31, 143, 109, 0.1);
            --warn: #9f3a14;
            --warn-bg: #fff4ef;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Cairo", "Space Grotesk", sans-serif;
            background:
                radial-gradient(circle at 12% 10%, #e4f2ec 0%, transparent 38%),
                radial-gradient(circle at 88% 12%, #fff0e9 0%, transparent 36%),
                var(--bg);
            color: var(--ink);
            min-height: 100vh;
        }

        html[lang="en"] body {
            font-family: "Space Grotesk", "Cairo", sans-serif;
        }

        .wrapper {
            width: min(980px, calc(100% - 32px));
            margin: 28px auto 36px;
            display: grid;
            gap: 18px;
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
            gap: 12px;
        }

        .brand-logo {
            width: 56px;
            height: 56px;
            object-fit: contain;
            background: #fff;
            border-radius: 50%;
            border: 1px solid var(--line);
            padding: 4px;
        }

        .brand h1 {
            margin: 0;
            font-size: clamp(24px, 3.2vw, 34px);
            line-height: 1.15;
        }

        .lang-toggle {
            display: inline-flex;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 5px;
            gap: 6px;
        }

        .lang-toggle a {
            text-decoration: none;
            color: var(--muted);
            padding: 6px 14px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 13px;
        }

        .lang-toggle a.active {
            background: #0f2430;
            color: #fff;
        }

        .card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: clamp(20px, 3vw, 30px);
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
        }

        .headline {
            display: grid;
            gap: 10px;
            margin-bottom: 18px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            background: var(--soft);
            color: var(--primary-strong);
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 700;
        }

        .headline h2 {
            margin: 0;
            font-size: clamp(22px, 2.8vw, 32px);
            line-height: 1.2;
        }

        .headline p {
            margin: 0;
            color: var(--muted);
            line-height: 1.75;
            font-size: 15px;
        }

        .code {
            margin-top: 2px;
            font-size: 15px;
            color: var(--ink);
            font-weight: 700;
        }

        .doc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 14px;
            margin-top: 18px;
        }

        .doc-item {
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 14px;
            background: #fff;
            display: grid;
            gap: 10px;
        }

        .doc-item h3 {
            margin: 0;
            font-size: 17px;
            line-height: 1.3;
        }

        .doc-item p {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
            font-size: 14px;
            min-height: 42px;
        }

        .doc-item a {
            text-decoration: none;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            min-height: 40px;
            border-radius: 10px;
            background: linear-gradient(120deg, var(--primary), #4dc49b);
            color: #fff;
            font-weight: 700;
            font-size: 14px;
            padding: 8px 14px;
        }

        .doc-item a:hover {
            filter: brightness(0.95);
        }

        .warning {
            margin-top: 12px;
            border: 1px solid rgba(159, 58, 20, 0.25);
            background: var(--warn-bg);
            color: var(--warn);
            border-radius: 12px;
            padding: 12px 14px;
            line-height: 1.7;
            font-weight: 700;
        }

        .actions {
            margin-top: 18px;
            display: flex;
            justify-content: flex-start;
        }

        .actions a {
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            border-radius: 11px;
            padding: 9px 16px;
            border: 1px solid var(--line);
            color: var(--ink);
            background: #fff;
            font-weight: 700;
        }

        @media (max-width: 640px) {
            .topbar {
                flex-direction: column;
                align-items: stretch;
            }

            .actions {
                justify-content: stretch;
            }

            .actions a {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <header class="topbar">
            <div class="brand">
                <img src="{{ asset('assets/medical-syndicate-logo.png') }}" alt="" class="brand-logo">
                <h1>{{ __('Registration Complete') }}</h1>
            </div>
            <div class="lang-toggle">
                <a href="{{ route('portal.register.success', ['lang' => 'ar', 'reg_code' => $regCode]) }}" class="{{ $locale === 'ar' ? 'active' : '' }}">العربية</a>
                <a href="{{ route('portal.register.success', ['lang' => 'en', 'reg_code' => $regCode]) }}" class="{{ $locale === 'en' ? 'active' : '' }}">English</a>
            </div>
        </header>

        <section class="card">
            <div class="headline">
                <span class="badge">{{ __('You can now download your documents') }}</span>
                <h2>{{ __('Required documents before visiting the syndicate') }}</h2>
                <p>{{ __('Please download the following documents and fill all required data before heading to the syndicate.') }}</p>
                @if ($regCode !== '')
                    <div class="code">{{ __('Registration Code') }}: {{ $regCode }}</div>
                @endif
            </div>

            @if ($regCode !== '')
                <div class="doc-grid">
                    <article class="doc-item">
                        <h3>{{ __('Registration request form') }}</h3>
                        <p>{{ __('Contains your general registration request data. Print and complete any missing fields.') }}</p>
                        <a href="{{ $registrationPdfUrl }}" target="_blank" rel="noopener noreferrer">{{ __('Download Registration Request PDF') }}</a>
                    </article>
                    <article class="doc-item">
                        <h3>{{ __('Practice license request form') }}</h3>
                        <p>{{ __('Contains your practice license request. Print it and complete signatures and required entries.') }}</p>
                        <a href="{{ $licensePdfUrl }}" target="_blank" rel="noopener noreferrer">{{ __('Download License Request PDF') }}</a>
                    </article>
                </div>
            @else
                <div class="warning">{{ __('Registration code is missing. Please go back and submit the registration form again.') }}</div>
            @endif

            <div class="actions">
                <a href="{{ $registerUrl }}">{{ __('Back to Registration Form') }}</a>
            </div>
        </section>
    </div>
</body>
</html>
