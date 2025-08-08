<?php

declare(strict_types=1);

namespace Grazulex\ShareLink;

use Grazulex\ShareLink\Console\PruneShareLinks;
use Grazulex\ShareLink\Services\ShareLinkManager;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class ShareLinkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sharelink.php', 'sharelink');
        $this->app->singleton(ShareLinkManager::class, fn (): ShareLinkManager => new ShareLinkManager());
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/sharelink.php' => config_path('sharelink.php'),
        ], 'sharelink-config');

        if (! class_exists('CreateShareLinksTable')) {
            $this->publishes([
                __DIR__.'/../database/migrations/create_share_links_table.php' => database_path('migrations/'.date('Y_m_d_His').'_create_share_links_table.php'),
            ], 'sharelink-migrations');
        }

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                PruneShareLinks::class,
            ]);
            // Register scheduler callback
            $this->app->afterResolving(Schedule::class, function (Schedule $schedule): void {
                if ((bool) config('sharelink.schedule.prune.enabled', true)) {
                    $expr = (string) config('sharelink.schedule.prune.expression', '0 3 * * *');
                    $schedule->command('sharelink:prune')->cron($expr)->name('sharelink:prune');
                }
            });
        }
    }
}
