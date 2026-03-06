@php
    $birthDate = $request->birth_date?->format('d-m-Y') ?? '';
    $formatLocalMobile = static function (?string $mobile): string {
        $mobile = trim((string) $mobile);

        if ($mobile === '') {
            return '';
        }

        return str_starts_with($mobile, '0') ? $mobile : '0' . $mobile;
    };
    $mobileNumber = $formatLocalMobile($request->residence_mobile_1);
    if ($mobileNumber === '') {
        $mobileNumber = trim((string) $request->residence_phone);
    }

    $residenceAddressParts = array_filter([
        $labels['residence_governorate'] ?? $request->residence_governorate,
        $request->residence_center,
        $request->residence_house_number,
        $request->residence_street,
    ]);
    $residenceAddress = implode('-', $residenceAddressParts);

    $faculty = trim((string) $request->faculty);
    $facultyText = $faculty === ''
        ? 'كلية طب'
        : (str_contains($faculty, 'كلية') ? $faculty : 'كلية ' . $faculty);
    $qualification =  trim($facultyText . ' ' . ($labels['university'] ?? $request->university));

    $birthPlaceParts = array_filter([
        $labels['birth_governorate'] ?? $request->birth_governorate,
        $request->residence_center,
    ]);
    $birthPlace = implode('-', $birthPlaceParts);
@endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: A4 portrait;
            margin: 22mm 18mm 18mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: "Tajawal", sans-serif;
            font-size: 12px;
            font-weight: 600;
            color: #111827;
            direction: rtl;
            text-align: right;
            line-height: 1.55;
            margin: 0;
        }
        .page {
            width: 100%;
        }
        .line {
            border-top: 1px solid #4b5563;
            margin: 6px 0 12px;
        }
        .title {
            text-align: center;
            font-size: 19px;
            font-weight: 700;
            line-height: 1.1;
            margin-top: 8px;
        }
        .center {
            text-align: center;
        }
        .intro {
            margin-top: 6px;
        }
        table.grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
            margin-top: 8px;
        }
        table.grid td {
            padding: 5px 4px;
            vertical-align: top;
            white-space: nowrap;
            text-align: right;
        }
        .meta col.col-r-label {
            width: 11%;
        }
        .meta col.col-r-value {
            width: 39%;
        }
        .meta col.col-l-label {
            width: 11%;
        }
        .meta col.col-l-value {
            width: 39%;
        }
        .meta td.label-cell {
            font-weight: 700;
            padding-right: 0;
        }
        .meta td.value-cell {
            padding-left: 14px;
        }
        .meta td.value-cell.wide {
            white-space: normal;
        }
        .row-label {
            font-weight: 700;
        }
        .meta {
            margin-top: 22px;
        }
        .meta .empty {
            display: inline-block;
            width: 100%;
            height: 1em;
        }
        .ltr-num {
            direction: ltr;
            unicode-bidi: isolate;
            display: inline-block;
        }
        .dots {
            display: inline-block;
            min-width: 170px;
            border-bottom: 2px dotted #4b5563;
            height: 10px;
            vertical-align: middle;
        }
        .dots.md {
            min-width: 190px;
        }
        .dots.sm {
            min-width: 120px;
        }
        .signatures {
            margin-top: 26px;
            table-layout: fixed;
        }
        .signatures td {
            text-align: center;
            width: 33.33%;
        }
        .signature-label {
            display: block;
            margin-bottom: 2px;
        }
        .spacer {
            margin-top: 30px;
        }
        .treasury-intro {
            margin-top: 10px;
        }
        .footer-block {
            margin-top: 14px;
            line-height: 1.75;
        }
        .bottom-signatures {
            margin-top: 16px;
            table-layout: fixed;
        }
        .bottom-signatures td {
            width: 50%;
            text-align: center;
        }
        .receipt-date {
            direction: rtl;
            unicode-bidi: isolate;
            display: inline-block;
            white-space: nowrap;
        }
        .generated-at {
            text-align: center;
            margin-top: 8px;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="title">طلب استخراج ترخيص<br>لمزاولة مهنة: الطب</div>
        <div class="line"></div>

        <div class="center intro">السيد الدكتور / مدير عام إدارة التراخيص الطبية</div>
        <div class="center">تحية طيبة و بعد ...</div>
        <div class="center" style="margin-top: 12px;">ارجو التكرم بقيد اسمى فى سجل الاطباء والبيانات الخاصة بى كالاتى:</div>

        <table class="grid meta">
            <colgroup>
                <col class="col-r-label">
                <col class="col-r-value">
                <col class="col-l-label">
                <col class="col-l-value">
            </colgroup>
            <tr>
                <td class="value-cell">{{ $mobileNumber }}</td>
                <td class="label-cell"><span class="row-label">تليفون:</span></td>
                <td class="value-cell">{{ $request->full_name_ar }}</td>
                <td class="label-cell"><span class="row-label">الاسم:</span></td>
            </tr>
            <tr>
                <td class="value-cell wide" colspan="3">{{ $residenceAddress }}</td>
                <td class="label-cell"><span class="row-label">عنوان السكن:</span></td>
            </tr>
            <tr>
                <td class="value-cell wide" colspan="3">{{ $qualification }}</td>
                <td class="label-cell"><span class="row-label">المؤهل و جهة التخرج:</span></td>
            </tr>
            <tr>
                <td class="value-cell">{{ $birthPlace }}</td>
                <td class="label-cell"><span class="row-label">جهة الميلاد:</span></td>
                <td class="value-cell">{{ $birthDate }}</td>
                <td class="label-cell"><span class="row-label">تاريخ الميلاد:</span></td>
            </tr>
            <tr>
                <td class="value-cell">&nbsp;</td>
                <td class="label-cell"><span class="row-label">تاريخ الاصدار:</span></td>
                <td class="value-cell">{{ $request->national_id }}</td>
                <td class="label-cell"><span class="row-label">رقم البطاقة :</span></td>
            </tr>
        </table>

        <div class="center spacer">مع العلم بأنه لم يسبق لى استخراج ترخيص بمزاولة المهنة</div>
        <div class="center">وتفضلوا بقبول وافر الاحترام ::</div>

        <table class="grid signatures">
            <tr>
                <td><span class="signature-label">توقيع مقدم الطلب:</span><span class="dots md"></span></td>
                <td><span class="signature-label">توقيع موظف الاستقبال:</span><span class="dots md"></span></td>
                <td><span class="signature-label">التاريخ:</span><span class="receipt-date">&#x0662;&#x0660;...../...../.....</span></td>
            </tr>
        </table>

        <div class="line" style="margin-top: 14px;"></div>

        <div class="center treasury-intro">السيد/مدير خزينة الوزارة</div>

        <div class="center footer-block">
            رجاء قبول مبلغ ...............جنيه (...............................) لحساب الادارة المركزية للمؤسسات <br>
            العلاجية غير الحكومية و الترخيص و تعلية المبلغ بحساب الدائنين و ذلك من السيد/.......................
        </div>

        <table class="grid bottom-signatures">
            <tr>
                <td>مدير إدارة التراخيص الطبية<br><span class="dots md"></span></td>
                <td>توقيع مسئول الخزينة<br><span class="dots md"></span></td>
            </tr>
        </table>

        <div class="center" style="margin-top: 24px;">
            التوقيع باستلام الترخيص<br>
            <span class="dots sm"></span>
        </div>

        <div class="generated-at">تم الانشاء في : {{ $generatedAt }}</div>
    </div>
</body>
</html>
