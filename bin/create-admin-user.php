#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Crée un utilisateur administrateur en base.
 * Usage: php bin/create-admin-user.php <email> <mot_de_passe>
 * Exemple: php bin/create-admin-user.php admin@ebest.pro "MonMotDePasseSecurise"
 */

chdir(__DIR__ . '/../');

require 'vendor/autoload.php';

$email = $argv[1] ?? null;
$password = $argv[2] ?? null;

if ($email === null || $password === null) {
    echo "Usage: php bin/create-admin-user.php <email> <mot_de_passe>\n";
    echo "Exemple: php bin/create-admin-user.php admin@ebest.pro \"MonMotDePasse\"\n";
    exit(1);
}

$email = trim($email);
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Erreur: adresse e-mail invalide.\n";
    exit(1);
}

if (strlen($password) < 8) {
    echo "Erreur: le mot de passe doit contenir au moins 8 caractères.\n";
    exit(1);
}

$config = require 'config/config.php';
$container = require 'config/container.php';

try {
    $userRepository = $container->get(\App\Service\UserRepository::class);
} catch (Throwable $e) {
    echo "Erreur: impossible de charger le service.\n";
    echo $e->getMessage() . "\n";
    if (str_contains($e->getMessage(), 'could not find driver')) {
        echo "\nPour SQLite : installez l'extension PHP (ex. php-sqlite3).\n";
        echo "Ou configurez MySQL/PostgreSQL dans config/autoload/local.php (clé database.dsn).\n";
    }
    exit(1);
}

$existing = $userRepository->findByEmail($email);
if ($existing !== null) {
    echo "Un utilisateur avec cet e-mail existe déjà. Utilisez « Mot de passe oublié » pour réinitialiser.\n";
    exit(1);
}

$userRepository->createUser($email, $password);
echo "Utilisateur créé : $email\n";
echo "Vous pouvez vous connecter sur /admin/login avec cet e-mail et le mot de passe saisi.\n";
exit(0);
