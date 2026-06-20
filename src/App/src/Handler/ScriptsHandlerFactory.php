<?php

declare(strict_types=1);

namespace App\Handler;

use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function assert;
use function dirname;

final class ScriptsHandlerFactory
{
    public function __invoke(ContainerInterface $container): RequestHandlerInterface
    {
        $template = $container->has(TemplateRendererInterface::class)
            ? $container->get(TemplateRendererInterface::class)
            : null;
        assert($template instanceof TemplateRendererInterface || null === $template);

        $config = $container->get('config');
        $rootDir = dirname(__DIR__, 4);
        $scriptsDir = $config['scripts_dir'] ?? $rootDir . '/data/scripts';
        $githubBaseUrl = $config['scripts_github_base_url']
            ?? 'https://github.com/ebest02/Macintosh-Performa-460/blob/main';

        return new ScriptsHandler($template, $scriptsDir, $githubBaseUrl);
    }
}
