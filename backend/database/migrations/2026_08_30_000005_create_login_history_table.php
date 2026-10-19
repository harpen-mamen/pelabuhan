<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_history', function (Blueprint $table) {
            $table->id('id_login');
            $table->foreignId('id_user')->nullable()->constrained('users', 'id_user')->onDelete('set null');
            $table->string('username')->nullable(); // Store username even if login fails
            $table->enum('status', ['success', 'failed'])->default('failed');
            $table->string('reason')->nullable(); // validation_error, invalid_credentials, account_inactive
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('token_id')->nullable(); // JWT token ID for successful login
            $table->timestamp('login_at');
            $table->timestamp('logout_at')->nullable();
            $table->integer('session_duration')->nullable(); // in seconds
            $table->timestamps();

            // Indexes for faster queries
            $table->index('id_user');
            $table->index('username');
            $table->index('status');
            $table->index('login_at');
            $table->index(['id_user', 'login_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_history');
    }
};
