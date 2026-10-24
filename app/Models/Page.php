<?php

namespace App\Models;

use App\Enums\PageBlockType;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\CommonMark\Extension\Table\TableExtension;

#[Fillable(['slug', 'title', 'blocks'])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    /**
     * Widths each page image is resized to. Files are stored as "{path}-{width}.webp".
     *
     * @var list<int>
     */
    public const IMAGE_WIDTHS = [800, 1600];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blocks' => 'array',
        ];
    }

    public static function imageUrl(string $path, int $width = 800): string
    {
        return Storage::disk('public')->url("{$path}-{$width}.webp");
    }

    /**
     * Blocks ready for the storefront: Markdown rendered to safe HTML, image paths turned into URLs and contact links built.
     *
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    public function renderedBlocks(): array
    {
        return array_map(fn (array $block): array => [
            'type' => $block['type'],
            'data' => $this->renderedData(PageBlockType::from($block['type']), $block['data']),
        ], $this->blocks);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function renderedData(PageBlockType $type, array $data): array
    {
        return match ($type) {
            PageBlockType::Hero => [...$data, 'image' => isset($data['image']) ? self::image($data['image']) : null],
            PageBlockType::Text => ['html' => self::markdown($data['markdown'])],
            PageBlockType::ImageText => [
                ...Arr::except($data, 'markdown'),
                'image' => self::image($data['image']),
                'html' => self::markdown($data['markdown']),
            ],
            PageBlockType::Faq => [...$data, 'items' => array_map(fn (array $item): array => [
                'question' => $item['question'],
                'html' => self::markdown($item['answer']),
            ], $data['items'])],
            PageBlockType::ContactCards => [...$data, 'items' => array_map(fn (array $item): array => [
                ...$item,
                'href' => self::contactHref($item),
            ], $data['items'])],
            default => $data,
        };
    }

    /**
     * Markdown rendered to HTML with raw HTML and unsafe links removed.
     */
    private static function markdown(string $markdown): string
    {
        return (string) Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ], [new TableExtension]);
    }

    /**
     * @return array{src: string, srcset: string}
     */
    private static function image(string $path): array
    {
        return [
            'src' => self::imageUrl($path),
            'srcset' => collect(self::IMAGE_WIDTHS)
                ->map(fn (int $width): string => self::imageUrl($path, $width)." {$width}w")
                ->implode(', '),
        ];
    }

    /**
     * @param  array{kind: string, value: string}  $item
     */
    private static function contactHref(array $item): ?string
    {
        return match ($item['kind']) {
            'phone' => "tel:+91{$item['value']}",
            'whatsapp' => "https://wa.me/91{$item['value']}",
            'email' => "mailto:{$item['value']}",
            default => null,
        };
    }
}
