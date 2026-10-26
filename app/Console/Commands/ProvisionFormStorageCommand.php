<?php

namespace App\Console\Commands;

use App\Models\Form;
use App\Repositories\FormFieldRepository;
use App\Services\FormStorageSchemaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProvisionFormStorageCommand extends Command
{
    protected $signature = 'forms:provision-storage';

    protected $description = 'Provision registered dynamic form storage outside submission requests';

    public function handle(FormStorageSchemaService $storage, FormFieldRepository $fields): int
    {
        $forms = Form::query()->orderBy('campaign_code')->orderBy('form_code')->get();
        $failed = 0;

        foreach ($forms as $form) {
            try {
                $storage->provision((string) $form->table_name, $fields->getFieldsForForm(
                    (string) $form->campaign_code,
                    (string) $form->form_code,
                ));
                $this->info("Provisioned form storage for form #{$form->id}.");
            } catch (Throwable $exception) {
                $failed++;
                Log::error('Dynamic form storage provisioning failed.', [
                    'form_id' => $form->id,
                    'exception_class' => $exception::class,
                ]);
                $this->error("Form #{$form->id} could not be provisioned (".$exception::class.').');
            }
        }

        if ($failed > 0) {
            $this->error("{$failed} form storage configuration(s) failed.");

            return self::FAILURE;
        }

        $this->info('Registered form storage is ready.');

        return self::SUCCESS;
    }
}
