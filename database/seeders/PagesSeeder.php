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
            'terms-of-service' => [
                'title_ar' => 'شروط الاستخدام',
                'title_en' => 'Terms of Service',
                'content_ar' => $this->termsArabic(),
                'content_en' => $this->termsEnglish(),
            ],
        ];
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
