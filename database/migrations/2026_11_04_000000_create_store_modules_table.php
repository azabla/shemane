<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('module', 100);
            $table->boolean('available')->default(false);
            $table->boolean('enabled')->default(false);
            $table->text('settings')->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_modules');
    }
};
