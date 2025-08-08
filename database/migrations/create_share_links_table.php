<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->json('resource');
            $table->string('token', 64)->unique();
            $table->string('password')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('max_clicks')->nullable();
            $table->unsignedInteger('click_count')->default(0);
            $table->timestamp('first_access_at')->nullable();
            $table->timestamp('last_access_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Helpful indexes
            $table->index('expires_at');
            $table->index('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_links');
    }
};
