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
        Schema::create('project_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title', 150);
            $table->string('slug', 160);
            $table->string('short_description', 300);
            $table->text('description');
            $table->text('problem')->nullable();
            $table->text('solution')->nullable();
            $table->string('cover_image_alt', 200)->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_translations');
    }
};
