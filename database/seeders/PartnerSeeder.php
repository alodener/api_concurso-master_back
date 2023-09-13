<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PartnerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('partners')->insert([
            'name' => 'Banca 1',
            'connection' =>'banca1',
            'created_at' => Carbon::now('America/Sao_Paulo'),
            'updated_at' => Carbon::now('America/Sao_Paulo')
        ]);

        DB::table('partners')->insert([
            'name' => 'Banca 2',
            'connection' =>'banca2',
            'created_at' => Carbon::now('America/Sao_Paulo'),
            'updated_at' => Carbon::now('America/Sao_Paulo')
        ]);
    }
}
