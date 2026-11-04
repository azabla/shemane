<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_fulfilments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier', 50);
            // Sent to the supplier on every attempt, so a retry after a timeout cannot place a second order.
            $table->string('reference')->unique();
            $table->string('status', 20);
            $table->string('supplier_reference')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('carrier')->nullable();
            $table->string('tracking_url')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('response')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'supplier']);
            $table->index(['status', 'supplier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_fulfilments');
    }
};
