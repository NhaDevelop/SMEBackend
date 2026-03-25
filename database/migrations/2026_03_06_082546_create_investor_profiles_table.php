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
        Schema::create('investor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('organization_name')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('investor_type')->nullable();
            $table->string('industry')->nullable();
            $table->string('years_in_business')->nullable();
            $table->string('team_size')->nullable();
            $table->text('address')->nullable();
            $table->decimal('min_ticket_size', 15, 2)->nullable();
            $table->decimal('max_ticket_size', 15, 2)->nullable();
            $table->json('preferred_industries')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investor_profiles');
    }
};
