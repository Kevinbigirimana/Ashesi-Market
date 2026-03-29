<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();

$order_id = (int) ($_GET['id'] ?? 0);
$uid      = $_SESSION['user_id'];

if (!$order_id) redirect(BASE_URL);

// Load order
$stmt = $conn->prepare(
    'SELECT o.*, u.name AS buyer_name
     FROM orders o JOIN users u ON u.id = o.buyer_id
     WHERE o.id = ? AND o.buyer_id = ?'
);
$stmt->bind_param('ii', $order_id, $uid);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) redirect(BASE_URL);

// Load items
$stmt = $conn->prepare(
    'SELECT oi.*, p.title, p.location,
            u.name AS seller_name, u.phone_whatsapp,
            pi.image_path
     FROM order_items oi
     JOIN products p ON p.id = oi.product_id
     JOIN users u    ON u.id = oi.seller_id
     LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
     WHERE oi.order_id = ?'
);
$stmt->bind_param('i', $order_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'Order Confirmed';
include __DIR__ . '/../includes/header.php';
?>

<div style="max-width:640px;margin:0 auto;">
  <div class="card" style="padding:32px;text-align:center;margin-bottom:24px;">
    <div style="font-size:3rem;margin-bottom:12px;">✅</div>
    <h1 style="font-family:var(--font-brand);font-size:1.8rem;margin-bottom:8px;">Order Confirmed!</h1>
    <p class="text-muted">Order #<?= $order_id ?> · GH₵ <?= number_format($order['total_amount'], 2) ?></p>
    <p style="margin-top:12px;font-size:.9rem;">
      Contact each seller on WhatsApp below to arrange pickup and payment.
    </p>
  </div>

  <?php foreach ($items as $item): ?>
  <div class="card" style="padding:20px;margin-bottom:16px;">
    <div style="display:flex;gap:14px;align-items:flex-start;">
      <div style="width:64px;height:64px;border-radius:8px;overflow:hidden;background:var(--c-bg);flex-shrink:0;">
        <?php if ($item['image_path']): ?>
          <img src="<?= UPLOAD_URL . e($item['image_path']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
        <?php else: ?>
          <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:1.6rem;">📦</div>
        <?php endif; ?>
      </div>
      <div style="flex:1;">
        <div style="font-weight:600;"><?= e($item['title']) ?></div>
        <div style="font-size:.82rem;color:var(--c-muted);">
          Qty: <?= $item['quantity'] ?> · GH₵ <?= number_format($item['unit_price'] * $item['quantity'], 2) ?>
        </div>
        <?php if ($item['location']): ?>
          <div style="font-size:.82rem;color:var(--c-muted);">📍 <?= e($item['location']) ?></div>
        <?php endif; ?>
        <div style="font-size:.82rem;color:var(--c-muted);margin-top:2px;">
          Seller: <strong><?= e($item['seller_name']) ?></strong>
        </div>
      </div>
    </div>

    <?php if ($item['phone_whatsapp']): ?>
      <?php
        $msg = "Hi {$item['seller_name']}! I just placed an order for {$item['title']} (×{$item['quantity']}) on Ashesi Market (Order #$order_id). When can we arrange pickup?";
        $wa  = whatsapp_url($item['phone_whatsapp'], $msg);
      ?>
      <a href="<?= e($wa) ?>" target="_blank" rel="noopener" class="btn btn-wa" style="margin-top:14px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
        Contact <?= e($item['seller_name']) ?> on WhatsApp
      </a>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <div class="flex gap-2" style="justify-content:center;margin-top:8px;">
    <a href="<?= BASE_URL ?>/pages/orders.php" class="btn btn-outline">View All Orders</a>
    <a href="<?= BASE_URL ?>" class="btn btn-secondary">Continue Shopping</a>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
