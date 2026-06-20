<?php

declare(strict_types=1);

namespace App\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class StatusCheckApiHandler implements RequestHandlerInterface
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
        $method = $request->getMethod();
        $sitesFile = dirname(__DIR__, 4) . '/data/sites.json';

        if ($method === 'GET') {
            try {
                return $this->checkAllSites($sitesFile);
            } catch (\Throwable $e) {
                return new JsonResponse([
                    'error' => 'Erreur lors de la vérification',
                    'message' => $e->getMessage(),
                    'sites' => [],
                ], 500);
            }
        }

        return new JsonResponse(['error' => 'Method not allowed'], 405);
    }

    private function checkAllSites(string $sitesFile): ResponseInterface
    {
        $sites = $this->loadSites($sitesFile);
        $results = [];
        $dateTime = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        $nowStr = $dateTime->format('Y-m-d H:i:s');

        foreach ($sites as $index => $site) {
            if (!is_array($site)) {
                $results[] = ['url' => '', 'status' => 'error', 'lastCheck' => $nowStr, 'responseTime' => null, 'screenshot' => ''];
                continue;
            }
            $url = isset($site['url']) ? (string) $site['url'] : '';
            if ($url === '') {
                $site['status'] = 'error';
                $site['lastCheck'] = $nowStr;
                $site['responseTime'] = null;
                $site['screenshot'] = '';
                $results[] = $site;
                continue;
            }
            $status = $this->checkSiteStatus($url);
            $site['status'] = $status['status'];
            $site['lastCheck'] = $nowStr;
            $site['responseTime'] = $status['responseTime'] ?? null;
            $site['screenshot'] = $this->getScreenshotUrl($url);
            $results[] = $site;
        }

        $this->saveSites($sitesFile, $results);

        return new JsonResponse(['sites' => $results]);
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
        if (is_readable($sitesFile)) {
            $content = file_get_contents($sitesFile);
            if ($content !== false) {
                $sites = json_decode($content, true);
                if (is_array($sites)) {
                    return array_values($sites);
                }
            }
        }

        $defaultSites = array_map(
            fn(string $url) => ['url' => $url, 'status' => 'unknown', 'lastCheck' => null],
            self::DEFAULT_SITES
        );
        $this->saveSites($sitesFile, $defaultSites);
        return $defaultSites;
    }

    private function saveSites(string $sitesFile, array $sites): void
    {
        $dir = dirname($sitesFile);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                throw new \RuntimeException('Impossible de créer le dossier data: ' . $dir);
            }
        }
        if (!is_writable($dir) && (!file_exists($sitesFile) || !is_writable($sitesFile))) {
            throw new \RuntimeException('Impossible d\'écrire dans data/sites.json (droits insuffisants)');
        }
        $json = json_encode($sites, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (@file_put_contents($sitesFile, $json) === false) {
            throw new \RuntimeException('Échec de l\'écriture de data/sites.json');
        }
    }

    private function getScreenshotUrl(string $url): string
    {
        // Utiliser un service externe pour générer les captures d'écran
        // Option 1: Utiliser htmlcsstoimage.com (nécessite une clé API)
        // Option 2: Utiliser screenshotapi.net (gratuit avec limites)
        // Option 3: Utiliser Puppeteer si disponible
        
        // Pour l'instant, on utilise un service gratuit simple
        // Vous pouvez configurer votre propre service dans la configuration
        $encodedUrl = urlencode($url);
        $host = parse_url($url, PHP_URL_HOST) ?? 'site';
        
        // Service gratuit: thum.io (avec limitations)
        // return "https://image.thum.io/get/width/800/crop/600/noanimate/{$url}";
        
        // Service alternatif: screenshotapi.net
        // return "https://api.screenshotapi.net/screenshot?url={$encodedUrl}&width=800&height=600";
        
        // Pour l'instant, on génère une URL vers notre propre endpoint
        return "/api/screenshot?url=" . $encodedUrl;
    }
}
