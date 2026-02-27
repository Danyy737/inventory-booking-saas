<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreviewBookingAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tenant + auth middleware already protects this endpoint.
        // If you want stricter role gates later, we can add them here.
        return true;
    }

    public function rules(): array
    {
        return [
            'start_at' => ['required', 'date'],
            'end_at'   => ['required', 'date', 'after:start_at'],

            'packages' => ['sometimes', 'array'],
            'packages.*.package_id' => ['required', 'integer', 'exists:packages,id'],
            'packages.*.quantity'   => ['required', 'integer', 'min:1'],

            'items' => ['sometimes', 'array'],
            'items.*.inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'items.*.quantity'          => ['required', 'integer', 'min:1'],

            'addons' => ['sometimes', 'array'],
'addons.*.addon_id' => ['required_with:addons', 'integer'],
'addons.*.quantity' => ['required_with:addons', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_at.after' => 'end_at must be after start_at.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Normalize missing arrays so controller logic can safely iterate.
        $this->merge([
            'packages' => $this->input('packages', []),
            'items' => $this->input('items', []),
        ]);
    }

    
}
