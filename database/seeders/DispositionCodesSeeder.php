<?php

namespace Database\Seeders;

use App\Models\DispositionCode;
use Illuminate\Database\Seeder;

class DispositionCodesSeeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            ['campaign_code' => '', 'code' => 'SALE', 'label' => 'Sale', 'sort_order' => 10],
            ['campaign_code' => '', 'code' => 'CBH', 'label' => 'Callback - Hot', 'sort_order' => 20],
            ['campaign_code' => '', 'code' => 'CBW', 'label' => 'Callback - Warm', 'sort_order' => 21],
            ['campaign_code' => '', 'code' => 'CBC', 'label' => 'Callback - Cold', 'sort_order' => 22],
            ['campaign_code' => '', 'code' => 'DNC', 'label' => 'Do Not Call', 'sort_order' => 30],
            ['campaign_code' => '', 'code' => 'NAN', 'label' => 'Not a Number', 'sort_order' => 40],
            ['campaign_code' => '', 'code' => 'NA', 'label' => 'No Answer', 'sort_order' => 50],
            ['campaign_code' => '', 'code' => 'BUSY', 'label' => 'Busy', 'sort_order' => 60],
            ['campaign_code' => '', 'code' => 'OTHER', 'label' => 'Other', 'sort_order' => 90],
        ];
        foreach ($codes as $row) {
            DispositionCode::updateOrCreate(
                ['campaign_code' => $row['campaign_code'], 'code' => $row['code']],
                array_merge($row, ['is_active' => true]),
            );
        }
    }
}
