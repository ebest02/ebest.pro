<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Container\ContainerInterface;

final class PasswordResetMailer
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $fromEmail,
        private readonly string $fromName = 'ebest.pro Admin'
    ) {
    }

    public static function fromContainer(ContainerInterface $container): self
    {
        $config = $container->get('config');
        $appUrl = $config['app_url'] ?? 'https://ebest.pro';
        $mail = $config['mail'] ?? [];
        return new self(
            rtrim($appUrl, '/'),
            $mail['from_email'] ?? 'noreply@ebest.pro',
            $mail['from_name'] ?? 'ebest.pro Admin'
        );
    }

    public function sendResetLink(string $toEmail, string $token): bool
    {
        $link = $this->baseUrl . '/admin/reset-password?token=' . urlencode($token);
        $subject = 'Réinitialisation du mot de passe - ebest.pro';
        $body = "Bonjour,\n\n"
            . "Une réinitialisation du mot de passe a été demandée pour ce compte.\n\n"
            . "Cliquez sur le lien suivant (valide 1 heure) :\n"
            . $link . "\n\n"
            . "Si vous n'êtes pas à l'origine de cette demande, ignorez ce message.\n\n"
            . "— " . $this->fromName;

        $headers = [
            'From: ' . $this->fromName . ' <' . $this->fromEmail . '>',
            'Reply-To: ' . $this->fromEmail,
            'X-Mailer: PHP/' . PHP_VERSION,
            'Content-Type: text/plain; charset=UTF-8',
        ];

        return @mail($toEmail, $subject, $body, implode("\r\n", $headers));
    }
}
