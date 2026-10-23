<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-admin');
    }

    /**
     * The column is NOT NULL so full-text search never sees a null description.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['description' => (string) $this->input('description')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:150', Rule::unique('products', 'slug')->ignore($this->route('product'))],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'description' => ['string', 'max:5000'],
            'base_price' => ['required', 'integer', 'min:100', 'max:10000000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
