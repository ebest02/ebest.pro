<?php

declare(strict_types=1);

namespace App\Handler;

use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ScreenshotHandler implements RequestHandlerInterface
{
    /**
     * Génère une capture d'écran d'un site web
     * Retourne l'image directement
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $url = $queryParams['url'] ?? '';
        $nocache = isset($queryParams['nocache']) && $queryParams['nocache'] === '1';

        // Décoder l'URL si elle est encodée
        $url = urldecode($url);

        if (empty($url)) {
            $placeholderData = $this->generatePlaceholder('error');
            return $this->createImageResponseFromData($placeholderData);
        }

        // Valider l'URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $placeholderData = $this->generatePlaceholder('error');
            return $this->createImageResponseFromData($placeholderData);
        }

        // Vérifier le cache d'abord (sauf si nocache est demandé)
        $cacheDir = dirname(__DIR__, 4) . '/data/screenshots';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
            @chmod($cacheDir, 0777);
        }
        
        $cacheFile = $cacheDir . '/' . md5($url) . '.png';
        
        // Si la capture existe en cache et est récente (moins de 24h), la retourner
        // Sauf si nocache est demandé (pour forcer la régénération)
        if (!$nocache && file_exists($cacheFile) && is_readable($cacheFile)) {
            $fileAge = time() - filemtime($cacheFile);
            if ($fileAge < 86400) {
                $imageData = @file_get_contents($cacheFile);
                if ($imageData !== false && strlen($imageData) > 1024) {
                    // Vérifier que c'est bien une image PNG valide
                    if (substr($imageData, 0, 8) === "\x89PNG\r\n\x1a\n") {
                        return $this->createImageResponseFromData($imageData);
                    } else {
                        // Fichier invalide, le supprimer
                        @unlink($cacheFile);
                    }
                } else {
                    // Fichier trop petit ou illisible, le supprimer
                    @unlink($cacheFile);
                }
            } else {
                // Fichier trop ancien, le supprimer
                @unlink($cacheFile);
            }
        }
        
        // Si le fichier existe mais n'a pas pu être lu ou est invalide, le supprimer
        if (file_exists($cacheFile) && (!is_readable($cacheFile) || filesize($cacheFile) < 1024)) {
            @unlink($cacheFile);
        }
        
        // Si nocache est demandé, supprimer le cache existant
        if ($nocache && file_exists($cacheFile)) {
            @unlink($cacheFile);
        }

        // Vérifier d'abord si Puppeteer est disponible (meilleure qualité)
        if ($this->isPuppeteerAvailable()) {
            try {
                $imageData = $this->generateScreenshotWithPuppeteer($url);
                if ($imageData !== null && strlen($imageData) > 1024) {
                    // Sauvegarder en cache avec les bonnes permissions
                    if (is_dir($cacheDir) && is_writable($cacheDir)) {
                        @file_put_contents($cacheFile, $imageData);
                        @chmod($cacheFile, 0644);
                    }
                    return $this->createImageResponseFromData($imageData);
                }
            } catch (\Exception $e) {
                // En cas d'erreur, continuer avec les autres méthodes
            }
        }

        // Si Puppeteer n'est pas disponible ou a échoué, essayer les services externes
        try {
            $imageData = $this->generateScreenshotFast($url);
            if ($imageData !== null && strlen($imageData) > 1024) {
                // Sauvegarder en cache même pour les services externes avec les bonnes permissions
                if (is_dir($cacheDir) && is_writable($cacheDir)) {
                    @file_put_contents($cacheFile, $imageData);
                    @chmod($cacheFile, 0644);
                }
                return $this->createImageResponseFromData($imageData);
            }
        } catch (\Exception $e) {
            // En cas d'erreur, continuer avec le placeholder
        }

        // En dernier recours, générer un placeholder de qualité
        // Même le placeholder est mis en cache pour éviter de le régénérer
        $placeholderData = $this->generatePlaceholder($url);
        // Sauvegarder en cache avec les bonnes permissions
        if (is_dir($cacheDir) && is_writable($cacheDir)) {
            @file_put_contents($cacheFile, $placeholderData);
            @chmod($cacheFile, 0644);
        }
        return $this->createImageResponseFromData($placeholderData);
    }

    /**
     * Génère une capture d'écran rapidement (avec timeout court)
     * Retourne les données de l'image ou null si échec
     */
    private function generateScreenshotFast(string $url): ?string
    {
        // Option 1: Utiliser un service API externe rapide
        $imageData = $this->generateScreenshotWithService($url);
        if ($imageData !== null && strlen($imageData) > 1024) {
            return $imageData;
        }

        // Option 2: Utiliser Puppeteer si disponible (mais avec timeout très court)
        if ($this->isPuppeteerAvailable()) {
            // Note: Puppeteer peut être lent, donc on l'utilise seulement si les services externes échouent
            // et on pourrait l'utiliser en arrière-plan plus tard
        }

        return null;
    }

    /**
     * Génère une capture d'écran en utilisant un service externe
     * Essaie plusieurs services en cascade avec timeout court
     */
    private function generateScreenshotWithService(string $url): ?string
    {
        // Liste des services à essayer (dans l'ordre de préférence)
        // Note: La plupart des services gratuits ont des limitations
        // Pour une solution fiable, installez Puppeteer ou utilisez un service payant
        $services = [
            // Service 1: mini.s-shot.ru (gratuit, souvent fonctionnel)
            "https://mini.s-shot.ru/400x300/PNG/400/?{$url}",
            
            // Service 2: thum.io (gratuit avec limitations de taux)
            "https://image.thum.io/get/width/400/crop/300/noanimate/{$url}",
            
            // Service 3: api.screenshotlayer.com (nécessite clé API gratuite)
            // "https://api.screenshotlayer.com/api/capture?access_key=YOUR_KEY&url=" . urlencode($url) . "&viewport=400x300",
        ];

        foreach ($services as $imageUrl) {
            $imageData = $this->downloadImage($imageUrl, 5); // Timeout de 5 secondes max
            if ($imageData !== null && strlen($imageData) > 1024) {
                return $imageData;
            }
        }

        return null;
    }

    /**
     * Télécharge une image depuis une URL avec cURL
     * @param int $timeout Timeout en secondes
     */
    private function downloadImage(string $imageUrl, int $timeout = 5): ?string
    {
        $ch = curl_init($imageUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_ENCODING => '', // Accepter la compression
        ]);

        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        curl_close($ch);

        // Vérifier que la requête a réussi
        if ($error || $httpCode !== 200 || $imageData === false) {
            return null;
        }

        // Vérifier le Content-Type
        if ($contentType && !preg_match('/image\/(png|jpeg|jpg|gif|webp)/i', $contentType)) {
            return null;
        }

        // Vérifier que c'est bien une image (au moins 1KB)
        if (strlen($imageData) < 1024) {
            return null;
        }

        // Vérifier que c'est bien une image valide
        $imageInfo = @getimagesizefromstring($imageData);
        if ($imageInfo === false) {
            return null;
        }

        return $imageData;
    }

    /**
     * Vérifie si Puppeteer est disponible
     */
    private function isPuppeteerAvailable(): bool
    {
        $nodePath = exec('which node 2>/dev/null');
        if (empty($nodePath)) {
            return false;
        }

        // Vérifier si puppeteer est installé
        exec('node -e "require(\'puppeteer\')" 2>&1', $output, $returnVar);
        return $returnVar === 0;
    }

    /**
     * Génère une capture d'écran en utilisant Puppeteer (si Node.js est disponible)
     */
    private function generateScreenshotWithPuppeteer(string $url): ?string
    {
        $tempFile = sys_get_temp_dir() . '/screenshot_' . uniqid() . '.png';
        $escapedUrl = escapeshellarg($url);
        $escapedFilepath = escapeshellarg($tempFile);
        
        $script = <<<JS
const puppeteer = require('puppeteer');

(async () => {
    let browser;
    try {
        browser = await puppeteer.launch({
            headless: true,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--disable-software-rasterizer'
            ]
        });
        const page = await browser.newPage();
        // Viewport plus large pour avoir moins de zoom (1200x900 = ratio 4:3)
        await page.setViewport({width: 1200, height: 900});
        
        // Ne pas désactiver les images pour avoir de meilleures captures
        // On garde tout pour avoir une vraie représentation visuelle
        
        // Essayer de charger la page avec plusieurs stratégies
        try {
            await page.goto({$escapedUrl}, {
                waitUntil: 'domcontentloaded',
                timeout: 15000
            });
        } catch (e) {
            // Si domcontentloaded échoue, essayer avec networkidle0
            try {
                await page.goto({$escapedUrl}, {
                    waitUntil: 'networkidle0',
                    timeout: 15000
                });
            } catch (e2) {
                // Si tout échoue, essayer avec load
                await page.goto({$escapedUrl}, {
                    waitUntil: 'load',
                    timeout: 15000
                });
            }
        }
        // Attendre que le contenu se rende complètement
        await page.waitForTimeout(3000);
        
        // Attendre que les éléments critiques soient chargés
        try {
            await page.waitForSelector('body', {timeout: 5000});
        } catch (e) {
            // Si body n'est pas trouvé, continuer quand même
        }
        await page.screenshot({
            path: {$escapedFilepath},
            fullPage: false,
            type: 'png'
        });
        await browser.close();
        process.exit(0);
    } catch (error) {
        if (browser) {
            await browser.close();
        }
        process.exit(1);
    }
})();
JS;

        // Exécuter le script Puppeteer avec timeout
        $scriptFile = sys_get_temp_dir() . '/screenshot_' . uniqid() . '.js';
        file_put_contents($scriptFile, $script);
        
        // Exécuter avec timeout de 35 secondes pour les sites lents
        $command = "timeout 35 node {$scriptFile} 2>&1";
        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);
        
        // Nettoyer le fichier script
        if (file_exists($scriptFile)) {
            @unlink($scriptFile);
        }

        // Vérifier si le fichier a été créé et est valide
        if ($returnVar === 0 && file_exists($tempFile)) {
            $fileSize = filesize($tempFile);
            if ($fileSize > 1024) {
                $imageData = @file_get_contents($tempFile);
                @unlink($tempFile);
                if ($imageData !== false && strlen($imageData) > 1024) {
                    return $imageData;
                }
            }
        }

        // Nettoyer le fichier temporaire en cas d'échec
        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
        
        return null;
    }

    /**
     * Génère un placeholder de qualité avec le nom du site
     * Cette méthode est rapide et fiable, toujours utilisée pour un affichage immédiat
     */
    private function generatePlaceholder(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?? 'site';
        // Enlever www. si présent
        $host = preg_replace('/^www\./', '', $host);
        // Enlever le protocole si présent
        $host = preg_replace('/^https?:\/\//', '', $host);
        // Limiter la longueur du host pour l'affichage
        if (strlen($host) > 28) {
            $host = substr($host, 0, 25) . '...';
        }
        
        // Taille plus grande pour avoir moins de zoom (proportion 4:3)
        $width = 1200;
        $height = 900;
        $image = imagecreatetruecolor($width, $height);
        
        // Couleurs modernes
        $bgColor = imagecolorallocate($image, 250, 250, 250);
        $textColor = imagecolorallocate($image, 60, 60, 60);
        $borderColor = imagecolorallocate($image, 230, 230, 230);
        $accentColor = imagecolorallocate($image, 0, 102, 204);
        $lightAccent = imagecolorallocate($image, 230, 240, 255);
        
        // Fond
        imagefill($image, 0, 0, $bgColor);
        
        // Bordure arrondie simulée
        imagerectangle($image, 0, 0, $width - 1, $height - 1, $borderColor);
        
        // Barre d'en-tête avec dégradé
        $headerHeight = 30;
        imagefilledrectangle($image, 0, 0, $width, $headerHeight, $accentColor);
        imagefilledrectangle($image, 0, $headerHeight - 5, $width, $headerHeight, $lightAccent);
        
        // Texte blanc sur la barre
        $whiteColor = imagecolorallocate($image, 255, 255, 255);
        $titleText = strtoupper($host);
        $titleFontSize = 4;
        $titleWidth = strlen($titleText) * imagefontwidth($titleFontSize);
        $titleX = ($width - $titleWidth) / 2;
        if ($titleX > 0 && $titleWidth < $width - 10) {
            imagestring($image, $titleFontSize, (int)$titleX, 8, $titleText, $whiteColor);
        }
        
        // Icône de globe stylisée au centre
        $centerX = $width / 2;
        $centerY = $height / 2;
        $radius = 40;
        
        // Cercle principal
        imagefilledellipse($image, (int)$centerX, (int)$centerY, $radius * 2, $radius * 2, $lightAccent);
        imageellipse($image, (int)$centerX, (int)$centerY, $radius * 2, $radius * 2, $accentColor);
        
        // Lignes pour simuler un globe
        $lineColor = imagecolorallocate($image, 180, 200, 255);
        // Ligne horizontale
        imageline($image, (int)($centerX - $radius), (int)$centerY, (int)($centerX + $radius), (int)$centerY, $lineColor);
        // Ligne verticale
        imageline($image, (int)$centerX, (int)($centerY - $radius), (int)$centerX, (int)($centerY + $radius), $lineColor);
        // Lignes diagonales
        imageline($image, (int)($centerX - $radius * 0.7), (int)($centerY - $radius * 0.7), (int)($centerX + $radius * 0.7), (int)($centerY + $radius * 0.7), $lineColor);
        imageline($image, (int)($centerX + $radius * 0.7), (int)($centerY - $radius * 0.7), (int)($centerX - $radius * 0.7), (int)($centerY + $radius * 0.7), $lineColor);
        
        // Texte du host en bas
        $fontSize = 3;
        $textWidth = strlen($host) * imagefontwidth($fontSize);
        $textX = ($width - $textWidth) / 2;
        $textY = $height - 40;
        
        // Ombre du texte
        $shadowColor = imagecolorallocate($image, 200, 200, 200);
        imagestring($image, $fontSize, (int)$textX + 2, (int)$textY + 2, $host, $shadowColor);
        imagestring($image, $fontSize, (int)$textX, (int)$textY, $host, $textColor);
        
        // Capturer l'image en mémoire avec compression
        ob_start();
        // Compression niveau 6 (bon compromis qualité/taille)
        imagepng($image, null, 6);
        $imageData = ob_get_clean();
        imagedestroy($image);
        
        return $imageData;
    }

    /**
     * Crée une réponse HTTP avec une image PNG depuis des données en mémoire
     */
    private function createImageResponseFromData(string $imageData): ResponseInterface
    {
        $stream = new Stream('php://memory', 'r+');
        $stream->write($imageData);
        $stream->rewind();
        
        return new Response($stream, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
