<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductVariantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-admin');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'variants' => ['required', 'array', 'min:1', 'max:100'],
            'variants.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('product_variants', 'id')->where('product_id', $this->route('product')->id)],
            'variants.*.size' => ['nullable', 'string', 'max:30'],
            'variants.*.colour' => ['nullable', 'string', 'max:30'],
            'variants.*.sku' => ['required', 'string', 'max:50', 'distinct:ignore_case'],
            'variants.*.price_override' => ['nullable', 'integer', 'min:100', 'max:10000000'],
            'variants.*.stock' => ['required', 'integer', 'min:0', 'max:100000'],
            'variants.*.stock_was' => ['nullable', 'integer', 'min:0'],
            'variants.*.is_active' => ['required', 'boolean'],
        ];

        foreach ((array) $this->input('variants') as $index => $variant) {
            $rules["variants.{$index}.sku"][] = Rule::unique('product_variants', 'sku')
                ->ignore(is_array($variant) ? ($variant['id'] ?? null) : null);
        }

        return $rules;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $combinations = collect((array) $this->input('variants'))
                    ->map(fn (mixed $variant): string => is_array($variant)
                        ? mb_strtolower(($variant['size'] ?? '').'|'.($variant['colour'] ?? ''))
                        : '');

                if ($combinations->duplicates()->isNotEmpty()) {
                    $validator->errors()->add('variants', 'Each size and colour combination can appear only once.');
                }
            },
        ];
    }
}
