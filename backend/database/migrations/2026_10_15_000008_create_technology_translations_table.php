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
        Schema::create('technology_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technology_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('display_name', 60)->nullable();
            $table->string('description', 300)->nullable();
            $table->timestamps();
            $table->unique(['technology_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('technology_translations');
    }
};
