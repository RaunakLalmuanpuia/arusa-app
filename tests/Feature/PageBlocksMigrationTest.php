<?php

use App\Models\Page;
use Illuminate\Support\Facades\DB;

test('existing page bodies become a single text block', function () {
    $migration = require collect(glob(database_path('migrations/*_add_blocks_to_pages_table.php')))->sole();

    $migration->down();
    DB::table('pages')->insert(['slug' => 'about', 'title' => 'About', 'body' => 'Made in **Aizawl**.', 'created_at' => now(), 'updated_at' => now()]);
    $migration->up();

    expect(Page::firstWhere('slug', 'about')->blocks)->toBe([['type' => 'text', 'data' => ['markdown' => 'Made in **Aizawl**.']]]);
});
