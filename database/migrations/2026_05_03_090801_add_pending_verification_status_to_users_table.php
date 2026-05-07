<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite does not support ALTER COLUMN for enums.
        // We handle this by using a string column check via DB statement for MySQL,
        // or by simply accepting the string value for SQLite (which doesn't enforce enums).
        // The application-level validation in the controller will enforce valid values.

        // For MySQL: modify the enum to include PENDING_VERIFICATION
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN status ENUM('PENDING', 'PENDING_VERIFICATION', 'ACTIVE', 'REJECTED') NOT NULL DEFAULT 'PENDING'");
        }
        // SQLite: no schema change needed — it stores strings freely
    }

    /**
     * Reverse the migrations.
     */ 
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN status ENUM('PENDING', 'ACTIVE', 'REJECTED') NOT NULL DEFAULT 'PENDING'");
        }
    }
};
