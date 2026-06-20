<?php

declare(strict_types=1);

namespace App\Handler;

use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function assert;

final class CompetencesHandlerFactory
{
    public function __invoke(ContainerInterface $container): RequestHandlerInterface
    {
        $template = $container->has(TemplateRendererInterface::class)
            ? $container->get(TemplateRendererInterface::class)
            : null;
        assert($template instanceof TemplateRendererInterface || null === $template);

        $config = $container->get('config');
        $path = $config['competences_file'] ?? dirname(__DIR__, 4) . '/competences.md';

        return new CompetencesHandler($template, $path);
    }
}
