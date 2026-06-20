<?php

declare(strict_types=1);

namespace App\Service;

use function array_unique;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_file;
use function json_decode;
use function json_encode;
use function mb_strtolower;
use function preg_match_all;
use function str_replace;
use function trim;

/**
 * Service gratuit basé sur l'API MediaWiki du Wiktionnaire français (fr.wiktionary.org).
 * Récupère les natures grammaticales des mots (verbe, adjectif, pronom, nom, etc.)
 * avec cache fichier pour limiter les appels API.
 */
final class WiktionnaireService
{
    private const API_URL = 'https://fr.wiktionary.org/w/api.php';
    private const CACHE_KEY_TYPES = 'types';
    private const CACHE_KEY_EMPTY  = 'empty'; // mot absent du Wiktionnaire

    /** Types recherchés dans le wikitext ({{S|verbe|fr}}, {{S|pronom|fr}}, etc.) */
    private const FR_TYPES = [
        'verbe', 'adjectif', 'pronom', 'pronom indéfini', 'pronom interrogatif', 'pronom possessif',
        'nom', 'nom propre', 'adverbe', 'préposition', 'conjonction', 'interjection',
    ];

    public function __construct(
        private readonly string $cacheFilePath = ''
    ) {
    }

    /**
     * Retourne les natures grammaticales du mot (ex. ['verbe'], ['adjectif', 'nom']).
     *
     * @return list<string>
     */
    public function getWordTypes(string $word): array
    {
        $word = trim($word);
        if ($word === '') {
            return [];
        }

        $normalized = $this->normalizeWordForCache($word);
        $cached     = $this->readFromCache($normalized);
        if ($cached !== null) {
            return $cached;
        }

        $types = $this->fetchTypesFromApi($word);
        $this->writeToCache($normalized, $types);
        return $types;
    }

    /**
     * Indique si le mot figure dans le Wiktionnaire (au moins une entrée française).
     */
    public function wordExists(string $word): bool
    {
        return $this->getWordTypes($word) !== [];
    }

    /**
     * Récupère des titres de pages aléatoires depuis le Wiktionnaire (mots français).
     *
     * @return list<string>
     */
    public function fetchRandomWordTitles(int $limit = 20): array
    {
        $limit = max(1, min(50, $limit));
        $url  = self::API_URL . '?action=query&list=random&rnnamespace=0&rnlimit=' . $limit . '&format=json';

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 8,
                'user_agent' => 'EbestPalindromes/1.0 (PHP; dictionnaire)',
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            return [];
        }
        $json = json_decode($raw, true);
        if (! is_array($json) || ! isset($json['query']['random']) || ! is_array($json['query']['random'])) {
            return [];
        }
        $titles = [];
        foreach ($json['query']['random'] as $page) {
            if (isset($page['title']) && is_string($page['title']) && $page['title'] !== '') {
                $titles[] = $page['title'];
            }
        }
        return $titles;
    }

    /**
     * Récupère des mots aléatoires du Wiktionnaire ayant la nature grammaticale demandée.
     *
     * @param string $category 'verbe' | 'adjectif' | 'pronom' | 'nom'
     * @return list<string>
     */
    public function getRandomWordsOfType(string $category, int $count, int $maxBatches = 5): array
    {
        $found = [];
        $batchSize = 25;
        for ($b = 0; $b < $maxBatches && count($found) < $count; $b++) {
            $titles = $this->fetchRandomWordTitles($batchSize);
            foreach ($titles as $word) {
                if ($this->wordHasType($word, $category)) {
                    $found[] = $word;
                    if (count($found) >= $count) {
                        break 2;
                    }
                }
            }
        }
        return $found;
    }

    /**
     * Indique si le mot a la nature grammaticale demandée.
     * @param string $category 'verbe' | 'adjectif' | 'pronom' | 'nom'
     */
    public function wordHasType(string $word, string $category): bool
    {
        $types = $this->getWordTypes($word);
        if ($types === []) {
            return false;
        }
        $valid = match ($category) {
            'verbe' => ['verbe'],
            'adjectif' => ['adjectif'],
            'pronom' => ['pronom', 'pronom indéfini', 'pronom interrogatif', 'pronom possessif'],
            'nom' => ['nom', 'nom propre'],
            default => [],
        };
        foreach ($valid as $t) {
            if (in_array($t, $types, true)) {
                return true;
            }
        }
        return false;
    }

    private function normalizeWordForCache(string $word): string
    {
        $w = mb_strtolower(trim($word), 'UTF-8');
        $w = str_replace([' ', '-', "'", '’'], ['', '', '', ''], $w);
        return $w;
    }

    /** @return list<string>|null null si pas en cache */
    private function readFromCache(string $normalized): ?array
    {
        if ($this->cacheFilePath === '' || ! is_file($this->cacheFilePath)) {
            return null;
        }
        $raw = file_get_contents($this->cacheFilePath);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        if (! is_array($data) || ! isset($data[$normalized])) {
            return null;
        }
        $entry = $data[$normalized];
        if ($entry === self::CACHE_KEY_EMPTY) {
            return [];
        }
        if (is_array($entry)) {
            return array_values($entry);
        }
        return null;
    }

    /** @param list<string> $types */
    private function writeToCache(string $normalized, array $types): void
    {
        if ($this->cacheFilePath === '') {
            return;
        }
        $data = [];
        if (is_file($this->cacheFilePath)) {
            $raw  = file_get_contents($this->cacheFilePath);
            $data = $raw !== false ? (json_decode($raw, true) ?? []) : [];
            if (! is_array($data)) {
                $data = [];
            }
        }
        $data[$normalized] = $types === [] ? self::CACHE_KEY_EMPTY : $types;
        file_put_contents($this->cacheFilePath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * @return list<string>
     */
    private function fetchTypesFromApi(string $word): array
    {
        $page = str_replace(' ', '_', trim($word));
        $url  = self::API_URL . '?action=parse&page=' . rawurlencode($page) . '&prop=wikitext&format=json';

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 5,
                'user_agent' => 'EbestPalindromes/1.0 (PHP; dictionnaire)',
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            return [];
        }
        $json = json_decode($raw, true);
        if (! is_array($json) || ! isset($json['parse']['wikitext']['*'])) {
            return [];
        }
        $wikitext = $json['parse']['wikitext']['*'];
        return $this->parseTypesFromWikitext($wikitext);
    }

    /**
     * @return list<string>
     */
    private function parseTypesFromWikitext(string $wikitext): array
    {
        $found = [];
        foreach (self::FR_TYPES as $type) {
            $pattern = '/\{\{S\|' . preg_quote($type, '/') . '\|fr\}\}/u';
            if (preg_match_all($pattern, $wikitext)) {
                $found[] = $type;
            }
        }
        return array_values(array_unique($found));
    }
}
