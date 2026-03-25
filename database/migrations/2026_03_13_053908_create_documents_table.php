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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sme_id'); // FK → sme_profiles.id
            $table->foreign('sme_id')->references('id')->on('sme_profiles')->onDelete('cascade');
            $table->string('name');
            $table->string('original_filename');
            $table->string('type')->nullable(); // PITCH_DECK, FINANCIALS, LEGAL
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('file_url');
            $table->boolean('is_verified')->default(false);
            $table->unsignedBigInteger('verified_by_user_id')->nullable();
            $table->foreign('verified_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
