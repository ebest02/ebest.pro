<?php

declare(strict_types=1);

namespace App\Handler;

use App\Service\WiktionnaireService;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function assert;

final class PalindromesHandlerFactory
{
    public function __invoke(ContainerInterface $container): RequestHandlerInterface
    {
        $template = $container->has(TemplateRendererInterface::class)
            ? $container->get(TemplateRendererInterface::class)
            : null;
        assert($template instanceof TemplateRendererInterface || null === $template);

        $root = dirname(__DIR__, 4);
        $palindromesFile = $root . '/data/palindromes.txt';
        $pythonScript = $root . '/data/scripts/palindrome_fr.py';

        $wiktionnaire = $container->has(WiktionnaireService::class)
            ? $container->get(WiktionnaireService::class)
            : null;
        assert($wiktionnaire instanceof WiktionnaireService || null === $wiktionnaire);

        return new PalindromesHandler($template, $palindromesFile, $wiktionnaire, $pythonScript);
    }
}
