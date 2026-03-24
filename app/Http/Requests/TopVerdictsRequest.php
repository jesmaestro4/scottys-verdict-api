<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TopVerdictsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vehicle_type_id' => ['nullable', 'integer'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'page_size' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ];
    }
}
