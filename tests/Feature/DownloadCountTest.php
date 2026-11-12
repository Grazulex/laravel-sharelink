<?php

declare(strict_types=1);

use Grazulex\ShareLink\Facades\ShareLink as ShareLinkFacade;
use Grazulex\ShareLink\Models\ShareLink;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

function downloadTestFile(int $bytes = 2048): string
{
    $tmp = tempnam(sys_get_temp_dir(), 'sld');
    file_put_contents($tmp, str_repeat('D', $bytes));

    return $tmp;
}

it('creates the download_count column with a zero default', function (): void {
    expect(Schema::hasColumn('share_links', 'download_count'))->toBeTrue();

    $link = ShareLink::create(['resource' => '/tmp/x.txt', 'token' => 'dl-default']);

    expect($link->fresh()->download_count)->toBe(0);
});

it('counts a full file download separately from clicks', function (): void {
    $tmp = downloadTestFile();
    $link = ShareLink::create(['resource' => $tmp, 'token' => 'dl-full']);

    $this->get('/share/'.$link->token)->assertOk();

    $fresh = $link->fresh();
    expect($fresh->click_count)->toBe(1)
        ->and($fresh->download_count)->toBe(1);

    @unlink($tmp);
});

it('does not count HEAD requests as downloads', function (): void {
    $tmp = downloadTestFile();
    $link = ShareLink::create(['resource' => $tmp, 'token' => 'dl-head']);

    $this->call('HEAD', '/share/'.$link->token)->assertOk();

    $fresh = $link->fresh();
    expect($fresh->click_count)->toBe(1)
        ->and($fresh->download_count)->toBe(0);

    @unlink($tmp);
});

it('counts only the first chunk of a ranged transfer', function (): void {
    $tmp = downloadTestFile(4096);
    $link = ShareLink::create(['resource' => $tmp, 'token' => 'dl-range']);

    $this->withHeaders(['Range' => 'bytes=0-1023'])->get('/share/'.$link->token)->assertStatus(206);
    $this->withHeaders(['Range' => 'bytes=1024-2047'])->get('/share/'.$link->token)->assertStatus(206);
    $this->withHeaders(['Range' => 'bytes=2048-'])->get('/share/'.$link->token)->assertStatus(206);

    $fresh = $link->fresh();
    expect($fresh->click_count)->toBe(3)
        ->and($fresh->download_count)->toBe(1);

    @unlink($tmp);
});

it('does not count route redirects as downloads', function (): void {
    Route::get('/dl-target', fn () => 'ok')->name('dl.target');
    Route::getRoutes()->refreshNameLookups();

    $link = ShareLink::create([
        'resource' => ['type' => 'route', 'name' => 'dl.target'],
        'token' => 'dl-route',
        'metadata' => ['max_downloads' => 1],
    ]);

    $this->get('/share/'.$link->token)->assertRedirect();
    $this->get('/share/'.$link->token)->assertRedirect();

    $fresh = $link->fresh();
    expect($fresh->click_count)->toBe(2)
        ->and($fresh->download_count)->toBe(0);
});

it('enforces the per-link download limit without burning previews', function (): void {
    $tmp = downloadTestFile();
    $link = ShareLink::create([
        'resource' => $tmp,
        'token' => 'dl-limit',
        'metadata' => ['max_downloads' => 1],
    ]);

    // A header-only preview does not consume the download allowance
    $this->call('HEAD', '/share/'.$link->token)->assertOk();
    $this->get('/share/'.$link->token)->assertOk();

    $this->get('/share/'.$link->token)
        ->assertStatus(429)
        ->assertJsonPath('code', 'sharelink.download_limit_reached');

    $fresh = $link->fresh();
    expect($fresh->download_count)->toBe(1)
        // The rejected request is not counted as a click either
        ->and($fresh->click_count)->toBe(2);

    @unlink($tmp);
});

it('applies the global limits.max_downloads option', function (): void {
    config()->set('sharelink.limits.max_downloads', 2);
    $tmp = downloadTestFile();
    $link = ShareLink::create(['resource' => $tmp, 'token' => 'dl-global']);

    $this->get('/share/'.$link->token)->assertOk();
    $this->get('/share/'.$link->token)->assertOk();
    $this->get('/share/'.$link->token)->assertStatus(429);

    expect($link->fresh()->download_count)->toBe(2);

    @unlink($tmp);
});

it('lets per-link metadata override the global download limit', function (): void {
    config()->set('sharelink.limits.max_downloads', 1);

    $unlimited = new ShareLink(['metadata' => ['max_downloads' => 0]]);
    $higher = new ShareLink(['metadata' => ['max_downloads' => 5]]);
    $inherit = new ShareLink(['metadata' => ['other' => true]]);

    expect($unlimited->maxDownloads())->toBeNull()
        ->and($higher->maxDownloads())->toBe(5)
        ->and($inherit->maxDownloads())->toBe(1);

    config()->set('sharelink.limits.max_downloads', null);
    expect($inherit->maxDownloads())->toBeNull()
        ->and($inherit->hasReachedDownloadLimit())->toBeFalse();
});

it('keeps max_clicks independent from the download counter', function (): void {
    $tmp = downloadTestFile();
    $link = ShareLink::create([
        'resource' => $tmp,
        'token' => 'dl-clicks',
        'max_clicks' => 1,
        'metadata' => ['max_downloads' => 10],
    ]);

    $this->get('/share/'.$link->token)->assertOk();
    $this->get('/share/'.$link->token)
        ->assertStatus(429)
        ->assertJsonPath('code', 'sharelink.limit_reached');

    @unlink($tmp);
});

it('exposes the download counter in the JSON resource', function (): void {
    $link = ShareLink::create([
        'resource' => ['type' => 'model', 'class' => 'App\\Models\\Doc', 'id' => 1],
        'token' => 'dl-json',
    ]);
    config()->set('app.debug', false);

    $this->get('/share/'.$link->token)
        ->assertOk()
        ->assertJsonPath('clicks', 1)
        ->assertJsonPath('downloads', 0);
});

it('stores max downloads through the fluent builder', function (): void {
    $link = ShareLinkFacade::create('/tmp/report.pdf')
        ->maxDownloads(3)
        ->metadata(['source' => 'test'])
        ->generate();

    expect($link->metadata)->toBe(['source' => 'test', 'max_downloads' => 3])
        ->and($link->maxDownloads())->toBe(3);
});

it('accepts --max-downloads on the CLI', function (): void {
    $this->artisan('sharelink:create', [
        'resource' => '/tmp/foo.txt',
        '--max-downloads' => 2,
    ])->assertExitCode(0)->run();

    $link = ShareLink::query()->firstOrFail();
    expect($link->metadata['max_downloads'] ?? null)->toBe(2);
});

it('keeps working on tables without the download_count column', function (): void {
    Schema::table('share_links', function (Blueprint $table): void {
        $table->dropColumn('download_count');
    });

    $tmp = downloadTestFile();
    $link = ShareLink::create([
        'resource' => $tmp,
        'token' => 'dl-legacy',
        'metadata' => ['max_downloads' => 1],
    ]);

    $this->get('/share/'.$link->token)->assertOk();
    $this->get('/share/'.$link->token)->assertOk();

    expect($link->fresh()->click_count)->toBe(2);

    // The upgrade migration adds the column back
    $migration = require __DIR__.'/../../database/migrations/add_download_count_to_share_links_table.php';
    $migration->up();

    expect(Schema::hasColumn('share_links', 'download_count'))->toBeTrue()
        ->and($link->fresh()->download_count)->toBe(0);

    // Running it again is a no-op
    $migration->up();

    @unlink($tmp);
});

it('increments downloads on a freshly created instance', function (): void {
    $link = ShareLink::create(['resource' => '/tmp/x.txt', 'token' => 'dl-fresh']);

    $link->incrementDownloads();
    $link->incrementDownloads();

    expect($link->download_count)->toBe(2)
        ->and($link->fresh()->download_count)->toBe(2);
});
