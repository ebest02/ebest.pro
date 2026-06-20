<?php

declare(strict_types=1);

namespace App\Handler;

use App\Service\WiktionnaireService;
use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class PalindromesHandler implements RequestHandlerInterface
{
    private const DEFAULT_PALINDROMES = ['radar', 'kayak', 'été', 'non', 'elle', 'Anna', 'Laval'];

    public function __construct(
        private readonly ?TemplateRendererInterface $template = null,
        private readonly string $palindromesFilePath = '',
        private readonly ?WiktionnaireService $wiktionnaire = null,
        private readonly string $pythonScriptPath = ''
    ) {
    }

    /** Palindromes longs affichés très souvent dans l'encart du haut (à compléter si besoin). */
    private const FEATURED_LONG_PALINDROMES = [
        'Éve, le sexe se lève.',
        'L\'âme des uns n\'use de mal.',
    ];

    private const COUNT_RANDOM = 150;
    private const SORT_RANDOM = 'random';
    private const SORT_ALPHA = 'alpha';
    private const SORT_ALPHA_DESC = 'alpha_desc';
    private const SORT_LENGTH_ASC = 'length_asc';
    private const SORT_LENGTH_DESC = 'length_desc';

    private const SESSION_KEY_GENERATED = 'palindromes_generes';

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $postBody = $request->getMethod() === 'POST' ? $request->getParsedBody() : null;
        if ($request->getMethod() === 'POST' && ! is_array($postBody)) {
            $raw = $request->getBody()->getContents();
            if ($raw !== '') {
                parse_str($raw, $postBody);
                $postBody = is_array($postBody) ? $postBody : [];
            } else {
                $postBody = [];
            }
        }
        $params = array_merge(
            $request->getQueryParams(),
            is_array($postBody) ? $postBody : []
        );

        if (isset($params['clear']) && (string) $params['clear'] === '1') {
            unset($_SESSION[self::SESSION_KEY_GENERATED]);
            $redirectUrl = '/palindromes';
            $q = [];
            if (isset($params['sort']) && (string) $params['sort'] !== '') {
                $q['sort'] = (string) $params['sort'];
            }
            if (isset($params['voir_tout']) && (string) $params['voir_tout'] !== '') {
                $q['voir_tout'] = (string) $params['voir_tout'];
            }
            if (isset($params['longs']) && (string) $params['longs'] !== '') {
                $q['longs'] = (string) $params['longs'];
            }
            if ($q !== []) {
                $redirectUrl .= '?' . http_build_query($q);
            }
            return new \Laminas\Diactoros\Response\RedirectResponse($redirectUrl, 302);
        }

        $list = $this->loadPalindromesList();
        $voirTout = isset($params['voir_tout']) && (string) $params['voir_tout'] === '1';
        $longs = isset($params['longs']) && (string) $params['longs'] === '1';
        $sort = (string) ($params['sort'] ?? self::SORT_RANDOM);

        if ($longs) {
            $list = $this->filterLongPalindromes($list, 30);
        }

        if ($voirTout) {
            $palindromes = $list;
        } else {
            $palindromes = $this->pickRandom(self::COUNT_RANDOM, $list);
        }
        $palindromes = $this->applySort($palindromes, $sort);

        $form = [
            'nb_mots'   => (int) ($params['nb_mots'] ?? 5),
            'verbes'    => isset($params['verbes']),
            'adjectifs' => isset($params['adjectifs']),
            'pronoms'   => isset($params['pronoms']),
            'noms'      => isset($params['noms']),
        ];
        $form['nb_mots'] = max(1, min(20, $form['nb_mots']));

        $generated = null;
        $generatedList = $_SESSION[self::SESSION_KEY_GENERATED] ?? [];
        if (! is_array($generatedList)) {
            $generatedList = [];
        }

        $isGenerateForm = $request->getMethod() === 'POST'
            && (array_key_exists('nb_mots', $params) || ($params['action'] ?? '') === 'generate');
        $generatedMessage = null;
        if ($isGenerateForm) {
            $generated = $this->generateOne($list, $form, $generatedList, $generatedMessage);
            $generated = is_string($generated) ? trim($generated) : '';
            if ($generated !== '') {
                $generatedList[] = $generated;
                $_SESSION[self::SESSION_KEY_GENERATED] = $generatedList;
            }
        }

        $searchQuery   = trim((string) ($params['search'] ?? ''));
        $searchResults = [];
        if ($searchQuery !== '') {
            $searchResults = $this->searchInFile($list, $searchQuery);
        }

        $verifyPhrase = trim((string) ($params['verify'] ?? ''));
        $verifyFound  = null;
        if ($verifyPhrase !== '') {
            $verifyFound = $this->isPalindromeInFile($list, $verifyPhrase);
        }

        $longPalindromes = $this->filterLongPalindromes($this->loadPalindromesList(), 30);
        $featuredLongPalindrome = $this->pickFeaturedLongPalindrome($longPalindromes);

        $refreshMode = isset($params['refresh']) && (string) $params['refresh'] === '1';

        $data = [
            'palindromes'        => $palindromes,
            'form'               => $form,
            'generated'          => $generated,
            'generated_message'  => $generatedMessage,
            'generated_list'     => array_reverse($generatedList),
            'sort'               => $sort,
            'voir_tout'          => $voirTout,
            'longs'              => $longs,
            'search_query'       => $searchQuery,
            'search_results'     => $searchResults,
            'verify_phrase'      => $verifyPhrase,
            'verify_found'       => $verifyFound,
            'featured_long_palindrome' => $featuredLongPalindrome,
            'refresh_mode'       => $refreshMode,
        ];

        $response = new HtmlResponse($this->template->render('app::palindromes', $data));
        if ($refreshMode) {
            $response = $response
                ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
                ->withHeader('Pragma', 'no-cache')
                ->withHeader('Expires', '0');
        }
        return $response;
    }

    /**
     * Génère un palindrome avec exactement le nombre de mots demandé, ou à défaut avec un mot de moins + message.
     *
     * @param list<string> $alreadyGenerated Palindromes déjà affichés dans cette session
     * @param string|null $generatedMessage Message à afficher si on a dû enlever un mot (ex. "Aucun palindrome à 11 mots ; affichage à 10 mots.")
     */
    private function generateOne(array $list, array $form, array $alreadyGenerated = [], ?string &$generatedMessage = null): string
    {
        $nbMots = max(1, min(20, $form['nb_mots']));

        $fromPython = $this->tryGenerateWithPythonScript($form, $nbMots, $alreadyGenerated);
        if ($fromPython !== null && $fromPython !== '' && $this->wordCount($fromPython) === $nbMots) {
            return $fromPython;
        }

        $fromApi = $this->tryGenerateFromDictionary($form, $alreadyGenerated);
        if ($fromApi !== null && $fromApi !== '' && $this->wordCount($fromApi) === $nbMots) {
            return $fromApi;
        }

        if ($list === []) {
            return '';
        }

        $wantVerbes    = $form['verbes'];
        $wantAdjectifs = $form['adjectifs'];
        $wantPronoms   = $form['pronoms'];
        $wantNoms      = $form['noms'];
        $nbCategories = ($wantVerbes ? 1 : 0) + ($wantAdjectifs ? 1 : 0) + ($wantPronoms ? 1 : 0) + ($wantNoms ? 1 : 0);
        $hasCategoryFilter = $nbCategories > 0;

        // Uniquement palindromes avec exactement nbMots mots (tolérance 0)
        $candidates = $this->collectCandidates(
            $list,
            $nbMots,
            $wantVerbes,
            $wantAdjectifs,
            $wantPronoms,
            $wantNoms,
            $nbCategories,
            $hasCategoryFilter,
            0
        );
        $candidates = $this->excludeAlreadyGenerated($candidates, $alreadyGenerated);

        if ($candidates !== []) {
            return $candidates[array_rand($candidates)];
        }

        // Impossible avec nbMots : essayer avec nbMots - 1 et notifier
        if ($nbMots > 1) {
            $fallbackCount = $nbMots - 1;
            $candidates = $this->collectCandidates(
                $list,
                $fallbackCount,
                $wantVerbes,
                $wantAdjectifs,
                $wantPronoms,
                $wantNoms,
                $nbCategories,
                $hasCategoryFilter,
                0
            );
            $candidates = $this->excludeAlreadyGenerated($candidates, $alreadyGenerated);
            if ($candidates !== []) {
                $generatedMessage = 'Aucun palindrome avec ' . $nbMots . ' mot' . ($nbMots > 1 ? 's' : '') . ' ; affichage d\'un palindrome à ' . $fallbackCount . ' mot' . ($fallbackCount > 1 ? 's' : '') . '.';
                return $candidates[array_rand($candidates)];
            }
        }

        // Dernier recours : exactement nbMots sans filtre catégories
        $candidates = $this->collectCandidates($list, $nbMots, false, false, false, false, 0, false, 0);
        $candidates = $this->excludeAlreadyGenerated($candidates, $alreadyGenerated);
        if ($candidates !== []) {
            return $candidates[array_rand($candidates)];
        }

        if ($nbMots > 1) {
            $fallbackCount = $nbMots - 1;
            $candidates = $this->collectCandidates($list, $fallbackCount, false, false, false, false, 0, false, 0);
            $candidates = $this->excludeAlreadyGenerated($candidates, $alreadyGenerated);
            if ($candidates !== []) {
                $generatedMessage = 'Aucun palindrome avec ' . $nbMots . ' mot' . ($nbMots > 1 ? 's' : '') . ' ; affichage d\'un palindrome à ' . $fallbackCount . ' mot' . ($fallbackCount > 1 ? 's' : '') . '.';
                return $candidates[array_rand($candidates)];
            }
        }

        // Ultime recours : n'importe quel palindrome (exact count si possible)
        foreach ([$nbMots, $nbMots - 1] as $target) {
            if ($target < 1) {
                continue;
            }
            $valid = [];
            foreach ($list as $p) {
                if (! $this->isPalindrome($p)) {
                    continue;
                }
                $n = count(preg_split('/\s+/u', trim($p), -1, PREG_SPLIT_NO_EMPTY));
                if ($n !== $target) {
                    continue;
                }
                $valid[] = $p;
            }
            $valid = $this->excludeAlreadyGenerated($valid, $alreadyGenerated);
            if ($valid !== []) {
                if ($target < $nbMots) {
                    $generatedMessage = 'Aucun palindrome avec ' . $nbMots . ' mot' . ($nbMots > 1 ? 's' : '') . ' ; affichage d\'un palindrome à ' . $target . ' mot' . ($target > 1 ? 's' : '') . '.';
                }
                return $valid[array_rand($valid)];
            }
        }

        $generatedMessage = 'Impossible de générer un palindrome avec ' . $nbMots . ' mot'
            . ($nbMots > 1 ? 's' : '') . '. Réessayez avec un autre nombre de mots ou sans filtre de catégories.';
        return '';
    }

    /**
     * Retire des candidats ceux déjà générés (comparaison normalisée : trim, casse, ponctuation ignorée).
     *
     * @param list<string> $candidates
     * @param list<string> $alreadyGenerated
     * @return list<string>
     */
    private function excludeAlreadyGenerated(array $candidates, array $alreadyGenerated): array
    {
        if ($alreadyGenerated === []) {
            return $candidates;
        }
        $normalized = [];
        foreach ($alreadyGenerated as $g) {
            $normalized[$this->normalizedGeneratedKey($g)] = true;
        }
        $out = [];
        foreach ($candidates as $c) {
            if (! isset($normalized[$this->normalizedGeneratedKey($c)])) {
                $out[] = $c;
            }
        }
        return $out;
    }

    /**
     * Clé de comparaison pour exclure les doublons (casse, espaces et ponctuation normalisés).
     */
    private function normalizedGeneratedKey(string $s): string
    {
        $key = strtolower(trim(preg_replace('/[\p{P}\p{S}\s]+/u', ' ', $s)));
        return preg_replace('/\s+/u', ' ', $key);
    }

    /**
     * Normalise pour recherche / comparaison : lettres uniquement, minuscules, sans accents.
     */
    private function normalizeForSearch(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        if (class_exists('Normalizer', false)) {
            $s = \Normalizer::normalize($s, \Normalizer::FORM_D);
        }
        $s = preg_replace('/\p{M}/u', '', $s);
        return preg_replace('/[^\p{L}]/u', '', $s);
    }

    /**
     * Recherche dans le fichier les palindromes contenant tous les mots donnés (ordre indifférent).
     *
     * @param list<string> $list Liste complète des lignes du fichier
     * @return list<string>
     */
    private function searchInFile(array $list, string $query): array
    {
        $tokens = preg_split('/\s+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY);
        if ($tokens === []) {
            return [];
        }
        $words = array_map([$this, 'normalizeForSearch'], $tokens);
        $words = array_filter($words, static fn (string $w): bool => $w !== '');
        if ($words === []) {
            return [];
        }
        $out = [];
        foreach ($list as $line) {
            $normLine = $this->normalizeForSearch($line);
            $ok = true;
            foreach ($words as $w) {
                if (mb_strpos($normLine, $w, 0, 'UTF-8') === false) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $out[] = $line;
            }
        }
        return $out;
    }

    /**
     * Vérifie si une phrase (normalisée) est présente dans le fichier.
     *
     * @param list<string> $list Liste complète des lignes du fichier
     */
    private function isPalindromeInFile(array $list, string $phrase): bool
    {
        $normPhrase = $this->normalizeForSearch($phrase);
        if ($normPhrase === '') {
            return false;
        }
        foreach ($list as $line) {
            if ($this->normalizeForSearch($line) === $normPhrase) {
                return true;
            }
        }
        return false;
    }

    /**
     * Vérifie que le mot/phrase ne contient que des caractères utilisés en français (lettres latines, espaces, apostrophe, trait d'union).
     * Exclut les caractères CJK, symboles mathématiques, etc.
     */
    private function isLikelyFrenchWord(string $s): bool
    {
        $s = trim($s);
        if ($s === '') {
            return false;
        }
        return (bool) preg_match('/^[\p{Latin}\p{N}\s\-\'\'’]+$/u', $s);
    }

    /**
     * Collecte les palindromes candidats : nombre de mots (exact si $tolerance 0, sinon ±1 ou ±2).
     *
     * @param int $tolerance 0 = exactement nbMots, 1 = nbMots±1, 2 = nbMots±2
     * @return list<string>
     */
    private function collectCandidates(
        array $list,
        int $nbMots,
        bool $wantVerbes,
        bool $wantAdjectifs,
        bool $wantPronoms,
        bool $wantNoms,
        int $nbCategories,
        bool $hasCategoryFilter,
        int $tolerance
    ): array {
        $minWords = $hasCategoryFilter ? max(2, $nbCategories) : 1;

        $candidates = [];
        foreach ($list as $p) {
            if (! $this->isPalindrome($p)) {
                continue;
            }
            $words = preg_split('/\s+/u', trim($p), -1, PREG_SPLIT_NO_EMPTY);
            $n = count($words);
            if ($n < $minWords) {
                continue;
            }
            if (abs($n - $nbMots) > $tolerance) {
                continue;
            }
            if ($hasCategoryFilter && $this->wiktionnaire !== null) {
                if (! $this->palindromeHasAllRequestedCategories($p, $wantVerbes, $wantAdjectifs, $wantPronoms, $wantNoms)) {
                    continue;
                }
            }
            // Ne garder que les palindromes dont tous les mots figurent dans le dictionnaire en ligne
            if ($this->wiktionnaire !== null && ! $this->palindromeHasOnlyDictionaryWords($p)) {
                continue;
            }
            $candidates[] = $p;
        }
        return $candidates;
    }

    /**
     * Vérifie que tous les mots du palindrome figurent dans le dictionnaire (Wiktionnaire).
     */
    private function palindromeHasOnlyDictionaryWords(string $palindrome): bool
    {
        if ($this->wiktionnaire === null) {
            return true;
        }
        $words = preg_split('/\s+/u', trim($palindrome), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($words as $word) {
            $clean = preg_replace('/[\p{P}\p{S}]/u', '', $word);
            if ($clean !== '' && ! $this->wiktionnaire->wordExists($clean)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Vérifie que le palindrome contient au moins un mot de chaque catégorie cochée
     * (verbe, adjectif, pronom, nom). Si une catégorie n'est pas cochée, elle n'est pas exigée.
     */
    private function palindromeHasAllRequestedCategories(
        string $palindrome,
        bool $wantVerbes,
        bool $wantAdjectifs,
        bool $wantPronoms,
        bool $wantNoms
    ): bool {
        if ($this->wiktionnaire === null) {
            return true;
        }
        $words = preg_split('/\s+/u', trim($palindrome), -1, PREG_SPLIT_NO_EMPTY);
        $hasVerbe = false;
        $hasAdjectif = false;
        $hasPronom = false;
        $hasNom = false;
        foreach ($words as $word) {
            $clean = preg_replace('/[\p{P}\p{S}]/u', '', $word);
            if ($clean === '') {
                continue;
            }
            if ($wantVerbes && $this->wiktionnaire->wordHasType($clean, 'verbe')) {
                $hasVerbe = true;
            }
            if ($wantAdjectifs && $this->wiktionnaire->wordHasType($clean, 'adjectif')) {
                $hasAdjectif = true;
            }
            if ($wantPronoms && $this->wiktionnaire->wordHasType($clean, 'pronom')) {
                $hasPronom = true;
            }
            if ($wantNoms && $this->wiktionnaire->wordHasType($clean, 'nom')) {
                $hasNom = true;
            }
        }
        return (! $wantVerbes || $hasVerbe)
            && (! $wantAdjectifs || $hasAdjectif)
            && (! $wantPronoms || $hasPronom)
            && (! $wantNoms || $hasNom);
    }

    /**
     * @param list<string> $list
     * @return list<string>
     */
    private function applySort(array $list, string $sort): array
    {
        if ($sort === self::SORT_RANDOM) {
            shuffle($list);
            return $list;
        }
        if ($sort === self::SORT_ALPHA) {
            usort($list, static fn (string $a, string $b) => strcasecmp($a, $b));
            return $list;
        }
        if ($sort === self::SORT_ALPHA_DESC) {
            usort($list, static fn (string $a, string $b) => -strcasecmp($a, $b));
            return $list;
        }
        if ($sort === self::SORT_LENGTH_ASC) {
            usort($list, static fn (string $a, string $b) => strlen($a) <=> strlen($b));
            return $list;
        }
        if ($sort === self::SORT_LENGTH_DESC) {
            usort($list, static fn (string $a, string $b) => strlen($b) <=> strlen($a));
            return $list;
        }
        return $list;
    }

    /** @return list<string> */
    private function loadPalindromesList(): array
    {
        if ($this->palindromesFilePath === '' || ! is_file($this->palindromesFilePath)) {
            return self::DEFAULT_PALINDROMES;
        }
        $content = file_get_contents($this->palindromesFilePath);
        if ($content === false) {
            return self::DEFAULT_PALINDROMES;
        }
        $lines = explode("\n", $content);
        $list = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $list[] = $line;
            }
        }
        return $list !== [] ? $list : self::DEFAULT_PALINDROMES;
    }

    /**
     * Vérifie que la chaîne est bien un palindrome (même lecture dans les deux sens,
     * en ignorant espaces, ponctuation et casse).
     */
    /**
     * Nombre de mots dans une phrase (séparation par espaces).
     */
    private function wordCount(string $s): int
    {
        return count(preg_split('/\s+/u', trim($s), -1, PREG_SPLIT_NO_EMPTY));
    }

    private function isPalindrome(string $s): bool
    {
        $normalized = mb_strtolower(preg_replace('/[^\p{L}]/u', '', $s), 'UTF-8');
        if ($normalized === '') {
            return false;
        }
        $len = mb_strlen($normalized, 'UTF-8');
        $reversed = '';
        for ($i = $len - 1; $i >= 0; $i--) {
            $reversed .= mb_substr($normalized, $i, 1, 'UTF-8');
        }
        return $normalized === $reversed;
    }

    /**
     * Inverse la chaîne (lettres uniquement, minuscules, UTF-8).
     */
    private function reverseLetters(string $s): string
    {
        $normalized = mb_strtolower(preg_replace('/[^\p{L}]/u', '', $s), 'UTF-8');
        $len = mb_strlen($normalized, 'UTF-8');
        $reversed = '';
        for ($i = $len - 1; $i >= 0; $i--) {
            $reversed .= mb_substr($normalized, $i, 1, 'UTF-8');
        }
        return $reversed;
    }

    /**
     * Tente de générer un palindrome via le script Python (dictionnaire FrequencyWords + Wiktionnaire).
     * Retourne null si script absent, échec ou aucun candidat valide. Exclut les déjà générés.
     *
     * @param list<string> $alreadyGenerated
     */
    private function tryGenerateWithPythonScript(array $form, int $nbMots, array $alreadyGenerated = []): ?string
    {
        if ($this->pythonScriptPath === '' || ! is_file($this->pythonScriptPath)) {
            return null;
        }
        if ($nbMots > 12) {
            return null;
        }

        $wantVerbes    = $form['verbes'];
        $wantAdjectifs = $form['adjectifs'];
        $wantPronoms   = $form['pronoms'];
        $wantNoms      = $form['noms'];
        $nbCategories = ($wantVerbes ? 1 : 0) + ($wantAdjectifs ? 1 : 0) + ($wantPronoms ? 1 : 0) + ($wantNoms ? 1 : 0);
        $hasCategoryFilter = $nbCategories > 0;

        $pos = 'tout';
        if ($nbCategories === 1) {
            if ($wantVerbes) {
                $pos = 'verbes';
            } elseif ($wantAdjectifs) {
                $pos = 'adjectifs';
            } elseif ($wantPronoms) {
                $pos = 'pronoms';
            } elseif ($wantNoms) {
                $pos = 'noms';
            }
        }

        $cmd = [
            'python3',
            $this->pythonScriptPath,
            '--machine',
            '--words',
            (string) $nbMots,
            '--limit',
            '20',
            '--pos',
            $pos,
        ];
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = @proc_open(
            $cmd,
            $descriptorSpec,
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );
        if (! is_resource($proc)) {
            return null;
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        if (! is_string($stdout)) {
            return null;
        }

        $excluded = [];
        foreach ($alreadyGenerated as $g) {
            $excluded[$this->normalizedGeneratedKey($g)] = true;
        }

        $candidates = [];
        foreach (explode("\n", $stdout) as $line) {
            $phrase = trim($line);
            if ($phrase === '') {
                continue;
            }
            if (! $this->isPalindrome($phrase)) {
                continue;
            }
            $words = preg_split('/\s+/u', trim($phrase), -1, PREG_SPLIT_NO_EMPTY);
            if (count($words) !== $nbMots) {
                continue;
            }
            if ($hasCategoryFilter && $this->wiktionnaire !== null) {
                if (! $this->palindromeHasAllRequestedCategories($phrase, $wantVerbes, $wantAdjectifs, $wantPronoms, $wantNoms)) {
                    continue;
                }
            }
            if (isset($excluded[$this->normalizedGeneratedKey($phrase)])) {
                continue;
            }
            $candidates[] = $phrase;
        }

        if ($candidates === []) {
            return null;
        }
        return $candidates[array_rand($candidates)];
    }

    /**
     * Tente de créer un nouveau palindrome à partir de mots tirés de l'API dictionnaire (Wiktionnaire).
     * Retourne null si impossible (repli sur le fichier). Exclut les déjà générés.
     *
     * @param list<string> $alreadyGenerated
     */
    private function tryGenerateFromDictionary(array $form, array $alreadyGenerated = []): ?string
    {
        if ($this->wiktionnaire === null) {
            return null;
        }
        $excluded = [];
        foreach ($alreadyGenerated as $g) {
            $excluded[$this->normalizedGeneratedKey($g)] = true;
        }
        $self = $this;
        $isExcluded = static function (string $s) use ($excluded, $self): bool {
            return isset($excluded[$self->normalizedGeneratedKey($s)]);
        };

        $nbMots = max(1, min(20, $form['nb_mots']));
        $wantVerbes    = $form['verbes'];
        $wantAdjectifs = $form['adjectifs'];
        $wantPronoms   = $form['pronoms'];
        $wantNoms      = $form['noms'];
        $categories = [];
        if ($wantVerbes) {
            $categories[] = 'verbe';
        }
        if ($wantAdjectifs) {
            $categories[] = 'adjectif';
        }
        if ($wantPronoms) {
            $categories[] = 'pronom';
        }
        if ($wantNoms) {
            $categories[] = 'nom';
        }

        $palindromeWords = [];
        $batchSize = 40;
        $maxBatches = 3;
        for ($b = 0; $b < $maxBatches; $b++) {
            $titles = $this->wiktionnaire->fetchRandomWordTitles($batchSize);
            foreach ($titles as $word) {
                if (! $this->isLikelyFrenchWord($word) || ! $this->wiktionnaire->wordExists($word)) {
                    continue;
                }
                if (! $this->isPalindrome($word)) {
                    continue;
                }
                if ($categories === []) {
                    $palindromeWords[] = $word;
                } else {
                    foreach ($categories as $cat) {
                        if ($this->wiktionnaire->wordHasType($word, $cat)) {
                            $palindromeWords[] = $word;
                            break;
                        }
                    }
                }
            }
            if (count($palindromeWords) >= 2) {
                break;
            }
        }

        $palindromeWords = array_values(array_unique($palindromeWords));

        if ($palindromeWords === []) {
            return null;
        }

        if ($nbMots === 1) {
            $one = $palindromeWords[array_rand($palindromeWords)];
            return $isExcluded($one) ? null : $one;
        }

        if ($nbMots === 2) {
            foreach ($palindromeWords as $w1) {
                $w2 = $this->reverseLetters($w1);
                if ($w2 !== $w1 && $this->isLikelyFrenchWord($w2) && $this->wiktionnaire->wordExists($w2)) {
                    $phrase = $w1 . ' ' . $w2;
                    if (! $isExcluded($phrase)) {
                        return $phrase;
                    }
                }
            }
            for ($b = 0; $b < 2; $b++) {
                $titles = $this->wiktionnaire->fetchRandomWordTitles(30);
                foreach ($titles as $w1) {
                    if (! $this->isLikelyFrenchWord($w1)) {
                        continue;
                    }
                    $w2 = $this->reverseLetters($w1);
                    if ($w2 !== $w1 && $this->isLikelyFrenchWord($w2) && $this->wiktionnaire->wordExists($w2)) {
                        $phrase = $w1 . ' ' . $w2;
                        if (! $isExcluded($phrase)) {
                            return $phrase;
                        }
                    }
                }
            }
        }

        if ($nbMots === 3 && count($palindromeWords) >= 2) {
            shuffle($palindromeWords);
            foreach ($palindromeWords as $w1) {
                $others = array_values(array_diff($palindromeWords, [$w1]));
                foreach ($others as $w2) {
                    $phrase = $w1 . ' ' . $w2 . ' ' . $w1;
                    if (! $isExcluded($phrase)) {
                        return $phrase;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Garde uniquement les palindromes dont la longueur (en caractères) est > $minLength.
     *
     * @param list<string> $list
     * @return list<string>
     */
    private function filterLongPalindromes(array $list, int $minLength): array
    {
        $out = [];
        foreach ($list as $p) {
            if (strlen($p) > $minLength) {
                $out[] = $p;
            }
        }
        return $out;
    }

    /**
     * Choisit le palindrome long pour l'encart du haut : très souvent un des FEATURED_LONG_PALINDROMES, sinon au hasard dans la liste.
     *
     * @param list<string> $longPalindromes
     */
    private function pickFeaturedLongPalindrome(array $longPalindromes): ?string
    {
        $featured = self::FEATURED_LONG_PALINDROMES;
        if ($featured === []) {
            return $longPalindromes !== [] ? $longPalindromes[array_rand($longPalindromes)] : null;
        }
        if (mt_rand(1, 100) <= 75) {
            return $featured[array_rand($featured)];
        }
        return $longPalindromes !== [] ? $longPalindromes[array_rand($longPalindromes)] : null;
    }

    /**
     * Retourne $n palindromes tirés au hasard sans doublon (sans remise).
     *
     * @param list<string> $list
     * @return list<string>
     */
    private function pickRandom(int $n, array $list): array
    {
        if ($list === []) {
            return [];
        }
        $shuffled = $list;
        shuffle($shuffled);
        return array_slice($shuffled, 0, min($n, count($shuffled)));
    }
}
