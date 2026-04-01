<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            if (!Schema::hasColumn('goals', 'proof_verified')) {
                $table->boolean('proof_verified')->default(false)->after('proof_document');
            }
            if (!Schema::hasColumn('goals', 'verified_by')) {
                $table->unsignedBigInteger('verified_by')->nullable()->after('proof_verified');
            }
            if (!Schema::hasColumn('goals', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('verified_by');
            }
            if (!Schema::hasColumn('goals', 'rejection_note')) {
                $table->text('rejection_note')->nullable()->after('verified_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->dropColumn(['proof_verified', 'verified_by', 'verified_at', 'rejection_note']);
        });
    }
};
