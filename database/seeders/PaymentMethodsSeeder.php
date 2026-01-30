<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\PaymentMethod;

class PaymentMethodsSeeder extends Seeder
{
    public function run(): void
    {
        if (PaymentMethod::query()->exists()) {
            return;
        }

        $items = [
            [
                'name' => ['ar' => 'فوري', 'en' => 'Fawry'],
                'key' => 'fawry',
                'is_active' => true,
            ],
            [
                'name' => ['ar' => 'حساب بنكي', 'en' => 'Bank Account'],
                'key' => 'BankAccount',
                'is_active' => true,
            ],
        ];

        foreach ($items as $item) {
            PaymentMethod::create($item);
        }
    }
}
