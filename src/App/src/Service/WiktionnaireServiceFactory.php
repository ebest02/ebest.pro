<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Container\ContainerInterface;

final class WiktionnaireServiceFactory
{
    public function __invoke(ContainerInterface $container): WiktionnaireService
    {
        $cacheFile = dirname(__DIR__, 4) . '/data/wiktionnaire_cache.json';
        return new WiktionnaireService($cacheFile);
    }
}
