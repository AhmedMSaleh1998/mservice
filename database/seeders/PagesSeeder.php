<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Pages\Models\Page;

class PagesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $slug => $data) {
            $page = Page::withTrashed()->firstOrNew(['slug' => $slug]);
            $page->setTranslation('title', 'ar', $data['title_ar']);
            $page->setTranslation('title', 'en', $data['title_en']);
            $page->setTranslation('content', 'ar', $data['content_ar']);
            $page->setTranslation('content', 'en', $data['content_en']);
            $page->is_active = true;
            $page->deleted_at = null;
            $page->save();
        }
    }

    private function pages(): array
    {
        return [
            'privacy-policy' => [
                'title_ar' => 'سياسة الخصوصية',
                'title_en' => 'Privacy Policy',
                'content_ar' => $this->privacyArabic(),
                'content_en' => $this->privacyEnglish(),
            ],
            'terms-of-service' => [
                'title_ar' => 'شروط الاستخدام',
                'title_en' => 'Terms of Service',
                'content_ar' => $this->termsArabic(),
                'content_en' => $this->termsEnglish(),
            ],
        ];
    }

    private function privacyArabic(): string
    {
        return <<<'HTML'
<p>نحن نقدّر خصوصيتك. توضّح هذه السياسة كيفية جمع البيانات الشخصية واستخدامها وحمايتها عند استخدامك تطبيقنا وخدماتنا.</p>

<h2>1. البيانات التي نجمعها</h2>
<ul>
    <li><strong>بيانات الحساب:</strong> الاسم، البريد الإلكتروني، رقم الهاتف، تاريخ الميلاد.</li>
    <li><strong>بيانات الاستخدام:</strong> معلومات عن استخدامك للتطبيق ووقت الجلسات وأنماط التفاعل.</li>
    <li><strong>بيانات الجهاز:</strong> نوع الجهاز، نظام التشغيل، إصدار التطبيق، ومعرّفات الإشعارات.</li>
</ul>

<h2>2. كيف نستخدم بياناتك</h2>
<ul>
    <li>تقديم الخدمة وتشغيل الحساب الخاص بك.</li>
    <li>تحسين تجربة المستخدم وتطوير الميزات.</li>
    <li>التواصل معك بخصوص التحديثات أو الإشعارات المهمة.</li>
    <li>الالتزام بالمتطلبات القانونية والتنظيمية.</li>
</ul>

<h2>3. مشاركة البيانات</h2>
<p>لا نبيع بياناتك الشخصية لأي طرف ثالث. قد نشارك بعض البيانات مع مزودي الخدمات الموثوقين (مثل خدمات الدفع والإشعارات) فقط بالقدر اللازم لتقديم الخدمة.</p>

<h2>4. حماية البيانات</h2>
<p>نستخدم وسائل تقنية وإدارية مناسبة لحماية بياناتك من الوصول غير المصرّح به أو التعديل أو الإفصاح أو الإتلاف.</p>

<h2>5. حقوقك</h2>
<ul>
    <li>الوصول إلى بياناتك الشخصية وتصحيحها.</li>
    <li>طلب حذف حسابك وبياناتك المرتبطة به.</li>
    <li>سحب موافقتك على معالجة البيانات في أي وقت.</li>
</ul>

<h2>6. الاحتفاظ بالبيانات</h2>
<p>نحتفظ ببياناتك طوال فترة استخدامك للخدمة، وللفترة اللازمة بعد ذلك للالتزام بالمتطلبات القانونية.</p>

<h2>7. التعديلات على هذه السياسة</h2>
<p>قد نقوم بتحديث هذه السياسة من وقت لآخر، وسيتم إعلامك بأي تغييرات جوهرية عبر التطبيق أو البريد الإلكتروني.</p>

<h2>8. التواصل معنا</h2>
<p>لأي استفسارات بخصوص هذه السياسة أو بياناتك الشخصية، يُرجى التواصل معنا عبر البريد الإلكتروني الرسمي للدعم.</p>
HTML;
    }

    private function privacyEnglish(): string
    {
        return <<<'HTML'
<p>We value your privacy. This policy explains how we collect, use, and protect your personal data when you use our application and services.</p>

<h2>1. Information We Collect</h2>
<ul>
    <li><strong>Account data:</strong> Name, email address, phone number, date of birth.</li>
    <li><strong>Usage data:</strong> Information about how you use the app, session times, and interaction patterns.</li>
    <li><strong>Device data:</strong> Device type, operating system, app version, and push notification identifiers.</li>
</ul>

<h2>2. How We Use Your Data</h2>
<ul>
    <li>To provide and operate your account and our services.</li>
    <li>To improve user experience and develop new features.</li>
    <li>To send you important updates and notifications.</li>
    <li>To comply with legal and regulatory obligations.</li>
</ul>

<h2>3. Sharing Your Data</h2>
<p>We do not sell your personal data to third parties. We may share data with trusted service providers (such as payment processors and notification providers) only to the extent necessary to deliver the service.</p>

<h2>4. Data Security</h2>
<p>We use appropriate technical and organizational measures to protect your data against unauthorized access, alteration, disclosure, or destruction.</p>

<h2>5. Your Rights</h2>
<ul>
    <li>Access and correct your personal data.</li>
    <li>Request deletion of your account and associated data.</li>
    <li>Withdraw consent to data processing at any time.</li>
</ul>

<h2>6. Data Retention</h2>
<p>We retain your data for as long as you use the service and for any additional period required to comply with legal obligations.</p>

<h2>7. Changes to This Policy</h2>
<p>We may update this policy from time to time. You will be notified of any material changes through the app or by email.</p>

<h2>8. Contact Us</h2>
<p>For any questions about this policy or your personal data, please contact us through our official support email.</p>
HTML;
    }

    private function termsArabic(): string
    {
        return <<<'HTML'
<p>باستخدامك التطبيق، فأنت توافق على شروط الاستخدام التالية. يُرجى قراءتها بعناية.</p>

<h2>1. قبول الشروط</h2>
<p>استخدامك للتطبيق يعني موافقتك الكاملة على هذه الشروط وأي تعديلات تطرأ عليها.</p>

<h2>2. الحساب والمسؤولية</h2>
<p>أنت مسؤول عن الحفاظ على سرية بيانات حسابك، وعن جميع الأنشطة التي تتم من خلاله.</p>

<h2>3. الاستخدام المسموح</h2>
<p>يُحظر استخدام التطبيق لأي غرض غير قانوني أو ينتهك حقوق الآخرين.</p>

<h2>4. إنهاء الخدمة</h2>
<p>نحتفظ بالحق في تعليق أو إنهاء حسابك في حال مخالفة هذه الشروط.</p>

<h2>5. التواصل</h2>
<p>للاستفسارات، يُرجى التواصل معنا عبر قنوات الدعم الرسمية.</p>
HTML;
    }

    private function termsEnglish(): string
    {
        return <<<'HTML'
<p>By using our application, you agree to the following terms of service. Please read them carefully.</p>

<h2>1. Acceptance of Terms</h2>
<p>Your use of the application constitutes full acceptance of these terms and any future modifications.</p>

<h2>2. Account and Responsibility</h2>
<p>You are responsible for maintaining the confidentiality of your account credentials and for all activities under your account.</p>

<h2>3. Acceptable Use</h2>
<p>You may not use the application for any unlawful purpose or in a way that infringes on the rights of others.</p>

<h2>4. Termination</h2>
<p>We reserve the right to suspend or terminate your account in the event of any violation of these terms.</p>

<h2>5. Contact</h2>
<p>For any inquiries, please contact us through our official support channels.</p>
HTML;
    }
}
