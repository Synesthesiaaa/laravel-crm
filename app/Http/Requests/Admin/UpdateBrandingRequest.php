<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'company_name' => [
                'required',
                'string',
                'max:'.config('branding.max_company_name_length', 120),
            ],
            'logo' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:'.config('branding.max_logo_kilobytes', 5120),
            ],
            'favicon' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp,ico',
                'mimetypes:image/jpeg,image/png,image/webp,image/x-icon,image/vnd.microsoft.icon',
                'max:'.config('branding.max_favicon_kilobytes', 2048),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_name.required' => 'Enter a company name.',
            'company_name.max' => 'The company name may not be longer than :max characters.',
            'logo.mimes' => 'The logo must be a PNG, JPG, JPEG, or WebP image.',
            'logo.mimetypes' => 'The logo must be a valid raster image.',
            'logo.max' => 'The logo must be smaller than :max KB.',
            'favicon.mimes' => 'The favicon must be a PNG, JPG, JPEG, WebP, or ICO image.',
            'favicon.mimetypes' => 'The favicon must be a valid raster image or ICO file.',
            'favicon.max' => 'The favicon must be smaller than :max KB.',
        ];
    }
}
