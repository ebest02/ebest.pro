<?php

declare(strict_types=1);

namespace App\Handler;

use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ScriptsHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly ?TemplateRendererInterface $template,
        private readonly string $scriptsDir,
        private readonly string $githubBaseUrl
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $fileParam = isset($params['file']) ? $params['file'] : null;
        $selectedFileName = $fileParam !== null && $fileParam !== '' ? basename((string) $fileParam) : null;

        $scriptFiles = is_dir($this->scriptsDir) ? glob($this->scriptsDir . '/*.sh') : [];
        $scripts = [];
        foreach ($scriptFiles as $path) {
            $basename = basename($path);
            $scripts[$basename] = $path;
        }

        $selectedPath = $selectedFileName !== null && isset($scripts[$selectedFileName])
            ? $scripts[$selectedFileName]
            : null;

        $scriptContent = null;
        if ($selectedPath !== null && is_readable($selectedPath)) {
            $content = file_get_contents($selectedPath);
            $scriptContent = $content !== false ? $content : null;
        }

        $data = [
            'scripts' => $scripts,
            'selectedFileName' => $selectedFileName,
            'scriptContent' => $scriptContent,
            'githubBaseUrl' => $this->githubBaseUrl,
        ];

        return new HtmlResponse($this->template->render('app::scripts', $data));
    }
}
