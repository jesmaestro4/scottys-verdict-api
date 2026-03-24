<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class NearbyListingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'zip' => ['nullable', 'digits:5'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page_size' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'make' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasZip = $this->filled('zip');
            $hasCoordinates = $this->filled('latitude') && $this->filled('longitude');

            if (! $hasZip && ! $hasCoordinates) {
                $validator->errors()->add('zip', 'Provide either a zip code or latitude and longitude.');
            }
        });
    }
}
