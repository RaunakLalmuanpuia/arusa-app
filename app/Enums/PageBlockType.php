<?php

namespace App\Enums;

use Closure;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

enum PageBlockType: string
{
    case Hero = 'hero';
    case Text = 'text';
    case ImageText = 'image_text';
    case Features = 'features';
    case Table = 'table';
    case Faq = 'faq';
    case ContactCards = 'contact_cards';
    case CallToAction = 'call_to_action';

    /**
     * Icon names a feature item may use; each maps to a component in resources/js/Components/Icons/.
     *
     * @var list<string>
     */
    public const ICONS = ['cotton', 'feather', 'puff-print', 'shield', 'chat', 'bag'];

    /**
     * @var list<string>
     */
    public const CONTACT_KINDS = ['phone', 'whatsapp', 'email', 'address', 'hours'];

    /**
     * Validation rules for this block's data, keyed under "{$prefix}.data".
     *
     * @param  array<string, mixed>  $input  The submitted data, for rules that depend on other fields.
     * @return array<string, array<mixed>>
     */
    public function rules(string $prefix, array $input): array
    {
        $data = "{$prefix}.data";
        $heading = ['nullable', 'string', 'max:120'];

        return match ($this) {
            self::Hero => [
                "{$data}.eyebrow" => ['nullable', 'string', 'max:60'],
                "{$data}.heading" => ['required', 'string', 'max:120'],
                "{$data}.intro" => ['nullable', 'string', 'max:300'],
                "{$data}.image" => ['nullable', ...self::imageRules()],
            ],
            self::Text => [
                "{$data}.markdown" => ['required', 'string', 'max:20000'],
            ],
            self::ImageText => [
                "{$data}.image" => ['required', ...self::imageRules()],
                "{$data}.image_alt" => ['nullable', 'string', 'max:150'],
                "{$data}.image_side" => ['required', Rule::in(['left', 'right'])],
                "{$data}.heading" => $heading,
                "{$data}.markdown" => ['required', 'string', 'max:5000'],
            ],
            self::Features => [
                "{$data}.heading" => $heading,
                "{$data}.items" => ['required', 'array', 'min:2', 'max:4'],
                "{$data}.items.*.icon" => ['required', Rule::in(self::ICONS)],
                "{$data}.items.*.title" => ['required', 'string', 'max:60'],
                "{$data}.items.*.text" => ['required', 'string', 'max:200'],
            ],
            self::Table => [
                "{$data}.heading" => $heading,
                "{$data}.columns" => ['required', 'array', 'min:2', 'max:6'],
                "{$data}.columns.*" => ['required', 'string', 'max:60'],
                "{$data}.rows" => ['required', 'array', 'min:1', 'max:30'],
                "{$data}.rows.*" => ['required', 'array', 'size:'.count((array) ($input['columns'] ?? []))],
                "{$data}.rows.*.*" => ['nullable', 'string', 'max:60'],
                "{$data}.note" => ['nullable', 'string', 'max:300'],
            ],
            self::Faq => [
                "{$data}.heading" => $heading,
                "{$data}.items" => ['required', 'array', 'min:1', 'max:20'],
                "{$data}.items.*.question" => ['required', 'string', 'max:200'],
                "{$data}.items.*.answer" => ['required', 'string', 'max:2000'],
            ],
            self::ContactCards => [
                "{$data}.heading" => $heading,
                "{$data}.items" => ['required', 'array', 'min:1', 'max:6'],
                "{$data}.items.*.kind" => ['required', Rule::in(self::CONTACT_KINDS)],
                "{$data}.items.*.label" => ['required', 'string', 'max:60'],
                "{$data}.items.*.value" => ['required', 'string', 'max:200'],
                ...self::contactValueRules($data, $input['items'] ?? []),
            ],
            self::CallToAction => [
                "{$data}.heading" => ['required', 'string', 'max:120'],
                "{$data}.text" => ['nullable', 'string', 'max:300'],
                "{$data}.button_label" => ['required', 'string', 'max:40'],
                // A site path ("/x", not "//host") or an https URL.
                "{$data}.button_url" => ['required', 'string', 'max:300', 'regex:#^(/(?!/)|https://)#'],
            ],
        };
    }

    /**
     * A path returned by the page image upload whose 800 px file exists.
     *
     * @return list<mixed>
     */
    private static function imageRules(): array
    {
        return [
            'bail',
            'string',
            'regex:#^pages/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$#',
            function (string $attribute, string $value, Closure $fail): void {
                if (! Storage::disk('public')->exists("{$value}-800.webp")) {
                    $fail('Upload the :attribute again.');
                }
            },
        ];
    }

    /**
     * Phone and WhatsApp values are 10-digit Indian mobiles; email values are emails.
     *
     * @return array<string, list<string>>
     */
    private static function contactValueRules(string $data, mixed $items): array
    {
        return collect(is_array($items) ? $items : [])
            ->mapWithKeys(fn (mixed $item, int|string $index): array => [
                "{$data}.items.{$index}.value" => match (is_array($item) ? ($item['kind'] ?? null) : null) {
                    'phone', 'whatsapp' => ['regex:/^[6-9]\d{9}$/'],
                    'email' => ['email'],
                    default => [],
                },
            ])
            ->all();
    }
}
