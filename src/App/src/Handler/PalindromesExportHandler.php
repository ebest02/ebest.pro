<?php

declare(strict_types=1);

namespace App\Handler;

use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class PalindromesExportHandler implements RequestHandlerInterface
{
    private const SESSION_KEY_GENERATED = 'palindromes_generes';

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $list = $_SESSION[self::SESSION_KEY_GENERATED] ?? [];
        if (! is_array($list)) {
            $list = [];
        }
        $content = implode("\n", $list);

        $response = new Response();
        $response->getBody()->write($content);

        return $response
            ->withHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="palindromes.txt"');
    }
}
