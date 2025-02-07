<?php

namespace Database\Seeders;

use App\Models\Currency;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Currency::create([
            'name' => 'United States Dollar',
            'shortcut' => 'USD',
            'symbol' => '$',
            'decimal_numbers' => 2,
            'usd_to_currency' => 1.00,
            'is_active' => true,
            'is_default' => true,
        ]);

        Currency::create([
            'name' => 'Euro',
            'shortcut' => 'EUR',
            'symbol' => '€',
            'decimal_numbers' => 2,
            'usd_to_currency' => 1.18,
            'is_active' => true,
            'is_default' => false,
        ]);

        Currency::create([
            'name' => 'British Pound',
            'shortcut' => 'GBP',
            'symbol' => '£',
            'decimal_numbers' => 2,
            'usd_to_currency' => 1.38,
            'is_active' => true,
            'is_default' => false,
        ]);

        Currency::create([
            'name' => 'Lebanese Pound',
            'shortcut' => 'LBP',
            'symbol' => 'LL',
            'decimal_numbers' => 0,
            'usd_to_currency' => 90000,
            'is_active' => true,
            'is_default' => false,
        ]);
    }
}
