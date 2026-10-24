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
                "{$data}.items" => ['required', 'list', 'min:2', 'max:4'],
                "{$data}.items.*.icon" => ['required', Rule::in(self::ICONS)],
                "{$data}.items.*.title" => ['required', 'string', 'max:60'],
                "{$data}.items.*.text" => ['required', 'string', 'max:200'],
            ],
            self::Table => [
                "{$data}.heading" => $heading,
                "{$data}.columns" => ['required', 'list', 'min:2', 'max:6'],
                "{$data}.columns.*" => ['required', 'string', 'max:60'],
                "{$data}.rows" => ['required', 'list', 'min:1', 'max:30'],
                "{$data}.rows.*" => ['required', 'list', 'size:'.count((array) ($input['columns'] ?? []))],
                "{$data}.rows.*.*" => ['nullable', 'string', 'max:60'],
                "{$data}.note" => ['nullable', 'string', 'max:300'],
            ],
            self::Faq => [
                "{$data}.heading" => $heading,
                "{$data}.items" => ['required', 'list', 'min:1', 'max:20'],
                "{$data}.items.*.question" => ['required', 'string', 'max:200'],
                "{$data}.items.*.answer" => ['required', 'string', 'max:2000'],
            ],
            self::ContactCards => [
                "{$data}.heading" => $heading,
                "{$data}.items" => ['required', 'list', 'min:1', 'max:6'],
                "{$data}.items.*.kind" => ['required', Rule::in(self::CONTACT_KINDS)],
                "{$data}.items.*.label" => ['required', 'string', 'max:60'],
                "{$data}.items.*.value" => ['required', 'string', 'max:200', self::contactValueRule($input)],
            ],
            self::CallToAction => [
                "{$data}.heading" => ['required', 'string', 'max:120'],
                "{$data}.text" => ['nullable', 'string', 'max:300'],
                "{$data}.button_label" => ['required', 'string', 'max:40'],
                // A site path ("/x", not "//host") or an https URL; no backslashes or whitespace, which browsers treat as slashes.
                "{$data}.button_url" => ['required', 'string', 'max:300', 'regex:#^(/(?![/\\\\])|https://)[^\s\\\\]*$#'],
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
     * @param  array<string, mixed>  $input  The block's submitted data.
     */
    private static function contactValueRule(array $input): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($input): void {
            if (! is_string($value)) {
                return;
            }

            preg_match('/\.items\.(\d+)\.value$/', $attribute, $match);
            $kind = $input['items'][$match[1] ?? '']['kind'] ?? null;

            if (in_array($kind, ['phone', 'whatsapp'], true) && ! preg_match('/^[6-9]\d{9}$/', (string) $value)) {
                $fail('The :attribute must be a 10-digit mobile number.');
            } elseif ($kind === 'email' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $fail('The :attribute must be a valid email address.');
            }
        };
    }
}
