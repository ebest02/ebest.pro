<?php

declare(strict_types=1);

namespace App\Handler;

use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CompetencesHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly ?TemplateRendererInterface $template,
        private readonly string $competencesFilePath
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $markdown = is_file($this->competencesFilePath)
            ? file_get_contents($this->competencesFilePath)
            : '';

        $markdown = $this->removeFirstPhrase($markdown);
        $html = $this->markdownToHtml($markdown);

        return new HtmlResponse($this->template->render('app::competences', ['content' => $html]));
    }

    private function removeFirstPhrase(string $markdown): string
    {
        $lines = explode("\n", $markdown);
        array_shift($lines);
        while ($lines !== [] && trim($lines[0]) === '') {
            array_shift($lines);
        }
        return implode("\n", $lines);
    }

    private function markdownToHtml(string $markdown): string
    {
        $lines = explode("\n", $markdown);
        $out = [];
        $inList = false;

        foreach ($lines as $line) {
            $trimmed = rtrim($line);

            if ($trimmed === '---') {
                if ($inList) {
                    $out[] = '</ul>';
                    $inList = false;
                }
                $out[] = '<hr>';
                continue;
            }

            if (preg_match('/^### (.+)$/', $trimmed, $m)) {
                if ($inList) {
                    $out[] = '</ul>';
                    $inList = false;
                }
                $out[] = '<h3>' . $this->inline($m[1]) . '</h3>';
                continue;
            }

            if (preg_match('/^## (.+)$/', $trimmed, $m)) {
                if ($inList) {
                    $out[] = '</ul>';
                    $inList = false;
                }
                $out[] = '<h2>' . $this->inline($m[1]) . '</h2>';
                continue;
            }

            if (preg_match('/^- (.+)$/', $trimmed, $m)) {
                if (!$inList) {
                    $out[] = '<ul>';
                    $inList = true;
                }
                $out[] = '<li>' . $this->inline($m[1]) . '</li>';
                continue;
            }

            if ($inList) {
                $out[] = '</ul>';
                $inList = false;
            }

            if ($trimmed === '') {
                $out[] = '';
                continue;
            }

            $out[] = '<p>' . $this->inline($trimmed) . '</p>';
        }

        if ($inList) {
            $out[] = '</ul>';
        }

        return implode("\n", $out);
    }

    private function inline(string $s): string
    {
        $placeholders = [];
        $s = preg_replace_callback(
            '/\[([^\]]*)\]\((https?:\/\/[^)]+)\)/',
            function (array $m) use (&$placeholders): string {
                $i = count($placeholders);
                $placeholders[] = ['url' => $m[2], 'text' => $m[1]];
                return "{{LINK{$i}}}";
            },
            $s
        );
        $s = preg_replace_callback(
            '/\*\*(.+?)\*\*/s',
            fn (array $m) => '<strong>' . $this->e($m[1]) . '</strong>',
            $this->e($s)
        );
        foreach ($placeholders as $i => $link) {
            $url = $this->eAttr($link['url']);
            $text = $this->e($link['text']);
            $s = str_replace("{{LINK{$i}}}", '<a href="' . $url . '" target="_blank" rel="noopener">' . $text . '</a>', $s);
        }
        return $s;
    }

    /**
     * Échappe pour affichage dans du HTML (contenu texte).
     * N'échappe pas l'apostrophe (') pour éviter d'afficher &apos; — on n'est pas dans un attribut.
     * Échappe : & < > "
     */
    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_HTML5 | ENT_COMPAT, 'UTF-8');
    }

    /** Échappe pour un attribut HTML (href, etc.). */
    private function eAttr(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
