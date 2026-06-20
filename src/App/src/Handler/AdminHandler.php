<?php

declare(strict_types=1);

namespace App\Handler;

use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;

final class AdminHandler implements RequestHandlerInterface
{
    private const DEFAULT_SITES = [
        'https://onphi.org/',
        'https://tv.onphi.org/',
        'https://radio.onphi.org/',
        'https://philo-fictions.fr',
        'https://sylx.fr',
        'https://onphi.art',
    ];

    public function __construct(
        private readonly ?TemplateRendererInterface $template = null
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Démarrer la session si elle n'est pas déjà démarrée
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Vérifier l'authentification
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            return new RedirectResponse('/admin/login');
        }

        $sitesFile = dirname(__DIR__, 4) . '/data/sites.json';
        $sites = $this->loadSites($sitesFile);

        $data = [
            'sites' => $sites,
        ];

        return new HtmlResponse($this->template->render('app::admin', $data));
    }

    private function loadSites(string $sitesFile): array
    {
        if (file_exists($sitesFile)) {
            $content = file_get_contents($sitesFile);
            $sites = json_decode($content, true);
            if (is_array($sites)) {
                return $sites;
            }
        }

        // Créer le fichier avec les sites par défaut
        $defaultSites = array_map(fn($url) => ['url' => $url, 'status' => 'unknown', 'lastCheck' => null], self::DEFAULT_SITES);
        $this->saveSites($sitesFile, $defaultSites);
        return $defaultSites;
    }

    private function saveSites(string $sitesFile, array $sites): void
    {
        $dir = dirname($sitesFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($sitesFile, json_encode($sites, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
