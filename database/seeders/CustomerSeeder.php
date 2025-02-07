<?php

namespace Database\Seeders;

use App\Models\Customer;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        Customer::create([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone' => '123-456-7890',
            'address' => '123 Main Street, Springfield',
            'type' => 'individual',
            'company_name' => null,
        ]);

        Customer::create([
            'name' => 'Acme Corp',
            'email' => 'contact@acmecorp.com',
            'phone' => '098-765-4321',
            'address' => '456 Corporate Blvd, Business City',
            'type' => 'company',
            'company_name' => 'Acme Corp',
        ]);

        Customer::create([
            'name' => 'Jane Smith',
            'email' => 'jane.smith@example.com',
            'phone' => '321-654-9870',
            'address' => '789 Elm Street, Springfield',
            'type' => 'individual',
            'company_name' => null,
        ]);
    }
}
