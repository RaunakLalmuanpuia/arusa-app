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
     * Base rules plus each submitted block's own rules, picked by its type.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:100', Rule::unique('pages', 'slug')->ignore($this->route('page'))],
            'blocks' => ['required', 'list', 'max:30'],
            'blocks.*.type' => ['required', Rule::enum(PageBlockType::class)],
            'blocks.*.data' => ['required', 'array'],
        ];

        foreach ((array) $this->input('blocks') as $index => $block) {
            $type = is_array($block) && is_string($block['type'] ?? null) ? PageBlockType::tryFrom($block['type']) : null;

            if ($type !== null) {
                $rules += $type->rules("blocks.{$index}", (array) ($block['data'] ?? []));
            }
        }

        return $rules;
    }

    /**
     * Validated data with blocks restored to submitted order: rule expansion order (exact keys before
     * wildcard ones) otherwise decides each block's position, which can store the list as a JSON object.
     *
     * @param  array-key|null  $key
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated();
        ksort($validated['blocks']);
        $validated['blocks'] = array_values($validated['blocks']);

        return data_get($validated, $key, $default);
    }

    /**
     * Name block fields by position, e.g. "blocks.1.data.items.*.title" becomes "block 2 items title".
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect($this->rules())
            ->keys()
            ->filter(fn (string $key): bool => str_starts_with($key, 'blocks.') && str_contains($key, '.data.'))
            ->mapWithKeys(fn (string $key): array => [$key => preg_replace_callback(
                '/^blocks\.(\d+)\.data\.(.+)$/',
                fn (array $match): string => 'block '.($match[1] + 1).' '.str_replace(['.*', '.', '_'], ['', ' ', ' '], $match[2]),
                $key,
            )])
            ->all();
    }
}
