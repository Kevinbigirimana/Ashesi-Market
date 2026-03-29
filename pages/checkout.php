<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
refresh_session_user($conn);

$uid = $_SESSION['user_id'];

// Load cart
$stmt = $conn->prepare(
    'SELECT ci.quantity AS cart_qty,
            p.id AS product_id, p.title, p.price, p.quantity AS stock,
            p.is_available, p.seller_id,
            u.name AS seller_name, u.phone_whatsapp
     FROM cart c
     JOIN cart_items ci ON ci.cart_id = c.id
     JOIN products p    ON p.id = ci.product_id
     JOIN users u       ON u.id = p.seller_id
     WHERE c.user_id = ?'
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($items)) {
    set_flash('info', 'Your cart is empty.');
    redirect(BASE_URL . '/pages/cart.php');
}

// Validate all items still available
$invalid = array_filter($items, fn($i) => !$i['is_available'] || $i['stock'] < $i['cart_qty']);
if ($invalid) {
    set_flash('error', 'Some items in your cart are no longer available. Please review your cart.');
    redirect(BASE_URL . '/pages/cart.php');
}

$total = array_sum(array_map(fn($i) => $i['price'] * $i['cart_qty'], $items));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Create order in a transaction
    $conn->begin_transaction();
    try {
        // Insert order
        $ord = $conn->prepare('INSERT INTO orders (buyer_id, total_amount) VALUES (?, ?)');
        $ord->bind_param('id', $uid, $total);
        $ord->execute();
        $order_id = $conn->insert_id;
        $ord->close();

        foreach ($items as $item) {
            // Insert order item
            $oi = $conn->prepare(
                'INSERT INTO order_items (order_id, product_id, seller_id, quantity, unit_price)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $oi->bind_param('iiiid', $order_id, $item['product_id'], $item['seller_id'], $item['cart_qty'], $item['price']);
            $oi->execute();
            $oi->close();

            // Decrement stock
            $upd = $conn->prepare(
                'UPDATE products SET quantity = quantity - ?
                 WHERE id = ? AND quantity >= ?'
            );
            $upd->bind_param('iii', $item['cart_qty'], $item['product_id'], $item['cart_qty']);
            $upd->execute();
            $upd->close();

            // Auto mark out of stock if quantity hits 0
            $conn->query(
                'UPDATE products SET is_available = 0
                 WHERE id = ' . (int)$item['product_id'] . ' AND quantity = 0'
            );
        }

        // Clear cart
        $clr = $conn->prepare('DELETE FROM cart_items WHERE cart_id = (SELECT id FROM cart WHERE user_id = ?)');
        $clr->bind_param('i', $uid);
        $clr->execute();
        $clr->close();

        $conn->commit();
        redirect(BASE_URL . '/pages/order_confirm.php?id=' . $order_id);

    } catch (Exception $e) {
        $conn->rollback();
        set_flash('error', 'Order failed. Please try again.');
        redirect(BASE_URL . '/pages/checkout.php');
    }
}

$page_title = 'Checkout';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1>Checkout</h1>
  <p>Review your order before confirming.</p>
</div>

<div class="cart-layout">
  <div class="card" style="padding:24px;">
    <h2 style="font-size:1.1rem;font-weight:600;margin-bottom:16px;">Order Items</h2>

    <?php foreach ($items as $item): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--c-border);">
      <div>
        <div style="font-weight:600;"><?= e($item['title']) ?></div>
        <div style="font-size:.82rem;color:var(--c-muted);">
          Seller: <?= e($item['seller_name']) ?> · Qty: <?= $item['cart_qty'] ?>
        </div>
      </div>
      <div style="font-weight:700;color:var(--c-accent);">
        GH₵ <?= number_format($item['price'] * $item['cart_qty'], 2) ?>
      </div>
    </div>
    <?php endforeach; ?>

    <div style="margin-top:20px;padding:14px;background:var(--c-bg);border-radius:var(--radius);">
      <p style="font-size:.88rem;color:var(--c-muted);line-height:1.6;">
        <strong>How it works:</strong> After confirming, use the WhatsApp button on each product page
        to coordinate pickup directly with each seller. Payment is done in person at pickup.
      </p>
    </div>
  </div>

  <div>
    <div class="card order-summary">
      <h3>Order Total</h3>
      <div class="summary-row summary-total">
        <span>Total</span>
        <span>GH₵ <?= number_format($total, 2) ?></span>
      </div>

      <form method="POST">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary w-full" style="margin-top:16px;">
          Confirm Order
        </button>
      </form>
      <a href="<?= BASE_URL ?>/pages/cart.php" class="btn btn-outline w-full btn-sm" style="margin-top:8px;">
        Back to Cart
      </a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
