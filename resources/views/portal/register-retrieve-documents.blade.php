@php
    $locale = in_array(request('lang'), ['ar', 'en'], true) ? request('lang') : 'ar';
    app()->setLocale($locale);
    $countryCodes = \App\Support\CountryCodeOptions::options();
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Retrieve Documents') }}</title>
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
            --danger: #9f3a14;
            --danger-bg: #fff4ef;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Cairo", "Space Grotesk", sans-serif;
            background:
                radial-gradient(circle at 10% 8%, #e4f2ec 0%, transparent 36%),
                radial-gradient(circle at 88% 10%, #fff0e9 0%, transparent 34%),
                var(--bg);
            color: var(--ink);
            min-height: 100vh;
        }

        html[lang="en"] body {
            font-family: "Space Grotesk", "Cairo", sans-serif;
        }

        .wrapper {
            width: min(840px, calc(100% - 32px));
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
            font-size: clamp(22px, 2.8vw, 30px);
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
            display: grid;
            gap: 16px;
        }

        .headline {
            display: grid;
            gap: 10px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            background: var(--soft);
            color: var(--primary-strong);
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 700;
        }

        .headline p {
            margin: 0;
            color: var(--muted);
            line-height: 1.75;
            font-size: 15px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .field {
            display: grid;
            gap: 6px;
        }

        .field label {
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
        }

        .field input,
        .field select {
            width: 100%;
            min-height: 44px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: #fff;
            font-size: 14px;
            font-family: inherit;
            padding: 10px 12px;
        }

        .field.span-2 {
            grid-column: span 2;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 4px;
            flex-wrap: wrap;
        }

        .btn {
            border: none;
            border-radius: 11px;
            padding: 10px 16px;
            min-height: 42px;
            font-family: inherit;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn.primary {
            background: linear-gradient(120deg, var(--primary), #4dc49b);
            color: #fff;
        }

        .btn.primary:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }

        .btn.secondary {
            background: #fff;
            color: var(--ink);
            border: 1px solid var(--line);
        }

        .alert {
            display: none;
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 14px;
            line-height: 1.7;
        }

        .alert.visible {
            display: block;
        }

        .alert.error {
            border: 1px solid rgba(159, 58, 20, 0.25);
            background: var(--danger-bg);
            color: var(--danger);
        }

        .alert.success {
            border: 1px solid rgba(23, 104, 79, 0.2);
            background: rgba(31, 143, 109, 0.1);
            color: var(--primary-strong);
        }

        .doc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 12px;
            margin-top: 6px;
        }

        .doc-item {
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px;
            display: grid;
            gap: 10px;
            background: #fff;
        }

        .doc-item h3 {
            margin: 0;
            font-size: 16px;
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

        .documents {
            display: none;
            gap: 12px;
        }

        .documents.visible {
            display: grid;
        }

        @media (max-width: 680px) {
            .topbar {
                flex-direction: column;
                align-items: stretch;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .field.span-2 {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <header class="topbar">
        <div class="brand">
            <img src="{{ asset('assets/medical-syndicate-logo.png') }}" alt="" class="brand-logo">
            <h1>{{ __('Retrieve Documents') }}</h1>
        </div>
        <div class="lang-toggle">
            <a href="{{ route('portal.register.retrieve', ['lang' => 'ar']) }}" class="{{ $locale === 'ar' ? 'active' : '' }}">العربية</a>
            <a href="{{ route('portal.register.retrieve', ['lang' => 'en']) }}" class="{{ $locale === 'en' ? 'active' : '' }}">English</a>
        </div>
    </header>

    <section class="card">
        <div class="headline">
            <span class="badge">{{ __('Retrieve Documents') }}</span>
            <p>{{ __('Enter your national ID and registered Mobile 1 number to retrieve your documents.') }}</p>
        </div>

        <form id="retrieve-form" novalidate>
            <div class="form-grid">
                <div class="field span-2">
                    <label for="national_id">{{ __('National ID') }}</label>
                    <input id="national_id" name="national_id" type="text" required maxlength="50" inputmode="numeric">
                </div>
                <div class="field">
                    <label for="residence_mobile_1_country_code">{{ __('Mobile 1 Country Code') }}</label>
                    <select id="residence_mobile_1_country_code" name="residence_mobile_1_country_code" required>
                        <option value="">—</option>
                        @foreach ($countryCodes as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="residence_mobile_1">{{ __('Mobile 1 Number') }}</label>
                    <input id="residence_mobile_1" name="residence_mobile_1" type="text" required minlength="11" maxlength="11" pattern="\d{11}" inputmode="numeric" placeholder="{{ __('Enter 11-digit mobile number.') }}">
                </div>
            </div>
            <div class="actions">
                <button type="submit" class="btn primary" id="retrieve-btn">{{ __('Retrieve Documents') }}</button>
                <a href="{{ url('/register') . '?lang=' . $locale }}" class="btn secondary">{{ __('Back to Registration Form') }}</a>
            </div>
        </form>

        <div id="error-box" class="alert error"></div>
        <div id="success-box" class="alert success"></div>

        <div class="documents" id="documents-box">
            <div class="doc-grid">
                <article class="doc-item">
                    <h3>{{ __('Registration request form') }}</h3>
                    <a href="#" id="registration-request-link" target="_blank" rel="noopener noreferrer">{{ __('Download Registration Request PDF') }}</a>
                </article>
                <article class="doc-item">
                    <h3>{{ __('Practice license request form') }}</h3>
                    <a href="#" id="license-request-link" target="_blank" rel="noopener noreferrer">{{ __('Download License Request PDF') }}</a>
                </article>
            </div>
        </div>
    </section>
</div>

<script>
    (() => {
        const locale = document.documentElement.lang || 'ar';
        const apiBase = @json(url('/api/v1'));
        const form = document.getElementById('retrieve-form');
        const retrieveBtn = document.getElementById('retrieve-btn');
        const errorBox = document.getElementById('error-box');
        const successBox = document.getElementById('success-box');
        const documentsBox = document.getElementById('documents-box');
        const registrationRequestLink = document.getElementById('registration-request-link');
        const licenseRequestLink = document.getElementById('license-request-link');

        const defaultErrorText = @json(__('Unable to retrieve documents with provided data.'));
        const rateLimitErrorText = @json(__('Too many attempts. Please try again in one minute.'));
        const successText = @json(__('Documents retrieved successfully.'));
        const loadingText = @json(__('Sending...'));
        const submitText = @json(__('Retrieve Documents'));

        const hideMessages = () => {
            errorBox.classList.remove('visible');
            successBox.classList.remove('visible');
            errorBox.textContent = '';
            successBox.textContent = '';
        };

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            hideMessages();
            documentsBox.classList.remove('visible');

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            retrieveBtn.disabled = true;
            retrieveBtn.textContent = loadingText;

            const payload = {
                national_id: form.national_id.value.trim(),
                residence_mobile_1_country_code: form.residence_mobile_1_country_code.value.trim(),
                residence_mobile_1: form.residence_mobile_1.value.trim(),
            };

            try {
                const response = await fetch(`${apiBase}/register-request/retrieve-documents`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'lang': locale,
                    },
                    body: JSON.stringify(payload),
                });

                const data = await response.json().catch(() => ({}));

                if (response.ok && data.success) {
                    const urls = data?.data?.pdf_urls || {};
                    const regCode = (data?.data?.reg_code || '').toString().trim();

                    if (!urls.registration_request || !urls.license_request) {
                        throw new Error('Missing PDF links');
                    }

                    registrationRequestLink.href = urls.registration_request;
                    licenseRequestLink.href = urls.license_request;
                    documentsBox.classList.add('visible');
                    successBox.textContent = data.message || successText;
                    successBox.classList.add('visible');

                    try {
                        sessionStorage.setItem('register_success_payload', JSON.stringify({
                            reg_code: regCode,
                            pdf_urls: urls,
                            saved_at: Date.now(),
                        }));
                    } catch (storageError) {
                    }

                    return;
                }

                const message = response.status === 429
                    ? rateLimitErrorText
                    : (data.message || defaultErrorText);
                errorBox.textContent = message;
                errorBox.classList.add('visible');
            } catch (error) {
                errorBox.textContent = defaultErrorText;
                errorBox.classList.add('visible');
            } finally {
                retrieveBtn.disabled = false;
                retrieveBtn.textContent = submitText;
            }
        });
    })();
</script>
</body>
</html>
