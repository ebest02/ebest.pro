<?php

declare(strict_types=1);

namespace App\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AdminApiHandler implements RequestHandlerInterface
{
    private const DEFAULT_SITES = [
        'https://onphi.org/',
        'https://tv.onphi.org/',
        'https://radio.onphi.org/',
        'https://philo-fictions.fr',
        'https://sylx.fr',
        'https://onphi.art',
    ];

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Démarrer la session si elle n'est pas déjà démarrée
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Vérifier l'authentification
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $method = $request->getMethod();
        $sitesFile = dirname(__DIR__, 4) . '/data/sites.json';

        if ($method === 'POST') {
            $body = json_decode($request->getBody()->getContents(), true);
            // Si c'est une requête de régénération de vignette
            if (isset($body['action']) && $body['action'] === 'regenerate_screenshot') {
                return $this->regenerateScreenshot($body);
            }
            return $this->addSite($body, $sitesFile);
        } elseif ($method === 'DELETE') {
            $body = json_decode($request->getBody()->getContents(), true);
            return $this->deleteSite($body, $sitesFile);
        }

        return new JsonResponse(['error' => 'Method not allowed'], 405);
    }

    private function addSite(array $body, string $sitesFile): ResponseInterface
    {
        if (!isset($body['url']) || empty($body['url'])) {
            return new JsonResponse(['error' => 'URL is required'], 400);
        }

        $url = filter_var($body['url'], FILTER_SANITIZE_URL);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new JsonResponse(['error' => 'Invalid URL'], 400);
        }

        // Normaliser l'URL
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'https://' . $url;
        }

        $sites = $this->loadSites($sitesFile);

        // Vérifier si le site existe déjà
        foreach ($sites as $site) {
            if ($site['url'] === $url) {
                return new JsonResponse(['error' => 'Site already exists'], 409);
            }
        }

        // Vérifier le statut du nouveau site
        $status = $this->checkSiteStatus($url);

        $newSite = [
            'url' => $url,
            'status' => $status['status'],
            'lastCheck' => date('Y-m-d H:i:s'),
            'responseTime' => $status['responseTime'] ?? null,
        ];

        $sites[] = $newSite;
        $this->saveSites($sitesFile, $sites);

        return new JsonResponse(['site' => $newSite], 201);
    }

    private function deleteSite(array $body, string $sitesFile): ResponseInterface
    {
        if (!isset($body['url']) || empty($body['url'])) {
            return new JsonResponse(['error' => 'URL is required'], 400);
        }

        $url = $body['url'];
        $sites = $this->loadSites($sitesFile);

        // Trouver et supprimer le site
        $found = false;
        $sites = array_filter($sites, function($site) use ($url, &$found) {
            if ($site['url'] === $url) {
                $found = true;
                return false;
            }
            return true;
        });

        // Réindexer le tableau
        $sites = array_values($sites);

        if (!$found) {
            return new JsonResponse(['error' => 'Site not found'], 404);
        }

        $this->saveSites($sitesFile, $sites);

        return new JsonResponse(['message' => 'Site deleted successfully']);
    }

    private function checkSiteStatus(string $url): array
    {
        $startTime = microtime(true);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; StatusChecker/1.0)',
            CURLOPT_NOBODY => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $responseTime = round((microtime(true) - $startTime) * 1000, 2);

        if ($error) {
            return [
                'status' => 'error',
                'responseTime' => $responseTime,
                'error' => $error,
            ];
        }

        if ($httpCode >= 200 && $httpCode < 400) {
            return [
                'status' => 'online',
                'responseTime' => $responseTime,
                'httpCode' => $httpCode,
            ];
        }

        return [
            'status' => 'offline',
            'responseTime' => $responseTime,
            'httpCode' => $httpCode,
        ];
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

    private function regenerateScreenshot(array $body): ResponseInterface
    {
        if (!isset($body['url']) || empty($body['url'])) {
            return new JsonResponse(['error' => 'URL is required'], 400);
        }

        $url = $body['url'];
        
        // Valider l'URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new JsonResponse(['error' => 'Invalid URL'], 400);
        }

        // Supprimer le fichier de cache de la vignette
        $cacheDir = dirname(__DIR__, 4) . '/data/screenshots';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $cacheFile = $cacheDir . '/' . md5($url) . '.png';
        
        $deleted = false;
        $existed = false;
        if (file_exists($cacheFile)) {
            $existed = true;
            $deleted = @unlink($cacheFile);
        }

        return new JsonResponse([
            'message' => 'Screenshot cache cleared',
            'url' => $url,
            'deleted' => $deleted,
            'existed' => $existed,
            'cacheFile' => $cacheFile
        ]);
    }
}
