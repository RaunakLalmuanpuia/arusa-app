<?php

namespace App\Http\Requests\Admin;

use App\Enums\FulfilmentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(FulfilmentStatus::class)],
            'tracking_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
