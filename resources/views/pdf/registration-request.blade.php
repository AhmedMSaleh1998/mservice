@php
    $dir = 'rtl';
    $birthDate = $request->birth_date?->format('d/m/Y') ?? '';
    $formatLocalMobile = static function (?string $mobile): string {
        $mobile = trim((string) $mobile);

        if ($mobile === '') {
            return '';
        }

        return str_starts_with($mobile, '0') ? $mobile : '0' . $mobile;
    };
    $mobile1 = $formatLocalMobile($request->residence_mobile_1);
    $mobile2 = $formatLocalMobile($request->residence_mobile_2);
    $residenceAddressParts = array_filter([
        $labels['residence_governorate'] ?? $request->residence_governorate,
        $request->residence_center,
        $request->residence_street,
    ]);
    $residenceAddress = implode(' - ', $residenceAddressParts);
    $logoPath = public_path('assets/medical-syndicate-logo.png');
    $logoData = file_exists($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;
    $facultyValue = (string) ($request->faculty ?? '');
    $bachelorTitle = 'الطب والجراحة';
    if ($facultyValue !== '' && ! str_contains($facultyValue, 'كلية')) {
        $bachelorTitle = $facultyValue;
    }
@endphp
<!doctype html>
<html lang="ar" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: A4 portrait;
            margin: 18mm 16mm 18mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: "Tajawal", sans-serif;
            font-size: 12px;
            font-weight: 600;
            color: #111827;
            line-height: 1.4;
            direction: rtl;
            text-align: right;
        }
        .page {
            width: 100%;
        }
        .header {
            text-align: center;
            margin-bottom: 8px;
        }
        .header .title {
            font-size: 15px;
            font-weight: 700;
        }
        .header .subtitle {
            font-size: 14px;
            font-weight: 700;
            margin-top: 4px;
        }
        .logo {
            width: 62px;
            height: 62px;
            margin: 6px auto 2px;
            display: block;
        }
        .line {
            border-top: 1px solid #6b7280;
            margin: 6px 0 8px;
        }
        table.grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table.grid td {
            padding: 2px 4px;
            vertical-align: top;
            text-align: right;
        }
        .number-cell {
            direction: ltr;
            unicode-bidi: isolate;
            text-align: left;
            white-space: nowrap;
        }
        .address-value {
            white-space: nowrap;
            font-size: 11px;
        }
        td.label {
            font-weight: 700;
            white-space: nowrap;
        }
        .ltr {
            direction: ltr;
            text-align: left;
        }
        .email-value {
            direction: ltr;
            text-align: left;
            white-space: nowrap;
            font-size: 10px;
            line-height: 1.2;
            padding-left: 0;
        }
        .center {
            text-align: center;
        }
        .dots {
            display: inline-block;
            min-width: 160px;
            border-bottom: 2px dotted #6b7280;
            height: 10px;
            vertical-align: middle;
        }
        .dots.short {
            min-width: 110px;
        }
        .dots.long {
            min-width: 240px;
        }
        .pair col.col-left-label {
            width: 10%;
        }
        .pair col.col-left-value {
            width: 35%;
        }
        .pair col.col-gap {
            width: 10%;
        }
        .pair col.col-right-value {
            width: 35%;
        }
        .pair col.col-right-label {
            width: 10%;
        }
        .name-row .name-value {
            white-space: nowrap;
            word-break: keep-all;
            font-size: 11px;
            line-height: 1.3;
        }
        .name-row .name-value-en {
            direction: ltr;
            text-align: left;
        }
        .single col.col-label {
            width: 16%;
        }
        .single col.col-value {
            width: 84%;
        }
        .triple td {
            text-align: center;
        }
        .double td {
            width: 50%;
        }
        .amount {
            margin: 10px 0 4px;
            text-align: center;
        }
        .amount .labels span {
            display: inline-block;
            min-width: 60px;
            font-weight: 700;
        }
        .amount .line {
            border-top: none;
            margin: 4px auto 6px;
            width: 150px;
            border-bottom: 2px dotted #6b7280;
            height: 10px;
        }
        .footer-note {
            text-align: center;
            margin-bottom: 6px;
        }
        .generated-at {
            text-align: center;
            margin-top: 6px;
            font-size: 10px;
        }
    </style>
</head>
<body>
<div class="page">
    <div class="header">
        <div class="title">النقابة العامة للأطباء</div>
        @if ($logoData)
            <img src="{{ $logoData }}" alt="logo" class="logo">
        @endif
        <div class="subtitle">طلب قيد بالجدول العام</div>
    </div>

    <div class="line"></div>

    <table class="grid pair">
        <colgroup>
            <col class="col-left-label">
            <col class="col-left-value">
            <col class="col-gap">
            <col class="col-right-value">
            <col class="col-right-label">
        </colgroup>
        <tr class="name-row">
            <td class="label ltr">Name:</td>
            <td class="value name-value name-value-en">{{ $request->full_name_en }}</td>
            <td></td>
            <td class="value name-value">{{ $request->full_name_ar }}</td>
            <td class="label">الاسم:</td>
        </tr>
        <tr>
            <td class="label">&nbsp;</td>
            <td class="value">&nbsp;</td>
            <td></td>
            <td class="value">{{ $labels['gender'] ?? $request->gender }}</td>
            <td class="label">النوع:</td>
        </tr>
        <tr>
            <td class="value">{{ $labels['religion'] ?? $request->religion }}</td>
            <td class="label">الديانة:</td>
            <td></td>
            <td class="value">{{ $labels['nationality'] ?? $request->nationality }}</td>
            <td class="label">الجنسية:</td>
        </tr>
        <tr>
            <td class="value">{{ $labels['birth_governorate'] ?? $request->birth_governorate }}</td>
            <td class="label">محافظة الميلاد:</td>
            <td></td>
            <td class="value">{{ $birthDate }}</td>
            <td class="label">تاريخ الميلاد:</td>
        </tr>
        <tr>
            <td class="label">&nbsp;</td>
            <td class="value">&nbsp;</td>
            <td></td>
            <td class="value">{{ $request->national_id }}</td>
            <td class="label">الرقم القومي:</td>
        </tr>
        <tr>
            <td class="label">محافظة:</td>
            <td class="value">{{ $labels['governorate'] ?? $request->governorate }}</td>
            <td></td>
            <td class="value">{{ $request->issued_from }}</td>
            <td class="label">صادر من قسم/مركز:</td>
        </tr>
    </table>

    <div class="line"></div>

    <table class="grid pair">
        <colgroup>
            <col class="col-left-label">
            <col class="col-left-value">
            <col class="col-gap">
            <col class="col-right-value">
            <col class="col-right-label">
        </colgroup>
        <tr>
            <td class="value">{{ $request->residence_street }}</td>
            <td class="label">شارع:</td>
            <td></td>
            <td class="value"></td>
            <td class="label">المقر الدائم للإقامة: رقم منزل:  {{ $request->residence_house_number }}</td>
        </tr>
        <tr>
            <td class="value">{{ $labels['residence_governorate'] ?? $request->residence_governorate }}</td>
            <td class="label">محافظة:</td>
            <td></td>
            <td class="value">{{ $request->residence_center }}</td>
            <td class="label">مركز/قرية:</td>
        </tr>
        <tr>
            <td class="value address-value" colspan="4">{{ $residenceAddress }}</td>
            <td class="label">العنوان:</td>
        </tr>
        <tr>
            <td class="value">{{ $mobile1 }}</td>
            <td class="label">ت المحمول (1):</td>
            <td></td>
            <td class="value">{{ $request->residence_phone }}</td>
            <td class="label">ت المنزل:</td>
        </tr>
        <tr>
            <td class="value email-value" colspan="2">Email:{{ $request->email }}</td>
            <td></td>
            <td class="value">{{ $mobile2 }}</td>
            <td class="label">ت المحمول (2):</td>
        </tr>
        <tr>
            <td class="label">ت العمل:</td>
            <td class="value">&nbsp;</td>
            <td></td>
            <td class="value">&nbsp;</td>
            <td class="label">جهة العمل (التكليف):</td>
        </tr>
    </table>

    <div class="line"></div>

    <table class="grid single">
        <colgroup>
            <col class="col-label">
            <col class="col-value">
        </colgroup>
        <tr>
            <td class="value">{{ $bachelorTitle }}</td>
            <td class="label">البكالوريوس:</td>
        </tr>
    </table>

    <table class="grid triple" style="margin-top: 4px;">
        <tr>
            <td><span class="value">{{ $labels['grade'] ?? $request->grade }}</span><span class="label">تقدير:</span></td>
            <td><span class="value">{{ $facultyValue }}</span><span class="label">كلية طب:</span></td>
            <td><span class="value">{{ $request->graduation_month }}</span><span class="label">شهر:</span></td>
        </tr>
    </table>

    <div class="line"></div>

    <table class="grid">
        <tr>
            <td class="value" colspan="6">&nbsp;</td>
            <td class="label">اللغة الأجنبية:</td>
        </tr>
    </table>
    <table class="grid double">
        <tr>
            <td><span class="value">{{ $labels['second_foreign_language'] ?? $request->second_foreign_language }}</span><span class="label">اللغة الثانية:</span></td>
            <td><span class="value">{{ $labels['first_foreign_language'] ?? $request->first_foreign_language }}</span><span class="label">اللغة الأولى:</span></td>
        </tr>
    </table>

    <div class="line"></div>

    <table class="grid double">
        <tr>
            <td><span class="dots"></span><span class="label">توقيع الطبيب:</span></td>
            <td class="label">تحريراً في:</td>
        </tr>
    </table>

    <div class="line"></div>

    <div class="amount">
        <div class="labels">
            <span>جنيه</span>
            <span>قرش</span>
        </div>
        <div class="line"></div>
    </div>
    <div class="footer-note">قيمة رسم القيد و اشتراك سنة بالنقابة و المصاريف الإدارية</div>

    <div class="center" style="margin-bottom: 4px;">
        سداد إيصال رقم: ............. : بتاريخ  ...../...../.........
    </div>
    <div class="ltr" style="margin-bottom: 6px;">
       <span class="dots long"></span>  توقيع الموظف المختص :
    </div>

    <div class="line"></div>

    <table class="grid double">
        <tr>
            <td>
                <div class="label">الكود/المدخل :</div>
                <div class="dots short" style="margin-top: 4px;"></div>
            </td>
            <td>
                <div class="label">المراجع</div>
                <div class="dots short" style="margin-top: 4px;"></div>
            </td>
        </tr>
    </table>

    <div class="line" style="margin-top: 6px;"></div>
    <div class="generated-at">تم الانشاء في : {{ $generatedAt }}</div>
</div>
</body>
</html>
