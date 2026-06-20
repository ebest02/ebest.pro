<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

final class UserRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, email, password_hash, created_at FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function verifyPassword(string $email, string $password): ?array
    {
        $user = $this->findByEmail($email);
        if ($user === null) {
            return null;
        }
        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }
        return $user;
    }

    public function createUser(string $email, string $password): void
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)');
        $stmt->execute([$email, $hash]);
    }

    public function updatePassword(int $userId, string $newPassword): void
    {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$hash, $userId]);
    }

    public function createResetToken(string $email): ?string
    {
        $user = $this->findByEmail($email);
        if ($user === null) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 heure

        $stmt = $this->pdo->prepare(
            'INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$user['id'], $token, $expiresAt]);

        return $token;
    }

    public function findValidToken(string $token): ?array
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'SELECT prt.id, prt.user_id, u.email FROM password_reset_tokens prt
             JOIN users u ON u.id = prt.user_id
             WHERE prt.token = ? AND prt.expires_at > ?'
        );
        $stmt->execute([$token, $now]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function consumeTokenAndUpdatePassword(string $token, string $newPassword): bool
    {
        $data = $this->findValidToken($token);
        if ($data === null) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $this->updatePassword((int) $data['user_id'], $newPassword);
            $stmt = $this->pdo->prepare('DELETE FROM password_reset_tokens WHERE token = ?');
            $stmt->execute([$token]);
            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function deleteExpiredTokens(): void
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('DELETE FROM password_reset_tokens WHERE expires_at <= ?');
        $stmt->execute([$now]);
    }
}
