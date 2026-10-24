<?php

namespace Database\Seeders;

use App\Actions\StoreWebpImage;
use App\Models\Page;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class PageSeeder extends Seeder
{
    public function __construct(private StoreWebpImage $storeWebpImage) {}

    /**
     * Create the content pages, or fill ones still holding placeholder text. Pages edited in the admin are left alone.
     *
     * Placeholders to confirm in the admin before launch: contact numbers and email, the size chart, the returns window.
     */
    public function run(): void
    {
        $pages = [
            'about' => ['About Arusa', $this->aboutBlocks(...)],
            'contact' => ['Contact', $this->contactBlocks(...)],
            'size-guide' => ['Size Guide', $this->sizeGuideBlocks(...)],
            'shipping-returns' => ['Shipping & Returns', $this->shippingBlocks(...)],
        ];

        foreach ($pages as $slug => [$title, $blocks]) {
            $page = Page::firstWhere('slug', $slug);

            if ($page !== null && ! $this->isPlaceholder($page)) {
                continue;
            }

            Page::updateOrCreate(['slug' => $slug], ['title' => $title, 'blocks' => $blocks()]);
        }
    }

    private function isPlaceholder(Page $page): bool
    {
        return count($page->blocks) === 1
            && $page->blocks[0]['type'] === 'text'
            && str_contains($page->blocks[0]['data']['markdown'], 'Edit this page in the admin.');
    }

    /**
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    private function aboutBlocks(): array
    {
        $photo = $this->storePhoto();

        return array_values(array_filter([
            ['type' => 'hero', 'data' => [
                'eyebrow' => 'Our story',
                'heading' => 'Made in Aizawl, worn everywhere',
                'intro' => 'Arusa is a small streetwear label from Aizawl, Mizoram, making heavyweight tees and everyday pieces we actually want to wear.',
            ]],
            $photo === null ? null : ['type' => 'image_text', 'data' => [
                'image' => $photo,
                'image_alt' => 'Arusa tees',
                'image_side' => 'left',
                'heading' => 'Started small, on purpose',
                'markdown' => "Arusa began in 2026 with one idea: clothes from Mizoram that hold up next to anything from the big cities.\n\nWe keep our runs small, finish our designs in raised puff print, and check every piece before it ships. When something sells out, we would rather make it again properly than rush a cheaper version.",
            ]],
            ['type' => 'features', 'data' => [
                'heading' => 'What goes into every piece',
                'items' => [
                    ['icon' => 'cotton', 'title' => 'Premium cotton', 'text' => 'Soft, heavyweight fabric that keeps its shape wash after wash.'],
                    ['icon' => 'puff-print', 'title' => 'Puff print detail', 'text' => 'Raised prints you can feel, made to last.'],
                    ['icon' => 'feather', 'title' => 'Comfort fit', 'text' => 'Relaxed cuts made for long days in the hills.'],
                    ['icon' => 'shield', 'title' => 'Checked by hand', 'text' => 'Every order is inspected before it leaves us.'],
                ],
            ]],
            ['type' => 'call_to_action', 'data' => [
                'heading' => 'Find your next favourite tee',
                'text' => 'New drops land in small batches. When they are gone, they are gone.',
                'button_label' => 'Shop now',
                'button_url' => '/',
            ]],
        ]));
    }

    /**
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    private function contactBlocks(): array
    {
        return [
            ['type' => 'hero', 'data' => [
                'eyebrow' => 'Contact',
                'heading' => 'We’re here to help',
                'intro' => 'Questions about sizing, an order or a delivery? Reach us the way that suits you and we’ll get back to you as soon as we can.',
            ]],
            ['type' => 'contact_cards', 'data' => [
                'heading' => 'Get in touch',
                'items' => [
                    ['kind' => 'whatsapp', 'label' => 'WhatsApp', 'value' => '9000000000'],
                    ['kind' => 'phone', 'label' => 'Call us', 'value' => '9000000000'],
                    ['kind' => 'email', 'label' => 'Email', 'value' => 'hello@example.com'],
                    ['kind' => 'address', 'label' => 'Based in', 'value' => 'Aizawl, Mizoram'],
                    ['kind' => 'hours', 'label' => 'Hours', 'value' => 'Monday to Saturday, 10am – 6pm'],
                ],
            ]],
            ['type' => 'faq', 'data' => [
                'heading' => 'Order help',
                'items' => [
                    ['question' => 'How do I track my order?', 'answer' => 'Use [Track order](/track) with your order number and phone number. If you have an account, every order is also listed there.'],
                    ['question' => 'Can I change or cancel my order?', 'answer' => 'Message us as soon as you can. We can change or cancel an order until it has been dispatched.'],
                    ['question' => 'Which payment methods do you accept?', 'answer' => 'Cash on delivery where available, and online payment through Razorpay (UPI, cards and netbanking).'],
                ],
            ]],
        ];
    }

    /**
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    private function sizeGuideBlocks(): array
    {
        return [
            ['type' => 'hero', 'data' => [
                'eyebrow' => 'Fit',
                'heading' => 'Size guide',
                'intro' => 'Our tees have a relaxed fit. Between sizes? Take the smaller one for a closer fit or the larger one for a roomier look.',
            ]],
            ['type' => 'text', 'data' => [
                'markdown' => "## How to measure\n\n- **Chest:** measure around the fullest part of your chest, keeping the tape level under your arms.\n- **Length:** measure from the highest point of the shoulder down to the hem.\n- **Shoulder:** measure straight across the back from one shoulder seam to the other.\n\nEasiest of all: lay a tee you already love flat, measure it, and compare it with the table below.",
            ]],
            ['type' => 'table', 'data' => [
                'heading' => 'Tees, in inches',
                'columns' => ['Size', 'Chest', 'Length', 'Shoulder'],
                'rows' => [
                    ['S', '38', '27', '17'],
                    ['M', '40', '28', '18'],
                    ['L', '42', '29', '19'],
                    ['XL', '44', '30', '20'],
                    ['XXL', '46', '31', '21'],
                ],
                'note' => 'Garment measurements, laid flat. Allow up to half an inch either way.',
            ]],
            ['type' => 'faq', 'data' => [
                'heading' => 'Fit questions',
                'items' => [
                    ['question' => 'How should I wash my tee?', 'answer' => 'Wash cold and inside out, and dry in the shade to keep the fabric and print at their best.'],
                    ['question' => 'What if it doesn’t fit?', 'answer' => 'See [Shipping & Returns](/pages/shipping-returns) for how exchanges work.'],
                ],
            ]],
        ];
    }

    /**
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    private function shippingBlocks(): array
    {
        return [
            ['type' => 'hero', 'data' => [
                'eyebrow' => 'Delivery',
                'heading' => 'Shipping & returns',
                'intro' => 'We deliver across Mizoram. Here is how delivery, payment and returns work.',
            ]],
            ['type' => 'features', 'data' => [
                'items' => [
                    ['icon' => 'bag', 'title' => 'Mizoram-wide delivery', 'text' => 'Enter your pincode at checkout to see the delivery charge for your area.'],
                    ['icon' => 'shield', 'title' => 'Pay your way', 'text' => 'Cash on delivery where available, or pay online with UPI, cards or netbanking.'],
                    ['icon' => 'chat', 'title' => 'Real help', 'text' => 'Message us about any order and a real person will sort it out.'],
                ],
            ]],
            ['type' => 'text', 'data' => [
                'markdown' => "## Delivery\n\nOrders are packed in Aizawl and typically delivered within a few working days, depending on your location. You can follow your order on the [tracking page](/track).\n\n## Returns and exchanges\n\nIf something is wrong with your order or the size doesn’t fit, contact us within **7 days** of delivery. Items must be unworn and unwashed, with their tags on.",
            ]],
            ['type' => 'faq', 'data' => [
                'heading' => 'Common questions',
                'items' => [
                    ['question' => 'How much is delivery?', 'answer' => 'It depends on your area. The exact charge is shown at checkout once you enter your pincode.'],
                    ['question' => 'Do you deliver outside Mizoram?', 'answer' => 'Not yet. We currently deliver within Mizoram only.'],
                    ['question' => 'When will I get my refund?', 'answer' => 'Online payments are refunded to the original payment method once the return is approved. Your bank may take a few more days to show it.'],
                ],
            ]],
        ];
    }

    /**
     * Run the bundled hero photo through the page image pipeline; null if the file is missing.
     */
    private function storePhoto(): ?string
    {
        $file = public_path('images/hero-1600.webp');

        if (! is_file($file)) {
            return null;
        }

        $path = 'pages/'.Str::uuid();
        $this->storeWebpImage->handle(new UploadedFile($file, basename($file), test: true), $path, Page::IMAGE_WIDTHS, 'image');

        return $path;
    }
}
