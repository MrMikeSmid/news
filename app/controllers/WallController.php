<?php

declare(strict_types=1);

function wall_page(): void
{
    require_admin();

    $filter = clean_query('category');
    $categories = db()->query("SELECT DISTINCT category FROM sources ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

    $sql = 'SELECT posts.*, sources.name AS source_name, sources.category AS source_category, sources.color AS source_color, sources.type AS source_type
            FROM posts LEFT JOIN sources ON sources.id = posts.source_id';
    $params = [];
    if ($filter !== '') {
        $sql .= ' WHERE sources.category = :category';
        $params['category'] = $filter;
    }
    $sql .= ' ORDER BY posts.published_at DESC LIMIT 100';

    $statement = db()->prepare($sql);
    $statement->execute($params);
    $posts = $statement->fetchAll();

    render('wall', [
        'title' => 'Wall',
        'posts' => $posts,
        'categories' => $categories,
        'activeCategory' => $filter,
    ]);
}

function clean_query(string $key): string
{
    return trim((string) ($_GET[$key] ?? ''));
}

function delete_post(int $id): void
{
    require_admin();
    verify_csrf();

    db()->prepare('DELETE FROM posts WHERE id = :id')->execute(['id' => $id]);
    redirect('wall');
}
