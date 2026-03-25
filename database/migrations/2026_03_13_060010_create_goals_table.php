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
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sme_id'); // FK → sme_profiles.id
            $table->foreign('sme_id')->references('id')->on('sme_profiles')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('pillar_id')->nullable();
            $table->foreign('pillar_id')->references('id')->on('pillars')->onDelete('set null');
            $table->string('status')->default('Not Started'); // ACTIVE, COMPLETED, ARCHIVED
            $table->date('due_date')->nullable();
            $table->integer('progress_percentage')->default(0);
            $table->decimal('target_score', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
