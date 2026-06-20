<?php

declare(strict_types=1);

namespace App\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class PalindromesSaveHandler implements RequestHandlerInterface
{
    private const SESSION_KEY_GENERATED = 'palindromes_generes';

    public function __construct(
        private readonly string $saveFilePath
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $list = $_SESSION[self::SESSION_KEY_GENERATED] ?? [];
        if (! is_array($list)) {
            $list = [];
        }

        if ($list !== []) {
            $dir = dirname($this->saveFilePath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $block = "\n# " . date('Y-m-d H:i:s') . "\n" . implode("\n", $list) . "\n";
            file_put_contents($this->saveFilePath, $block, FILE_APPEND | LOCK_EX);
        }

        return new RedirectResponse('/palindromes', 302);
    }
}
