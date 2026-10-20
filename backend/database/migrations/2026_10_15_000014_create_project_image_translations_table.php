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
        Schema::create('project_image_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_image_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('alt_text', 200);
            $table->timestamps();
            $table->unique(['project_image_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_image_translations');
    }
};
