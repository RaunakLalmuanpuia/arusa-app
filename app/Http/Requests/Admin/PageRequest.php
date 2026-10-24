<?php

namespace App\Http\Requests\Admin;

use App\Enums\PageBlockType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PageRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:100', Rule::unique('pages', 'slug')->ignore($this->route('page'))],
            'blocks' => ['required', 'list', 'max:30'],
            'blocks.*.type' => ['required', Rule::enum(PageBlockType::class)],
            'blocks.*.data' => ['required', 'array'],
        ];
    }
}
