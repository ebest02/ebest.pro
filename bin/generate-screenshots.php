#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Script CLI pour générer toutes les vignettes des sites
 * 
 * Usage: php bin/generate-screenshots.php [--force]
 * 
 * Options:
 *   --force    Force la régénération même si la vignette existe déjà
 */

chdir(dirname(__DIR__));

require 'vendor/autoload.php';

use Laminas\Diactoros\ServerRequest;
use App\Handler\ScreenshotHandler;

// Fonction pour générer une vignette
function generateScreenshotForUrl(string $url, bool $force = false): array
{
    $cacheDir = __DIR__ . '/../data/screenshots';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0777, true);
    }
    
    $cacheFile = $cacheDir . '/' . md5($url) . '.png';
    
    // Si le cache existe et qu'on ne force pas, vérifier l'âge
    if (!$force && file_exists($cacheFile)) {
        $age = time() - filemtime($cacheFile);
        // Si la vignette a moins de 24h, on la garde
        if ($age < 86400) {
            return [
                'url' => $url,
                'status' => 'cached',
                'message' => 'Vignette déjà en cache (âge: ' . round($age / 3600, 1) . 'h)',
                'cacheFile' => $cacheFile
            ];
        }
    }
    
    // Supprimer le cache existant si on force
    if ($force && file_exists($cacheFile)) {
        @unlink($cacheFile);
    }
    
    $startTime = microtime(true);
    
    try {
        // Créer une requête HTTP simulée pour utiliser le handler
        $request = new ServerRequest();
        $queryParams = ['url' => $url];
        if ($force) {
            $queryParams['nocache'] = '1';
        }
        $request = $request->withQueryParams($queryParams);
        
        // Créer le handler et générer la vignette
        $screenshotHandler = new ScreenshotHandler();
        $response = $screenshotHandler->handle($request);
        
        // Vérifier si l'image a été générée
        if (file_exists($cacheFile) && filesize($cacheFile) > 1024) {
            $duration = round((microtime(true) - $startTime), 2);
            return [
                'url' => $url,
                'status' => 'success',
                'message' => 'Vignette générée avec succès',
                'duration' => $duration,
                'cacheFile' => $cacheFile,
                'size' => filesize($cacheFile)
            ];
        } else {
            return [
                'url' => $url,
                'status' => 'error',
                'message' => 'La vignette n\'a pas été générée correctement',
                'duration' => round((microtime(true) - $startTime), 2)
            ];
        }
    } catch (\Exception $e) {
        return [
            'url' => $url,
            'status' => 'error',
            'message' => 'Erreur: ' . $e->getMessage(),
            'duration' => round((microtime(true) - $startTime), 2)
        ];
    }
}

// Fonction pour charger les sites
function loadSites(): array
{
    $sitesFile = __DIR__ . '/../data/sites.json';
    
    if (!file_exists($sitesFile)) {
        echo "Erreur: Le fichier sites.json n'existe pas.\n";
        exit(1);
    }
    
    $content = file_get_contents($sitesFile);
    $sites = json_decode($content, true);
    
    if (!is_array($sites)) {
        echo "Erreur: Le fichier sites.json n'est pas valide.\n";
        exit(1);
    }
    
    return $sites;
}

// Main
$force = in_array('--force', $argv);
$scriptStartTime = microtime(true);

echo "=== Génération des vignettes ===\n";
echo "Mode: " . ($force ? "FORCE (régénération)" : "Normal (cache respecté)") . "\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

$sites = loadSites();
$total = count($sites);
$success = 0;
$cached = 0;
$errors = 0;

echo "Nombre de sites à traiter: $total\n\n";

foreach ($sites as $index => $site) {
    $url = $site['url'] ?? '';
    if (empty($url)) {
        continue;
    }
    
    $num = $index + 1;
    echo "[$num/$total] Traitement de: $url\n";
    
    $result = generateScreenshotForUrl($url, $force);
    
    switch ($result['status']) {
        case 'success':
            $success++;
            echo "  ✓ " . $result['message'] . " (" . $result['duration'] . "s, " . round($result['size'] / 1024, 2) . " KB)\n";
            break;
        case 'cached':
            $cached++;
            echo "  ⊙ " . $result['message'] . "\n";
            break;
        case 'error':
            $errors++;
            echo "  ✗ " . $result['message'] . "\n";
            break;
    }
    
    echo "\n";
    
    // Petite pause entre les sites pour éviter la surcharge
    if ($num < $total) {
        sleep(1);
    }
}

$totalDuration = round(microtime(true) - $scriptStartTime, 2);

echo "=== Résumé ===\n";
echo "Total: $total sites\n";
echo "Succès: $success\n";
echo "En cache: $cached\n";
echo "Erreurs: $errors\n";
echo "Durée totale: {$totalDuration}s\n";

exit($errors > 0 ? 1 : 0);
