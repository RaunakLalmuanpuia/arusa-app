<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShippingZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-admin');
    }

    /**
     * The admin types pincodes as free text; validate them as a list.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'pincodes' => array_values(array_unique(
                preg_split('/[\s,]+/', (string) $this->input('pincodes'), -1, PREG_SPLIT_NO_EMPTY),
            )),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'rate' => ['required', 'integer', 'min:0', 'max:10000000'],
            'free_above' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'cod_allowed' => ['required', 'boolean'],
            'cod_max' => ['nullable', 'integer', 'min:100', 'max:10000000'],
            'is_active' => ['required', 'boolean'],
            'pincodes' => ['required', 'array', 'min:1', 'max:1000'],
            'pincodes.*' => [
                'digits:6',
                Rule::unique('shipping_zone_pincodes', 'pincode')->ignore($this->route('shipping_zone')?->id, 'shipping_zone_id'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pincodes.*.digits' => 'Pincode :input must be 6 digits.',
            'pincodes.*.unique' => 'Pincode :input already belongs to another zone.',
        ];
    }
}
