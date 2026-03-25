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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained()->onDelete('cascade');
            $table->string('pillar_id'); // team, market, finance, etc.
            $table->text('text');
            $table->string('type')->default('Yes/No'); 
            $table->decimal('weight', 5, 2)->default(0);
            $table->boolean('required')->default(true);
            $table->json('options')->nullable(); // For multiple choice options
            $table->text('helper_text')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
