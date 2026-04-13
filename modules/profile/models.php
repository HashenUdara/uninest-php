<?php

/**
 * Profile Module — Models
 */

function profile_user_with_context(int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }

    return db_fetch(
        "SELECT u.id,
                u.name,
                u.email,
                u.role,
                u.academic_year,
                u.university_id,
                u.batch_id,
                u.created_at,
                u.updated_at,
                uni.name AS university_name,
                b.name AS batch_name,
                b.batch_code
         FROM users u
         LEFT JOIN universities uni ON uni.id = u.university_id
         LEFT JOIN batches b ON b.id = u.batch_id
         WHERE u.id = ?
         LIMIT 1",
        [$userId]
    );
}

function profile_user_password_hash(int $userId): ?string
{
    if ($userId <= 0) {
        return null;
    }

    $row = db_fetch('SELECT password FROM users WHERE id = ? LIMIT 1', [$userId]);
    if (!$row) {
        return null;
    }

    $hash = (string) ($row['password'] ?? '');
    return $hash !== '' ? $hash : null;
}

function profile_email_exists(string $email, ?int $excludeUserId = null): bool
{
    if ($excludeUserId !== null && $excludeUserId > 0) {
        return (bool) db_fetch(
            'SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1',
            [$email, $excludeUserId]
        );
    }

    return (bool) db_fetch('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]);
}

function profile_update_user(int $userId, array $data): bool
{
    if ($userId <= 0) {
        return false;
    }

    $updatedRows = db_update('users', [
        'name' => (string) ($data['name'] ?? ''),
        'email' => (string) ($data['email'] ?? ''),
        'academic_year' => $data['academic_year'] ?? null,
    ], ['id' => $userId]);

    return $updatedRows > 0;
}

function profile_update_password_hash(int $userId, string $passwordHash): bool
{
    if ($userId <= 0 || $passwordHash === '') {
        return false;
    }

    $updatedRows = db_update('users', [
        'password' => $passwordHash,
    ], ['id' => $userId]);

    return $updatedRows > 0;
}
