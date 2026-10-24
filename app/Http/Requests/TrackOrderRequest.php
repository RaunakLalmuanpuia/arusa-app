<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TrackOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Accepts "ARU-000123", "aru-123" or "123". Nine digits at most keeps the id inside an int.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'regex:/^(aru-?)?0*[1-9]\d{0,8}$/i'],
            'phone' => ['required', 'regex:/^[6-9]\d{9}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'number.regex' => 'Enter your order number, like ARU-000123.',
            'phone.regex' => 'Enter a 10-digit mobile number, without +91.',
        ];
    }

    /**
     * The order id inside the validated order number.
     */
    public function orderId(): int
    {
        return (int) preg_replace('/\D/', '', $this->validated('number'));
    }
}
