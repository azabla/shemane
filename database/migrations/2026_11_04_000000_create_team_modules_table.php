<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which installed modules the platform offers to teams. No row means not offered.
        Schema::create('module_availability', function (Blueprint $table) {
            $table->id();
            $table->string('module', 100)->unique();
            $table->boolean('available')->default(false);
            $table->timestamps();
        });

        // A team's choice and settings; every store of the team inherits them.
        Schema::create('team_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('module', 100);
            $table->boolean('enabled')->default(false);
            $table->text('settings')->nullable();
            $table->timestamps();
            $table->unique(['team_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_modules');
        Schema::dropIfExists('module_availability');
    }
};
