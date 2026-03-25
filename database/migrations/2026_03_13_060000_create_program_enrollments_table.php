<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('sme_id');
            $table->foreign('sme_id')->references('id')->on('sme_profiles')->onDelete('cascade');
            $table->string('status')->default('Applied'); // Applied, Accepted, Rejected
            $table->timestamp('enrollment_date')->nullable();
            $table->timestamps();

            $table->unique(['program_id', 'sme_id']); // Prevent duplicate enrollments
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_enrollments');
    }
};
