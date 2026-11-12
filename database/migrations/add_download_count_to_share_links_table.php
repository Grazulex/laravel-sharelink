<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrade migration for installations created before the download counter existed.
 * It is a no-op when the table does not exist yet or already has the column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('share_links') || Schema::hasColumn('share_links', 'download_count')) {
            return;
        }

        Schema::table('share_links', function (Blueprint $table): void {
            $table->unsignedInteger('download_count')->default(0)->after('click_count');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('share_links') || ! Schema::hasColumn('share_links', 'download_count')) {
            return;
        }

        Schema::table('share_links', function (Blueprint $table): void {
            $table->dropColumn('download_count');
        });
    }
};
