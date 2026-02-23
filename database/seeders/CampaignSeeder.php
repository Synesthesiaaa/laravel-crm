<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Form;
use Illuminate\Database\Seeder;

class CampaignSeeder extends Seeder
{
    public function run(): void
    {
        $campaigns = [
            ['code' => 'mbsales', 'name' => 'MBSales', 'description' => 'Main MBSales Campaign', 'color' => 'blue', 'display_order' => 0],
            ['code' => 'pjli', 'name' => 'PJLI', 'description' => 'PJLI Campaign', 'color' => 'indigo', 'display_order' => 1],
        ];
        foreach ($campaigns as $c) {
            Campaign::updateOrCreate(['code' => $c['code']], $c);
        }

        $forms = [
            ['campaign_code' => 'mbsales', 'form_code' => 'ezycash', 'name' => 'EzyCash', 'table_name' => 'ezycash', 'color' => 'green', 'icon' => 'cash', 'display_order' => 1],
            ['campaign_code' => 'mbsales', 'form_code' => 'ezyconvert', 'name' => 'EzyConvert', 'table_name' => 'ezyconvert', 'color' => 'blue', 'icon' => 'convert', 'display_order' => 2],
            ['campaign_code' => 'mbsales', 'form_code' => 'ezytransfer', 'name' => 'EzyTransfer', 'table_name' => 'ezytransfer', 'color' => 'purple', 'icon' => 'transfer', 'display_order' => 3],
            ['campaign_code' => 'pjli', 'form_code' => 'cycle', 'name' => 'Cycle', 'table_name' => 'pjli_cycle', 'color' => 'green', 'icon' => 'cycle', 'display_order' => 1],
            ['campaign_code' => 'pjli', 'form_code' => 'winback', 'name' => 'Winback', 'table_name' => 'pjli_winback', 'color' => 'orange', 'icon' => 'winback', 'display_order' => 2],
            ['campaign_code' => 'pjli', 'form_code' => 'renewal', 'name' => 'Renewal', 'table_name' => 'pjli_renewal', 'color' => 'blue', 'icon' => 'renewal', 'display_order' => 3],
            ['campaign_code' => 'pjli', 'form_code' => 'ofw', 'name' => 'OFW', 'table_name' => 'pjli_ofw', 'color' => 'purple', 'icon' => 'ofw', 'display_order' => 4],
        ];
        foreach ($forms as $f) {
            Form::updateOrCreate(
                ['campaign_code' => $f['campaign_code'], 'form_code' => $f['form_code']],
                $f
            );
        }
    }
}
