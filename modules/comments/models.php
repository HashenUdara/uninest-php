<?php

/**
 * Comments Module — Models
 */

function comments_known_target_types(): array
{
    return ['resource', 'feed_post', 'kuppi_request', 'quiz', 'quiz_question'];
}

function comments_max_depth(): int
{
    // 0=root, 1=reply, 2=nested-reply => 3 visible levels
    return 2;
}

function comments_max_depth_for_target(string $targetType): int
{
    return match ($targetType) {
        'feed_post' => 25,
        'resource' => comments_max_depth(),
        'kuppi_request' => comments_max_depth(),
        'quiz' => comments_max_depth(),
        'quiz_question' => comments_max_depth(),
        default => comments_max_depth(),
    };
}

function comments_max_body_length(): int
{
    return 2000;
}

function comments_target_type_is_known(string $targetType): bool
{
    return in_array($targetType, comments_known_target_types(), true);
}

function comments_find_by_id(int $commentId): ?array
{
    return db_fetch(
        "SELECT c.*,
                u.name AS user_name,
                u.role AS user_role
         FROM comments c
         LEFT JOIN users u ON u.id = c.user_id
         WHERE c.id = ?
         LIMIT 1",
        [$commentId]
    );
}

function comments_find_target_comment(int $commentId, string $targetType, int $targetId): ?array
{
    return db_fetch(
        "SELECT c.*,
                u.name AS user_name,
                u.role AS user_role
         FROM comments c
         LEFT JOIN users u ON u.id = c.user_id
         WHERE c.id = ?
           AND c.target_type = ?
           AND c.target_id = ?
         LIMIT 1",
        [$commentId, $targetType, $targetId]
    );
}

function comments_count_for_target(string $targetType, int $targetId): int
{
    $row = db_fetch(
        "SELECT COUNT(*) AS cnt
         FROM comments
         WHERE target_type = ?
           AND target_id = ?",
        [$targetType, $targetId]
    );

    return (int) ($row['cnt'] ?? 0);
}

function comments_counts_for_target_ids(string $targetType, array $targetIds): array
{
    $targetIds = array_values(array_unique(array_filter(array_map(
        static fn($id): int => (int) $id,
        $targetIds
    ), static fn($id): bool => $id > 0)));

    if (empty($targetIds)) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($targetIds), '?'));
    $params = array_merge([$targetType], $targetIds);

    $rows = db_fetch_all(
        "SELECT target_id, COUNT(*) AS cnt
         FROM comments
         WHERE target_type = ?
           AND target_id IN ({$placeholders})
         GROUP BY target_id",
        $params
    );

    $counts = [];
    foreach ($rows as $row) {
        $counts[(int) $row['target_id']] = (int) ($row['cnt'] ?? 0);
    }

    return $counts;
}

function comments_insert(
    string $targetType,
    int $targetId,
    int $userId,
    string $body,
    ?int $parentCommentId,
    int $depth
): int {
    return (int) db_insert('comments', [
        'target_type' => $targetType,
        'target_id' => $targetId,
        'parent_comment_id' => $parentCommentId,
        'depth' => $depth,
        'user_id' => $userId,
        'body' => $body,
    ]);
}

function comments_update_body_by_author(int $commentId, int $authorUserId, string $body): bool
{
    $existing = db_fetch(
        'SELECT id FROM comments WHERE id = ? AND user_id = ? LIMIT 1',
        [$commentId, $authorUserId]
    );
    if (!$existing) {
        return false;
    }

    db_query(
        'UPDATE comments SET body = ? WHERE id = ?',
        [$body, $commentId]
    );

    return true;
}

function comments_delete_by_id(int $commentId): bool
{
    $deleted = db_query('DELETE FROM comments WHERE id = ?', [$commentId])->rowCount();
    return $deleted > 0;
}

function comments_delete_for_target(string $targetType, int $targetId): int
{
    return db_query(
        'DELETE FROM comments WHERE target_type = ? AND target_id = ?',
        [$targetType, $targetId]
    )->rowCount();
}

function comments_delete_for_target_ids(string $targetType, array $targetIds): int
{
    $targetIds = array_values(array_unique(array_filter(array_map(
        static fn($id): int => (int) $id,
        $targetIds
    ), static fn($id): bool => $id > 0)));

    if (empty($targetIds)) {
        return 0;
    }

    $placeholders = implode(', ', array_fill(0, count($targetIds), '?'));
    $params = array_merge([$targetType], $targetIds);

    return db_query(
        "DELETE FROM comments
         WHERE target_type = ?
           AND target_id IN ({$placeholders})",
        $params
    )->rowCount();
}

function comments_rows_for_target(string $targetType, int $targetId): array
{
    return db_fetch_all(
        "SELECT c.*,
                u.name AS user_name,
                u.role AS user_role
         FROM comments c
         LEFT JOIN users u ON u.id = c.user_id
         WHERE c.target_type = ?
           AND c.target_id = ?
         ORDER BY c.created_at ASC, c.id ASC",
        [$targetType, $targetId]
    );
}

function comments_tree_for_target(string $targetType, int $targetId): array
{
    $rows = comments_rows_for_target($targetType, $targetId);
    if (empty($rows)) {
        return [];
    }

    $nodes = [];
    foreach ($rows as $row) {
        $row['depth'] = (int) ($row['depth'] ?? 0);
        $row['children'] = [];
        $nodes[(int) $row['id']] = $row;
    }

    $roots = [];
    foreach ($nodes as $id => &$node) {
        $parentId = (int) ($node['parent_comment_id'] ?? 0);
        if ($parentId > 0 && isset($nodes[$parentId])) {
            $nodes[$parentId]['children'][] = &$node;
            continue;
        }

        $roots[] = &$node;
    }
    unset($node);

    usort($roots, static function (array $a, array $b): int {
        $createdCompare = strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        if ($createdCompare !== 0) {
            return $createdCompare;
        }

        return (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0);
    });

    return array_values($roots);
}
