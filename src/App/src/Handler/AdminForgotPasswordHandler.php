<?php

declare(strict_types=1);

namespace App\Handler;

use App\Service\PasswordResetMailer;
use App\Service\UserRepository;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AdminForgotPasswordHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly ?TemplateRendererInterface $template,
        private readonly UserRepository $userRepository,
        private readonly PasswordResetMailer $mailer
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $method = $request->getMethod();
        $error = null;
        $success = null;

        if ($method === 'POST') {
            $body = $request->getParsedBody();
            $email = trim((string) ($body['email'] ?? ''));

            if ($email === '') {
                $error = 'Veuillez indiquer votre adresse e-mail.';
            } else {
                $token = $this->userRepository->createResetToken($email);
                if ($token !== null) {
                    $this->mailer->sendResetLink($email, $token);
                    $this->userRepository->deleteExpiredTokens();
                }
                $success = 'Si un compte existe pour cette adresse, un e-mail de réinitialisation a été envoyé.';
            }
        }

        $data = [
            'error' => $error,
            'success' => $success,
        ];

        return new HtmlResponse($this->template->render('app::admin-forgot-password', $data));
    }
}
