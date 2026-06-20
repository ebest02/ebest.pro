<?php

declare(strict_types=1);

use App\Handler\PingHandler;
use App\Handler\StatusCheckHandler;
use App\Handler\StatusCheckApiHandler;
use App\Handler\SitesStatusHandler;
use App\Handler\CompetencesHandler;
use App\Handler\ScriptsHandler;
use App\Handler\AdminHandler;
use App\Handler\AdminLoginHandler;
use App\Handler\AdminForgotPasswordHandler;
use App\Handler\AdminResetPasswordHandler;
use App\Handler\AdminLogoutHandler;
use App\Handler\AdminApiHandler;
use App\Handler\AdminDocsHandler;
use App\Handler\ScreenshotHandler;
use App\Handler\GematrieHandler;
use App\Handler\EtienneHandler;
use App\Handler\OutilsHandler;
use App\Handler\PalindromesHandler;
use App\Handler\PalindromesExportHandler;
use App\Handler\PalindromesSaveHandler;
use App\Handler\EcritsHandler;
use Mezzio\Application;
use Mezzio\MiddlewareFactory;
use Psr\Container\ContainerInterface;

/**
 * FastRoute route configuration
 *
 * @see https://github.com/nikic/FastRoute
 *
 * Setup routes with a single request method:
 *
 * $app->get('/', App\Handler\HomePageHandler::class, 'home');
 * $app->post('/album', App\Handler\AlbumCreateHandler::class, 'album.create');
 * $app->put('/album/{id:\d+}', App\Handler\AlbumUpdateHandler::class, 'album.put');
 * $app->patch('/album/{id:\d+}', App\Handler\AlbumUpdateHandler::class, 'album.patch');
 * $app->delete('/album/{id:\d+}', App\Handler\AlbumDeleteHandler::class, 'album.delete');
 *
 * Or with multiple request methods:
 *
 * $app->route('/contact', App\Handler\ContactHandler::class, ['GET', 'POST', ...], 'contact');
 *
 * Or handling all request methods:
 *
 * $app->route('/contact', App\Handler\ContactHandler::class)->setName('contact');
 *
 * or:
 *
 * $app->route(
 *     '/contact',
 *     App\Handler\ContactHandler::class,
 *     Mezzio\Router\Route::HTTP_METHOD_ANY,
 *     'contact'
 * );
 */

return static function (Application $app, MiddlewareFactory $factory, ContainerInterface $container): void {
    $app->get('/', StatusCheckHandler::class, 'home');
    $app->get('/api/ping', PingHandler::class, 'api.ping');
    $app->get('/sites-status', SitesStatusHandler::class, 'sites-status');
    $app->get('/competences', CompetencesHandler::class, 'competences');
    $app->get('/ecrits', EcritsHandler::class, 'ecrits');
    $app->get('/scripts', ScriptsHandler::class, 'scripts');
    $app->get('/gematrie', GematrieHandler::class, 'gematrie');
    $app->get('/etienne', EtienneHandler::class, 'etienne');
    $app->get('/outils', OutilsHandler::class, 'outils');
    $app->route('/palindromes', PalindromesHandler::class, ['GET', 'POST'], 'palindromes');
    $app->get('/palindromes/export-txt', PalindromesExportHandler::class, 'palindromes.export');
    $app->post('/palindromes/save', PalindromesSaveHandler::class, 'palindromes.save');
    $app->get('/api/status-check', StatusCheckApiHandler::class, 'api.status-check');
    $app->get('/api/screenshot', ScreenshotHandler::class, 'api.screenshot');
    
    // Routes d'administration
    $app->route('/admin/login', AdminLoginHandler::class, ['GET', 'POST'], 'admin.login');
    $app->route('/admin/forgot-password', AdminForgotPasswordHandler::class, ['GET', 'POST'], 'admin.forgot-password');
    $app->route('/admin/reset-password', AdminResetPasswordHandler::class, ['GET', 'POST'], 'admin.reset-password');
    $app->get('/admin', AdminHandler::class, 'admin');
    $app->get('/admin/docs', AdminDocsHandler::class, 'admin.docs');
    $app->get('/admin/logout', AdminLogoutHandler::class, 'admin.logout');
    $app->route('/api/admin/sites', AdminApiHandler::class, ['POST', 'DELETE'], 'api.admin.sites');
};
