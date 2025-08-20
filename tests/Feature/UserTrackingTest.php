<?php

declare(strict_types=1);

use Grazulex\ShareLink\Models\ShareLink;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

it('migration works without user tracking enabled', function (): void {
    // Ensure user tracking is disabled
    config()->set('sharelink.user_tracking.enabled', false);

    // Fresh migration should work
    Schema::dropIfExists('share_links');

    // Run the migration manually to ensure it works
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

        // Add created_by column - flexible approach to handle different user ID types
        if (config('sharelink.user_tracking.enabled', false)) {
            $userIdType = config('sharelink.user_tracking.user_id_type', 'bigint');
            $userTable = config('sharelink.user_tracking.user_table', 'users');

            match ($userIdType) {
                'uuid' => $table->uuid('created_by')->nullable(),
                'ulid' => $table->ulid('created_by')->nullable(),
                'bigint' => $table->foreignId('created_by')->nullable()->constrained($userTable)->nullOnDelete(),
                default => $table->unsignedBigInteger('created_by')->nullable(),
            };

            // Add foreign key constraint only for non-bigint types or when explicitly enabled
            if ($userIdType !== 'bigint' && config('sharelink.user_tracking.add_foreign_key', true)) {
                $table->foreign('created_by')->references('id')->on($userTable)->nullOnDelete();
            }
        }

        $table->timestamps();

        // Helpful indexes
        $table->index('expires_at');
        $table->index('revoked_at');
    });

    expect(Schema::hasTable('share_links'))->toBeTrue();
    expect(Schema::hasColumn('share_links', 'created_by'))->toBeFalse();
});

it('migration works with user tracking enabled and bigint type', function (): void {
    // Enable user tracking with bigint type
    config()->set('sharelink.user_tracking.enabled', true);
    config()->set('sharelink.user_tracking.user_id_type', 'bigint');
    config()->set('sharelink.user_tracking.user_table', 'users');

    // Create a users table first to avoid foreign key constraint error
    if (! Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    // Fresh migration should work
    Schema::dropIfExists('share_links');

    // Run the migration manually to ensure it works
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

        // Add created_by column - flexible approach to handle different user ID types
        if (config('sharelink.user_tracking.enabled', false)) {
            $userIdType = config('sharelink.user_tracking.user_id_type', 'bigint');
            $userTable = config('sharelink.user_tracking.user_table', 'users');

            match ($userIdType) {
                'uuid' => $table->uuid('created_by')->nullable(),
                'ulid' => $table->ulid('created_by')->nullable(),
                'bigint' => $table->foreignId('created_by')->nullable()->constrained($userTable)->nullOnDelete(),
                default => $table->unsignedBigInteger('created_by')->nullable(),
            };

            // Add foreign key constraint only for non-bigint types or when explicitly enabled
            if ($userIdType !== 'bigint' && config('sharelink.user_tracking.add_foreign_key', true)) {
                $table->foreign('created_by')->references('id')->on($userTable)->nullOnDelete();
            }
        }

        $table->timestamps();

        // Helpful indexes
        $table->index('expires_at');
        $table->index('revoked_at');
    });

    expect(Schema::hasTable('share_links'))->toBeTrue();
    expect(Schema::hasColumn('share_links', 'created_by'))->toBeTrue();
});

it('migration works with user tracking enabled and uuid type', function (): void {
    // Enable user tracking with UUID type
    config()->set('sharelink.user_tracking.enabled', true);
    config()->set('sharelink.user_tracking.user_id_type', 'uuid');
    config()->set('sharelink.user_tracking.user_table', 'users');
    config()->set('sharelink.user_tracking.add_foreign_key', false); // Disable FK for this test

    // Fresh migration should work
    Schema::dropIfExists('share_links');

    // Run the migration manually to ensure it works
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

        // Add created_by column - flexible approach to handle different user ID types
        if (config('sharelink.user_tracking.enabled', false)) {
            $userIdType = config('sharelink.user_tracking.user_id_type', 'bigint');
            $userTable = config('sharelink.user_tracking.user_table', 'users');

            match ($userIdType) {
                'uuid' => $table->uuid('created_by')->nullable(),
                'ulid' => $table->ulid('created_by')->nullable(),
                'bigint' => $table->foreignId('created_by')->nullable()->constrained($userTable)->nullOnDelete(),
                default => $table->unsignedBigInteger('created_by')->nullable(),
            };

            // Add foreign key constraint only for non-bigint types or when explicitly enabled
            if ($userIdType !== 'bigint' && config('sharelink.user_tracking.add_foreign_key', true)) {
                $table->foreign('created_by')->references('id')->on($userTable)->nullOnDelete();
            }
        }

        $table->timestamps();

        // Helpful indexes
        $table->index('expires_at');
        $table->index('revoked_at');
    });

    expect(Schema::hasTable('share_links'))->toBeTrue();
    expect(Schema::hasColumn('share_links', 'created_by'))->toBeTrue();
});

it('created_by is not fillable when user tracking is disabled', function (): void {
    config()->set('sharelink.user_tracking.enabled', false);

    $shareLink = new ShareLink();
    expect($shareLink->getFillable())->not->toContain('created_by');
});

it('created_by is fillable when user tracking is enabled', function (): void {
    config()->set('sharelink.user_tracking.enabled', true);

    $shareLink = new ShareLink();
    expect($shareLink->getFillable())->toContain('created_by');
});
