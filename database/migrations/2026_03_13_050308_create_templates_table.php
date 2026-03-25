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
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('version')->default('v1.0');
            $table->string('industry')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('Draft'); // Draft, Active, Archived
            $table->json('settings')->nullable(); // For pillars, indicators, thresholds
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
