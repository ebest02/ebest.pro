<?php

declare(strict_types=1);

namespace App;

/**
 * The configuration provider for the App module
 *
 * @see https://docs.laminas.dev/laminas-component-installer/
 */
class ConfigProvider
{
    /**
     * Returns the configuration array
     *
     * To add a bit of a structure, each section is defined in a separate
     * method which returns an array with its configuration.
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
            'templates'    => $this->getTemplates(),
        ];
    }

    /**
     * Returns the container dependencies
     */
    public function getDependencies(): array
    {
        return [
            'invokables' => [
                Handler\PingHandler::class => Handler\PingHandler::class,
            ],
            'factories'  => [
                \PDO::class => \App\Service\PdoFactory::class,
                \App\Service\UserRepository::class => \App\Service\UserRepositoryFactory::class,
                \App\Service\WiktionnaireService::class => \App\Service\WiktionnaireServiceFactory::class,
                \App\Service\PasswordResetMailer::class => \App\Service\PasswordResetMailerFactory::class,
                Handler\HomePageHandler::class => Handler\HomePageHandlerFactory::class,
                Handler\StatusCheckHandler::class => Handler\StatusCheckHandlerFactory::class,
                Handler\StatusCheckApiHandler::class => Handler\StatusCheckApiHandlerFactory::class,
                Handler\SitesStatusHandler::class => Handler\SitesStatusHandlerFactory::class,
                Handler\CompetencesHandler::class => Handler\CompetencesHandlerFactory::class,
                Handler\EcritsHandler::class => Handler\EcritsHandlerFactory::class,
                Handler\ScriptsHandler::class => Handler\ScriptsHandlerFactory::class,
                Handler\GematrieHandler::class => Handler\GematrieHandlerFactory::class,
                Handler\EtienneHandler::class => Handler\EtienneHandlerFactory::class,
                Handler\OutilsHandler::class => Handler\OutilsHandlerFactory::class,
                Handler\PalindromesHandler::class => Handler\PalindromesHandlerFactory::class,
                Handler\PalindromesExportHandler::class => Handler\PalindromesExportHandlerFactory::class,
                Handler\PalindromesSaveHandler::class => Handler\PalindromesSaveHandlerFactory::class,
                Handler\AdminHandler::class => Handler\AdminHandlerFactory::class,
                Handler\AdminLoginHandler::class => Handler\AdminLoginHandlerFactory::class,
                Handler\AdminForgotPasswordHandler::class => Handler\AdminForgotPasswordHandlerFactory::class,
                Handler\AdminResetPasswordHandler::class => Handler\AdminResetPasswordHandlerFactory::class,
                Handler\AdminLogoutHandler::class => Handler\AdminLogoutHandlerFactory::class,
                Handler\AdminApiHandler::class => Handler\AdminApiHandlerFactory::class,
                Handler\AdminDocsHandler::class => Handler\AdminDocsHandlerFactory::class,
                Handler\ScreenshotHandler::class => Handler\ScreenshotHandlerFactory::class,
            ],
        ];
    }

    /**
     * Returns the templates configuration
     */
    public function getTemplates(): array
    {
        return [
            'paths' => [
                'app'    => [__DIR__ . '/../templates/app'],
                'error'  => [__DIR__ . '/../templates/error'],
                'layout' => [__DIR__ . '/../templates/layout'],
            ],
        ];
    }
}
