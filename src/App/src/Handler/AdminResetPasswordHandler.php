<?php

declare(strict_types=1);

namespace App\Handler;

use App\Service\UserRepository;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AdminResetPasswordHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly ?TemplateRendererInterface $template,
        private readonly UserRepository $userRepository
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = $request->getQueryParams()['token'] ?? '';
        $method = $request->getMethod();
        $error = null;
        $success = false;

        if ($token === '') {
            return new RedirectResponse('/admin/forgot-password');
        }

        if ($method === 'GET' && $this->userRepository->findValidToken($token) === null) {
            $data = ['token' => null, 'error' => 'Ce lien a expiré ou est invalide.'];
            return new HtmlResponse($this->template->render('app::admin-reset-password', $data));
        }

        if ($method === 'POST') {
            $body = $request->getParsedBody();
            $password = $body['password'] ?? '';
            $confirm = $body['password_confirm'] ?? '';

            if (strlen($password) < 8) {
                $error = 'Le mot de passe doit contenir au moins 8 caractères.';
            } elseif ($password !== $confirm) {
                $error = 'Les deux mots de passe ne correspondent pas.';
            } elseif ($this->userRepository->consumeTokenAndUpdatePassword($token, $password)) {
                $success = true;
                $data = ['success' => true];
                return new HtmlResponse($this->template->render('app::admin-reset-password', $data));
            } else {
                $error = 'Ce lien a expiré ou est invalide. Demandez une nouvelle réinitialisation.';
            }
        }

        $data = [
            'token' => $token,
            'error' => $error,
        ];

        return new HtmlResponse($this->template->render('app::admin-reset-password', $data));
    }
}
