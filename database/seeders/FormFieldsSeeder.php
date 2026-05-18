<?php

namespace Database\Seeders;

use App\Services\FormFieldsSchemaSyncService;
use Illuminate\Database\Seeder;

class FormFieldsSeeder extends Seeder
{
    public function run(): void
    {
        /** @var FormFieldsSchemaSyncService $sync */
        $sync = app(FormFieldsSchemaSyncService::class);
        $sync->syncAllFromRegisteredForms();
    }
}
