<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 10)->nullable()->after('email');
        });

        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedInteger('rate');
            $table->unsignedInteger('free_above')->nullable();
            $table->boolean('cod_allowed')->default(true);
            $table->unsignedInteger('cod_max')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shipping_zone_pincodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->index()->constrained()->cascadeOnDelete();
            $table->string('pincode', 6)->unique();
            $table->timestamps();
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('phone', 10);
            $table->string('line1', 200);
            $table->string('locality', 100);
            $table->string('district', 30);
            $table->string('pincode', 6);
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->index()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();
            $table->unique(['user_id', 'product_variant_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('customer_name', 100);
            $table->string('customer_phone', 10);
            $table->string('customer_email', 150)->nullable();
            $table->string('ship_line1', 200);
            $table->string('ship_locality', 100);
            $table->string('ship_district', 30);
            $table->string('ship_pincode', 6);
            $table->foreignId('shipping_zone_id')->index()->constrained()->restrictOnDelete();
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('shipping');
            $table->unsignedInteger('total');
            $table->string('payment_method', 20);
            $table->string('payment_status', 20);
            $table->string('fulfilment_status', 20)->index();
            $table->text('tracking_note')->nullable();
            $table->timestamp('placed_at');
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->index()->constrained()->restrictOnDelete();
            $table->string('product_name');
            $table->string('size', 30)->nullable();
            $table->string('colour', 30)->nullable();
            $table->unsignedInteger('unit_price');
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('shipping_zone_pincodes');
        Schema::dropIfExists('shipping_zones');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
