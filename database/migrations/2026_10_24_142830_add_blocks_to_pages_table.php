<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->json('blocks')->nullable()->after('title');
        });

        DB::table('pages')->orderBy('id')->each(function (object $page): void {
            DB::table('pages')->where('id', $page->id)->update([
                'blocks' => json_encode([['type' => 'text', 'data' => ['markdown' => $page->body]]]),
            ]);
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->json('blocks')->nullable(false)->change();
            $table->dropColumn('body');
        });
    }

    /**
     * Reverse the migrations. Only text blocks survive; other block types are lost.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->text('body')->nullable()->after('title');
        });

        DB::table('pages')->orderBy('id')->each(function (object $page): void {
            DB::table('pages')->where('id', $page->id)->update([
                'body' => collect(json_decode($page->blocks, true))
                    ->where('type', 'text')
                    ->pluck('data.markdown')
                    ->implode("\n\n"),
            ]);
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->text('body')->nullable(false)->change();
            $table->dropColumn('blocks');
        });
    }
};
