<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
verify_csrf();

$uid          = $_SESSION['user_id'];
$order_item_id = (int) ($_POST['order_item_id'] ?? 0);
$seller_id    = (int) ($_POST['seller_id'] ?? 0);
$rating       = (int) ($_POST['rating'] ?? 0);
$comment      = trim($_POST['comment'] ?? '');

if (!$order_item_id || $rating < 1 || $rating > 5) {
    set_flash('error', 'Invalid review submission.');
    redirect(BASE_URL . '/pages/orders.php');
}

// Verify: reviewer bought this item, and it belongs to a completed order
$stmt = $conn->prepare(
    'SELECT oi.id, oi.seller_id FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE oi.id = ? AND o.buyer_id = ? AND o.status = "completed"'
);
$stmt->bind_param('ii', $order_item_id, $uid);
$stmt->execute();
$oi = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$oi) {
    set_flash('error', 'You can only review sellers for completed orders.');
    redirect(BASE_URL . '/pages/orders.php');
}

// Prevent self-review
if ($oi['seller_id'] === $uid) {
    set_flash('error', 'You cannot review yourself.');
    redirect(BASE_URL . '/pages/orders.php');
}

// Check duplicate review (order_item_id is UNIQUE in reviews table)
$chk = $conn->prepare('SELECT id FROM reviews WHERE order_item_id = ?');
$chk->bind_param('i', $order_item_id);
$chk->execute();
if ($chk->get_result()->num_rows > 0) {
    set_flash('error', 'You have already reviewed this purchase.');
    redirect(BASE_URL . '/pages/orders.php');
}
$chk->close();

// Insert review
$ins = $conn->prepare(
    'INSERT INTO reviews (order_item_id, reviewer_id, seller_id, rating, comment) VALUES (?, ?, ?, ?, ?)'
);
$ins->bind_param('iiiis', $order_item_id, $uid, $oi['seller_id'], $rating, $comment);
$ins->execute();
$ins->close();

// Update seller avg_rating and review_count
$upd = $conn->prepare(
    'UPDATE users SET
        review_count = (SELECT COUNT(*) FROM reviews WHERE seller_id = ?),
        avg_rating   = (SELECT AVG(rating) FROM reviews WHERE seller_id = ?)
     WHERE id = ?'
);
$upd->bind_param('iii', $oi['seller_id'], $oi['seller_id'], $oi['seller_id']);
$upd->execute();
$upd->close();

set_flash('success', 'Review submitted. Thank you!');
redirect(BASE_URL . '/pages/orders.php');
