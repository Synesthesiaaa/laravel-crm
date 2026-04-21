<?php

namespace App\Repositories;

use App\Contracts\Repositories\FormSubmissionRepositoryInterface;
use Illuminate\Support\Facades\DB;

class FormSubmissionRepository implements FormSubmissionRepositoryInterface
{
    public function __construct(
        protected CampaignRepository $campaignRepository,
    ) {}

    public function insert(string $tableName, array $columnsAndValues): int
    {
        $allowed = $this->campaignRepository->getAllFormTableNames();
        if (! in_array($tableName, $allowed, true)) {
            throw new \InvalidArgumentException('Table not allowed for form submission.');
        }
        $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);

        return (int) DB::table($tableName)->insertGetId($columnsAndValues);
    }
}
