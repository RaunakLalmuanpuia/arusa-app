<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductImagesRequest extends FormRequest
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
        return [
            'images' => ['required', 'array'],
            'images.*.id' => ['required', 'integer', 'distinct', Rule::exists('product_images', 'id')->where('product_id', $this->route('product')->id)],
            'images.*.alt' => ['nullable', 'string', 'max:150'],
            'images.*.colour' => ['nullable', 'string', 'max:30'],
        ];
    }
}
