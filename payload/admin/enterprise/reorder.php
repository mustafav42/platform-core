<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function reorder_response(bool $ok, string $message, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reorder_response(false, 'Geçersiz istek yöntemi.', 405);
}

try {
    $payload = json_decode((string)file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($payload)) {
        throw new RuntimeException('Geçersiz veri gönderildi.');
    }

    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    $requestToken = (string)($payload['csrf_token'] ?? '');
    if ($sessionToken === '' || !hash_equals($sessionToken, $requestToken)) {
        reorder_response(false, 'Oturum doğrulaması başarısız. Sayfayı yenileyin.', 419);
    }

    $type = (string)($payload['type'] ?? '');
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($payload['ids'] ?? [])), static fn(int $id): bool => $id > 0)));
    if (!$ids) {
        throw new RuntimeException('Kaydedilecek sıralama bulunamadı.');
    }

    $pdo = ent_db();
    $pdo->beginTransaction();

    if ($type === 'categories') {
        if (!in_array('sort_order', ent_columns('categories'), true)) {
            throw new RuntimeException('Kategori sıralama alanı bulunamadı.');
        }
        $dbIds = array_map('intval', $pdo->query('SELECT id FROM categories ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
        $checkA = $dbIds; $checkB = $ids;
        sort($checkA); sort($checkB);
        if ($checkA !== $checkB) {
            throw new RuntimeException('Kategori listesi değişti. Sayfayı yenileyip tekrar deneyin.');
        }
        $update = $pdo->prepare('UPDATE categories SET sort_order=? WHERE id=?');
        foreach ($ids as $index => $id) {
            $update->execute([($index + 1) * 10, $id]);
        }
        $pdo->commit();
        reorder_response(true, 'Kategori sırası kaydedildi.');
    }

    if ($type === 'products') {
        if (!in_array('sort_order', ent_columns('products'), true)) {
            throw new RuntimeException('Ürün sıralama alanı bulunamadı.');
        }
        $categoryId = (int)($payload['category_id'] ?? 0);
        if ($categoryId < 1) {
            throw new RuntimeException('Kategori bilgisi eksik.');
        }
        $stmt = $pdo->prepare('SELECT id FROM products WHERE category_id=? ORDER BY id');
        $stmt->execute([$categoryId]);
        $dbIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $checkA = $dbIds; $checkB = $ids;
        sort($checkA); sort($checkB);
        if ($checkA !== $checkB) {
            throw new RuntimeException('Ürün listesi değişti. Sayfayı yenileyip tekrar deneyin.');
        }
        $update = $pdo->prepare('UPDATE products SET sort_order=? WHERE id=? AND category_id=?');
        foreach ($ids as $index => $id) {
            $update->execute([($index + 1) * 10, $id, $categoryId]);
        }
        $pdo->commit();
        reorder_response(true, 'Ürün sırası kaydedildi.');
    }

    throw new RuntimeException('Bilinmeyen sıralama türü.');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    reorder_response(false, $e->getMessage(), 422);
}
