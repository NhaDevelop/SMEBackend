<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investor_interests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('investor_id');
            $table->foreign('investor_id')->references('id')->on('investor_profiles')->onDelete('cascade');
            $table->unsignedBigInteger('sme_id');
            $table->foreign('sme_id')->references('id')->on('sme_profiles')->onDelete('cascade');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['investor_id', 'sme_id']); // One watchlist entry per pair
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investor_interests');
    }
};
