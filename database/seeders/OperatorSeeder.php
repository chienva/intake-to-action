<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class OperatorSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email' => 'owner@example.test'], ['name' => 'Synthetic operator', 'password' => '!disabled-operator-login!']);
    }
}
