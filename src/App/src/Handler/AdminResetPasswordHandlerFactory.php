<?php

declare(strict_types=1);

namespace App\Handler;

use App\Service\UserRepository;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function assert;

final class AdminResetPasswordHandlerFactory
{
    public function __invoke(ContainerInterface $container): RequestHandlerInterface
    {
        $template = $container->has(TemplateRendererInterface::class)
            ? $container->get(TemplateRendererInterface::class)
            : null;
        assert($template instanceof TemplateRendererInterface || null === $template);

        return new AdminResetPasswordHandler(
            $template,
            $container->get(UserRepository::class)
        );
    }
}
