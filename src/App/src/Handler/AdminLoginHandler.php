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

final class AdminLoginHandler implements RequestHandlerInterface
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

        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            return new RedirectResponse('/admin');
        }

        $method = $request->getMethod();
        $error = null;

        if ($method === 'POST') {
            $body = $request->getParsedBody();
            $email = trim((string) ($body['email'] ?? ''));
            $password = $body['password'] ?? '';

            if ($email === '' || $password === '') {
                $error = 'Veuillez saisir votre e-mail et mot de passe.';
            } else {
                $user = $this->userRepository->verifyPassword($email, $password);
                if ($user !== null) {
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_user_id'] = $user['id'];
                    return new RedirectResponse('/admin');
                }
                $error = 'E-mail ou mot de passe incorrect.';
            }
        }

        $data = [
            'error' => $error,
            'email' => ($request->getParsedBody() ?? [])['email'] ?? null,
        ];

        return new HtmlResponse($this->template->render('app::admin-login', $data));
    }
}
