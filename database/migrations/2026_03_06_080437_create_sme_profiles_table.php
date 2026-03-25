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
        Schema::create('sme_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('company_name')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('industry')->nullable();
            $table->string('stage')->nullable();
            $table->string('years_in_business')->nullable();
            $table->string('team_size')->nullable();
            $table->string('address')->nullable();
            $table->decimal('readiness_score', 5, 2)->nullable();
            $table->string('risk_level')->nullable();
            // Schema Design Guide additions
            $table->date('founding_date')->nullable();
            $table->string('website_url')->nullable();
            $table->unsignedBigInteger('verified_by_user_id')->nullable();
            $table->foreign('verified_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('verification_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sme_profiles');
    }
};
