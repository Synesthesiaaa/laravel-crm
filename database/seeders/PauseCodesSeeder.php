<?php

namespace Database\Seeders;

use App\Models\PauseCode;
use Illuminate\Database\Seeder;

class PauseCodesSeeder extends Seeder
{
    public function run(): void
    {
        if (PauseCode::query()->exists()) {
            return;
        }

        $defaults = config('vicidial.pause_codes', ['BREAK', 'LUNCH', 'MEET', 'COACH', 'SYSTEM']);
        $order = 0;
        foreach ($defaults as $code) {
            $code = strtoupper(trim((string) $code));
            if ($code === '') {
                continue;
            }
            PauseCode::query()->create([
                'code' => $code,
                'label' => $code,
                'is_active' => true,
                'sort_order' => $order++,
            ]);
        }
    }
}
