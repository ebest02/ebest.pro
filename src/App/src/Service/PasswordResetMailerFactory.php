<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Container\ContainerInterface;

final class PasswordResetMailerFactory
{
    public function __invoke(ContainerInterface $container): PasswordResetMailer
    {
        return PasswordResetMailer::fromContainer($container);
    }
}
