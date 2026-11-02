<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funnels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('product_name');
            $table->string('slug')->unique();
            $table->string('template', 60);
            $table->string('status', 20)->default('active');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('compare_at_price', 10, 2);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['brand_id', 'status']);
        });

        Schema::create('funnel_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('phone', 20);
            $table->string('address');
            $table->string('province', 120);
            $table->string('city', 120);
            $table->string('barangay', 120)->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total', 12, 2);
            $table->string('status', 20)->default('new');
            $table->json('source')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index(['funnel_id', 'created_at']);
            $table->index(['funnel_id', 'status']);
            $table->index(['funnel_id', 'phone']);
        });

        $this->addFirstFunnel();
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_orders');
        Schema::dropIfExists('funnels');
    }

    /**
     * The first funnel: Ginger Turmeric, under the Prime Z brand on existing installs.
     */
    private function addFirstFunnel(): void
    {
        $brandId = DB::table('brands')->where('slug', 'prime-z')->value('id')
            ?? DB::table('brands')->orderBy('id')->value('id');

        if (! $brandId) {
            return;
        }

        DB::table('funnels')->insert([
            'brand_id' => $brandId,
            'name' => 'Ginger Turmeric Promo',
            'product_name' => 'Pr1me Ginger Turmeric',
            'slug' => 'pr1mez-gingerturmeric',
            'template' => 'ginger-turmeric',
            'status' => 'active',
            'unit_price' => 890,
            'compare_at_price' => 1112.50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
