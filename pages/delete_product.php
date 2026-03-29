<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();

$id  = (int) ($_GET['id'] ?? 0);
$uid = $_SESSION['user_id'];

if ($id) {
    // Only the seller can delete
    $stmt = $conn->prepare('SELECT id, seller_id FROM products WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row && $row['seller_id'] === $uid) {
        // Images are cascade-deleted by FK; also delete files
        $imgs = $conn->prepare('SELECT image_path FROM product_images WHERE product_id = ?');
        $imgs->bind_param('i', $id);
        $imgs->execute();
        foreach ($imgs->get_result()->fetch_all(MYSQLI_ASSOC) as $img) {
            $path = UPLOAD_DIR . $img['image_path'];
            if (file_exists($path)) unlink($path);
        }
        $imgs->close();

        $del = $conn->prepare('DELETE FROM products WHERE id = ? AND seller_id = ?');
        $del->bind_param('ii', $id, $uid);
        $del->execute();
        $del->close();

        set_flash('success', 'Product deleted.');
    } else {
        set_flash('error', 'Unauthorized.');
    }
}

redirect(BASE_URL . '/pages/profile.php?id=' . $uid);
