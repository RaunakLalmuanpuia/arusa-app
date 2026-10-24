<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

class PagePreviewRequest extends PageRequest
{
    /**
     * Saving's block rules without the page's own title and slug.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return Arr::except(parent::rules(), ['title', 'slug']);
    }
}
