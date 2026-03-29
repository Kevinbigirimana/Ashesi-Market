<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
refresh_session_user($conn);

$uid = $_SESSION['user_id'];

// Fetch cart items with product details
$stmt = $conn->prepare(
    'SELECT ci.quantity AS cart_qty,
            p.id, p.title, p.price, p.quantity AS stock, p.is_available, p.seller_id,
            u.name AS seller_name,
            pi.image_path
     FROM cart c
     JOIN cart_items ci ON ci.cart_id = c.id
     JOIN products p   ON p.id = ci.product_id
     JOIN users u      ON u.id = p.seller_id
     LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
     WHERE c.user_id = ?'
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total = array_sum(array_map(fn($i) => $i['price'] * $i['cart_qty'], $items));

$page_title = 'My Cart';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1>My Cart</h1>
</div>

<?php if (empty($items)): ?>
  <div class="empty-state">
    <div class="icon">🛒</div>
    <p>Your cart is empty.</p>
    <a href="<?= BASE_URL ?>/pages/search.php" class="btn btn-primary mt-3">Browse Products</a>
  </div>
<?php else: ?>

<div class="cart-layout">
  <div class="card" style="padding:20px;">
    <?php foreach ($items as $item): ?>
    <div class="cart-item">
      <div class="cart-item-thumb">
        <?php if ($item['image_path']): ?>
          <img src="<?= UPLOAD_URL . e($item['image_path']) ?>" alt="">
        <?php else: ?>
          <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:1.8rem;background:var(--c-bg)">📦</div>
        <?php endif; ?>
      </div>

      <div>
        <a href="<?= BASE_URL ?>/pages/product.php?id=<?= $item['id'] ?>" class="cart-item-title" style="text-decoration:none;color:var(--c-text);">
          <?= e($item['title']) ?>
        </a>
        <div class="cart-item-price"><?= e($item['seller_name']) ?> · GH₵ <?= number_format($item['price'], 2) ?> each</div>

        <?php if (!$item['is_available']): ?>
          <span class="pill pill-oos" style="margin-top:4px;">Unavailable</span>
        <?php endif; ?>

        <!-- Qty update -->
        <form method="POST" action="<?= BASE_URL ?>/pages/cart_action.php" style="margin-top:8px;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
          <div class="qty-stepper">
            <button type="button" class="qty-minus">−</button>
            <input type="number" name="quantity" value="<?= $item['cart_qty'] ?>"
                   min="0" max="<?= $item['stock'] ?>" onchange="this.form.submit()">
            <button type="button" class="qty-plus">+</button>
          </div>
        </form>
      </div>

      <div style="text-align:right;">
        <div style="font-weight:700;font-size:1rem;color:var(--c-accent);">
          GH₵ <?= number_format($item['price'] * $item['cart_qty'], 2) ?>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/pages/cart_action.php" style="margin-top:8px;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="remove">
          <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
          <button type="submit" style="background:none;border:none;color:var(--c-muted);cursor:pointer;font-size:.8rem;">Remove</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Order summary -->
  <div>
    <div class="card order-summary">
      <h3>Order Summary</h3>
      <?php foreach ($items as $item): ?>
        <div class="summary-row">
          <span><?= e($item['title']) ?> ×<?= $item['cart_qty'] ?></span>
          <span>GH₵ <?= number_format($item['price'] * $item['cart_qty'], 2) ?></span>
        </div>
      <?php endforeach; ?>
      <div class="summary-row summary-total">
        <span>Total</span>
        <span>GH₵ <?= number_format($total, 2) ?></span>
      </div>

      <a href="<?= BASE_URL ?>/pages/checkout.php" class="btn btn-primary w-full" style="margin-top:16px;">
        Proceed to Checkout
      </a>
      <form method="POST" action="<?= BASE_URL ?>/pages/cart_action.php" style="margin-top:8px;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="clear">
        <button type="submit" class="btn btn-outline w-full btn-sm"
                data-confirm="Clear your entire cart?">Clear Cart</button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
