<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use Psr\Container\ContainerInterface;

final class PdoFactory
{
    public function __invoke(ContainerInterface $container): PDO
    {
        $config = $container->get('config')['database'] ?? [];
        $dsn = $config['dsn'] ?? 'sqlite::memory:';
        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;
        $options = $config['options'] ?? [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

        if (!isset($options[PDO::ATTR_ERRMODE])) {
            $options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
        }

        $pdo = new PDO($dsn, $username, $password, $options);

        if ($this->isSqlite($dsn)) {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }

        $this->ensureSchema($pdo, $dsn);

        return $pdo;
    }

    private function isSqlite(string $dsn): bool
    {
        return str_starts_with($dsn, 'sqlite:');
    }

    private function ensureSchema(PDO $pdo, string $dsn): void
    {
        if ($this->isSqlite($dsn)) {
            $this->ensureSchemaSqlite($pdo);
        } else {
            $this->ensureSchemaMysql($pdo);
        }
    }

    private function ensureSchemaSqlite(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        SQL);
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS password_reset_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                token TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reset_token ON password_reset_tokens(token)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reset_expires ON password_reset_tokens(expires_at)');
    }

    private function ensureSchemaMysql(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS password_reset_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_reset_token (token),
                INDEX idx_reset_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }
}
