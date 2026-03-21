<?php

/**
 * Auth Module — Models
 */

function auth_find_by_email(string $email): ?array
{
    return db_fetch('SELECT * FROM users WHERE email = ?', [$email]);
}

function auth_create_user(array $data): string
{
    return db_insert('users', [
        'name'          => $data['name'],
        'email'         => $data['email'],
        'password'      => password_hash($data['password'], PASSWORD_DEFAULT),
        'role'          => $data['role'] ?? 'student',
        'academic_year' => $data['academic_year'] ?? null,
        'university_id' => $data['university_id'] ?? null,
        'batch_id'      => $data['batch_id'] ?? null,
        'first_approved_batch_id' => $data['first_approved_batch_id'] ?? null,
    ]);
}

function auth_create_password_reset_token(int $userId, string $email, int $ttlMinutes = 60): string
{
    db_query('DELETE FROM password_reset_tokens WHERE user_id = ? OR expires_at <= NOW() OR used_at IS NOT NULL', [$userId]);

    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + ($ttlMinutes * 60));

    db_insert('password_reset_tokens', [
        'user_id'    => $userId,
        'email'      => $email,
        'token_hash' => $hash,
        'expires_at' => $expiresAt,
    ]);

    return $token;
}

function auth_find_valid_password_reset(string $email, string $token): ?array
{
    $hash = hash('sha256', $token);

    return db_fetch(
        "SELECT prt.*, u.id AS resolved_user_id
         FROM password_reset_tokens prt
         INNER JOIN users u ON u.id = prt.user_id
         WHERE prt.email = ?
           AND prt.token_hash = ?
           AND prt.used_at IS NULL
           AND prt.expires_at > NOW()
         ORDER BY prt.id DESC
         LIMIT 1",
        [$email, $hash]
    );
}

function auth_mark_password_reset_used(int $id): void
{
    db_query('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ? AND used_at IS NULL', [$id]);
}

function auth_mark_all_password_resets_used_for_user(int $userId): void
{
    db_query('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$userId]);
}
