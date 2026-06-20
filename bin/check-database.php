#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Vérifie que la connexion à la base de données configurée (config/autoload/local.php ou database.global.php) fonctionne.
 * Usage: php bin/check-database.php
 */

chdir(__DIR__ . '/../');

require 'vendor/autoload.php';

$config = require 'config/config.php';
$dsn = $config['database']['dsn'] ?? '(non défini)';

echo "DSN configuré : " . (str_contains((string) $dsn, 'password') ? '***' : $dsn) . "\n";

try {
    $container = require 'config/container.php';
    $pdo = $container->get(\PDO::class);
} catch (Throwable $e) {
    echo "ERREUR connexion : " . $e->getMessage() . "\n";
    exit(1);
}

$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "Connexion OK (driver : $driver)\n";

try {
    $pdo->query('SELECT 1');
    echo "Requête test OK.\n";
} catch (Throwable $e) {
    echo "ERREUR requête : " . $e->getMessage() . "\n";
    exit(1);
}

// Vérifier que les tables existent
$table = $driver === 'mysql' ? 'information_schema.tables' : 'sqlite_master';
$stmt = $pdo->query(
    $driver === 'mysql'
        ? "SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('users', 'password_reset_tokens')"
        : "SELECT COUNT(*) AS n FROM sqlite_master WHERE type='table' AND name IN ('users', 'password_reset_tokens')"
);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$count = (int) ($row['n'] ?? 0);

if ($count >= 2) {
    echo "Tables users et password_reset_tokens présentes.\n";
} else {
    echo "Tables manquantes (attendu : users, password_reset_tokens). Elles seront créées au premier accès.\n";
}

echo "Base de données opérationnelle.\n";
exit(0);
