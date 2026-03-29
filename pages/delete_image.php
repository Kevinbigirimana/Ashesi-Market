<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();

$img_id     = (int) ($_GET['id'] ?? 0);
$product_id = (int) ($_GET['product_id'] ?? 0);
$uid        = $_SESSION['user_id'];

if ($img_id && $product_id) {
    // Verify ownership
    $stmt = $conn->prepare(
        'SELECT pi.image_path FROM product_images pi
         JOIN products p ON p.id = pi.product_id
         WHERE pi.id = ? AND p.seller_id = ?'
    );
    $stmt->bind_param('ii', $img_id, $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $path = UPLOAD_DIR . $row['image_path'];
        if (file_exists($path)) unlink($path);

        $del = $conn->prepare('DELETE FROM product_images WHERE id = ?');
        $del->bind_param('i', $img_id);
        $del->execute();
        $del->close();

        // Re-assign primary if needed
        $fix = $conn->prepare(
            'UPDATE product_images SET is_primary = 1
             WHERE product_id = ? AND id = (SELECT id FROM (SELECT id FROM product_images WHERE product_id = ? LIMIT 1) t)'
        );
        $fix->bind_param('ii', $product_id, $product_id);
        $fix->execute();
        $fix->close();

        set_flash('success', 'Image removed.');
    }
}

redirect(BASE_URL . '/pages/product_form.php?id=' . $product_id);
