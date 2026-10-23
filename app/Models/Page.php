<?php

namespace App\Models;

use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use League\CommonMark\Extension\Table\TableExtension;

#[Fillable(['slug', 'title', 'body'])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    /**
     * Markdown body rendered to HTML with raw HTML and unsafe links removed.
     */
    public function renderedBody(): string
    {
        return (string) Str::markdown($this->body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ], [new TableExtension]);
    }
}
