<?php

declare(strict_types=1);

namespace App\Handler;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class PalindromesSaveHandlerFactory
{
    public function __invoke(ContainerInterface $container): RequestHandlerInterface
    {
        $saveFile = dirname(__DIR__, 4) . '/data/palindromes_generes.txt';
        return new PalindromesSaveHandler($saveFile);
    }
}
