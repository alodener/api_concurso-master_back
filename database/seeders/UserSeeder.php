<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('users')->insert([
            'name' => 'Super Admin Loto',
            'email' =>'lotomaster@gmail.com',
            'role' => 'super_admin',
            'password' => Hash::make('@#loto2023'),
            'created_at' => Carbon::now('America/Sao_Paulo'),
            'updated_at' => Carbon::now('America/Sao_Paulo')
        ]);
    }
}
