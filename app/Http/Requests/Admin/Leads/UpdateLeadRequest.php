<?php

namespace App\Http\Requests\Admin\Leads;

class UpdateLeadRequest extends StoreLeadRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['list_id']);

        return $rules;
    }
}
