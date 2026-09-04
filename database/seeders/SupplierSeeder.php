<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        Supplier::firstOrCreate(['code' => 'supplier-a'], ['name' => 'Supplier A']);
        Supplier::firstOrCreate(['code' => 'supplier-b'], ['name' => 'Supplier B']);
    }
}
