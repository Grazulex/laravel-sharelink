<?php

declare(strict_types=1);

namespace Grazulex\ShareLink\Console;

use Grazulex\ShareLink\Services\ShareLinkManager;
use Illuminate\Console\Command;

class CreateShareLink extends Command
{
    protected $signature = 'sharelink:create
        {resource : File path or storage reference (disk:path)}
        {--expires= : Expiration in hours}
        {--max-clicks= : Maximum number of clicks}
        {--password= : Optional password}
        {--metadata= : JSON metadata}
        {--burn : Burn after reading}
        {--signed-minutes= : Output a temporary signed URL with given minutes}
    ';

    protected $description = 'Create a new share link';

    public function handle(ShareLinkManager $manager): int
    {
        $resource = (string) $this->argument('resource');
        $builder = $manager->create($resource);

        $expires = $this->option('expires');
        if ($expires !== null && $expires !== '' && $expires !== '0') {
            $builder->expiresIn((int) $expires);
        }

        $max = $this->option('max-clicks');
        if ($max !== null && $max !== '') {
            $builder->maxClicks((int) $max);
        }

        $password = $this->option('password');
        if (is_string($password)) {
            $builder->withPassword($password);
        }

        $metaJson = $this->option('metadata');
        if (is_string($metaJson) && $metaJson !== '') {
            try {
                /** @var array<string,mixed> $meta */
                $meta = json_decode($metaJson, true, 512, JSON_THROW_ON_ERROR);
                $builder->metadata($meta);
            } catch (\Throwable $e) {
                $this->error('Invalid metadata JSON: '.$e->getMessage());
                return self::FAILURE;
            }
        }

        if ((bool) $this->option('burn')) {
            $builder->burnAfterReading();
        }

        $model = $builder->generate();

        $this->info('Share link created');
        $this->line('Token: '.$model->token);
        $this->line('URL:   '.$model->url);

        $signedMinutes = $this->option('signed-minutes');
        if ($signedMinutes !== null && $signedMinutes !== '') {
            $url = $manager->signedUrl($model, (int) $signedMinutes);
            $this->line('Signed URL ('.$signedMinutes.'m): '.$url);
        }

        return self::SUCCESS;
    }
}
