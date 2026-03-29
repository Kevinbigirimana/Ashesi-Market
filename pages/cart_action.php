<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
verify_csrf();

$action     = $_POST['action'] ?? '';
$product_id = (int) ($_POST['product_id'] ?? 0);
$quantity   = max(1, (int) ($_POST['quantity'] ?? 1));
$uid        = $_SESSION['user_id'];

// Get or create cart for user
$stmt = $conn->prepare('SELECT id FROM cart WHERE user_id = ?');
$stmt->bind_param('i', $uid);
$stmt->execute();
$cart = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cart) {
    $c = $conn->prepare('INSERT INTO cart (user_id) VALUES (?)');
    $c->bind_param('i', $uid);
    $c->execute();
    $cart_id = $conn->insert_id;
    $c->close();
} else {
    $cart_id = $cart['id'];
}

if ($action === 'add' && $product_id) {
    // Validate product exists, is available, and not seller's own
    $p = $conn->prepare('SELECT id, seller_id, quantity, is_available FROM products WHERE id = ?');
    $p->bind_param('i', $product_id);
    $p->execute();
    $product = $p->get_result()->fetch_assoc();
    $p->close();

    if (!$product || !$product['is_available']) {
        set_flash('error', 'This product is not available.');
        redirect(BASE_URL . '/pages/product.php?id=' . $product_id);
    }
    if ($product['seller_id'] === $uid) {
        set_flash('error', 'You cannot add your own product to cart.');
        redirect(BASE_URL . '/pages/product.php?id=' . $product_id);
    }

    // Clamp quantity to stock
    $quantity = min($quantity, $product['quantity']);

    // Insert or update cart item
    $stmt = $conn->prepare(
        'INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE quantity = quantity + ?'
    );
    $stmt->bind_param('iiii', $cart_id, $product_id, $quantity, $quantity);
    $stmt->execute();
    $stmt->close();

    set_flash('success', 'Added to cart!');
    redirect(BASE_URL . '/pages/cart.php');

} elseif ($action === 'update' && $product_id) {
    if ($quantity <= 0) {
        // Remove item
        $del = $conn->prepare('DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?');
        $del->bind_param('ii', $cart_id, $product_id);
        $del->execute();
        $del->close();
    } else {
        $upd = $conn->prepare('UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND product_id = ?');
        $upd->bind_param('iii', $quantity, $cart_id, $product_id);
        $upd->execute();
        $upd->close();
    }
    redirect(BASE_URL . '/pages/cart.php');

} elseif ($action === 'remove' && $product_id) {
    $del = $conn->prepare('DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?');
    $del->bind_param('ii', $cart_id, $product_id);
    $del->execute();
    $del->close();
    redirect(BASE_URL . '/pages/cart.php');

} elseif ($action === 'clear') {
    $clr = $conn->prepare('DELETE FROM cart_items WHERE cart_id = ?');
    $clr->bind_param('i', $cart_id);
    $clr->execute();
    $clr->close();
    redirect(BASE_URL . '/pages/cart.php');
}

redirect(BASE_URL . '/pages/cart.php');
