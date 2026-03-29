<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
refresh_session_user($conn);

$uid  = $_SESSION['user_id'];
$user = current_user();

// --- Buyer orders ---
$stmt = $conn->prepare(
    'SELECT o.id, o.total_amount, o.status, o.created_at,
            COUNT(oi.id) AS item_count
     FROM orders o
     JOIN order_items oi ON oi.order_id = o.id
     WHERE o.buyer_id = ?
     GROUP BY o.id
     ORDER BY o.created_at DESC'
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$buy_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Sales (items where I'm the seller) ---
$stmt = $conn->prepare(
    'SELECT oi.id AS item_id, oi.quantity, oi.unit_price,
            p.title, p.id AS product_id,
            o.id AS order_id, o.status, o.created_at,
            u.name AS buyer_name, u.phone_whatsapp AS buyer_phone,
            r.id AS review_id, r.rating
     FROM order_items oi
     JOIN products p ON p.id = oi.product_id
     JOIN orders o   ON o.id = oi.order_id
     JOIN users u    ON u.id = o.buyer_id
     LEFT JOIN reviews r ON r.order_item_id = oi.id
     WHERE oi.seller_id = ?
     ORDER BY o.created_at DESC'
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Pending reviews: buyer's completed order items without a review ---
$stmt = $conn->prepare(
    'SELECT oi.id AS item_id, p.title, oi.seller_id, u.name AS seller_name
     FROM order_items oi
     JOIN orders o   ON o.id = oi.order_id
     JOIN products p ON p.id = oi.product_id
     JOIN users u    ON u.id = oi.seller_id
     LEFT JOIN reviews r ON r.order_item_id = oi.id
     WHERE o.buyer_id = ? AND o.status = "completed" AND r.id IS NULL
       AND oi.seller_id != ?'
);
$stmt->bind_param('ii', $uid, $uid);
$stmt->execute();
$pending_reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Handle status update (sellers mark orders as confirmed/completed) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    verify_csrf();
    $oid    = (int) $_POST['order_id'];
    $status = in_array($_POST['status'] ?? '', ['confirmed','completed','cancelled']) ? $_POST['status'] : null;

    if ($oid && $status) {
        // Verify the logged-in user is a seller in this order
        $chk = $conn->prepare(
            'SELECT o.id FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             WHERE o.id = ? AND oi.seller_id = ? LIMIT 1'
        );
        $chk->bind_param('ii', $oid, $uid);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $upd = $conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
            $upd->bind_param('si', $status, $oid);
            $upd->execute();
            $upd->close();
            set_flash('success', 'Order status updated.');
        }
        $chk->close();
    }
    redirect(BASE_URL . '/pages/orders.php');
}

$page_title = 'My Orders';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1>Orders</h1>
</div>

<div class="tabs">
  <button class="tab-btn" data-tab="tab-purchases">My Purchases (<?= count($buy_orders) ?>)</button>
  <?php if (in_array($user['role'], ['seller','both'])): ?>
  <button class="tab-btn" data-tab="tab-sales">My Sales (<?= count($sales) ?>)</button>
  <?php endif; ?>
  <?php if ($pending_reviews): ?>
  <button class="tab-btn" data-tab="tab-reviews">
    Pending Reviews <span class="badge"><?= count($pending_reviews) ?></span>
  </button>
  <?php endif; ?>
</div>

<!-- Purchases tab -->
<div class="tab-panel" id="tab-purchases">
  <?php if (empty($buy_orders)): ?>
    <div class="empty-state"><div class="icon">🛍</div><p>No purchases yet.</p></div>
  <?php else: ?>
    <?php foreach ($buy_orders as $o): ?>
    <div class="card order-card">
      <div class="order-meta">
        <div>
          <strong>Order #<?= $o['id'] ?></strong>
          <span class="text-muted" style="margin-left:10px;font-size:.82rem;"><?= date('M j, Y', strtotime($o['created_at'])) ?></span>
        </div>
        <span class="status-pill status-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span>
      </div>
      <div class="flex justify-between" style="font-size:.9rem;">
        <span><?= $o['item_count'] ?> item<?= $o['item_count'] > 1 ? 's' : '' ?></span>
        <strong>GH₵ <?= number_format($o['total_amount'], 2) ?></strong>
      </div>
      <a href="<?= BASE_URL ?>/pages/order_confirm.php?id=<?= $o['id'] ?>"
         class="btn btn-outline btn-sm" style="margin-top:12px;">View Details</a>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Sales tab -->
<?php if (in_array($user['role'], ['seller','both'])): ?>
<div class="tab-panel" id="tab-sales">
  <?php if (empty($sales)): ?>
    <div class="empty-state"><div class="icon">📦</div><p>No sales yet.</p></div>
  <?php else: ?>
    <?php
    // Group by order
    $by_order = [];
    foreach ($sales as $s) $by_order[$s['order_id']][] = $s;
    foreach ($by_order as $oid => $sitems):
      $first = $sitems[0];
    ?>
    <div class="card order-card">
      <div class="order-meta">
        <div>
          <strong>Order #<?= $oid ?></strong>
          <span class="text-muted" style="margin-left:10px;font-size:.82rem;"><?= date('M j, Y', strtotime($first['created_at'])) ?></span>
          <span class="text-muted" style="margin-left:10px;font-size:.82rem;">Buyer: <?= e($first['buyer_name']) ?></span>
        </div>
        <span class="status-pill status-<?= $first['status'] ?>"><?= ucfirst($first['status']) ?></span>
      </div>

      <?php foreach ($sitems as $si): ?>
      <div style="font-size:.88rem;padding:6px 0;border-bottom:1px solid var(--c-border);">
        <strong><?= e($si['title']) ?></strong> · ×<?= $si['quantity'] ?> · GH₵ <?= number_format($si['unit_price'] * $si['quantity'], 2) ?>
      </div>
      <?php endforeach; ?>

      <div class="flex gap-2" style="margin-top:12px;flex-wrap:wrap;align-items:center;">
        <?php if ($first['buyer_phone']): ?>
          <?php $wa = whatsapp_url($first['buyer_phone'], "Hi {$first['buyer_name']}! This is regarding Order #$oid on Ashesi Market."); ?>
          <a href="<?= e($wa) ?>" target="_blank" class="btn btn-wa btn-sm">
            WhatsApp Buyer
          </a>
        <?php endif; ?>

        <?php if (in_array($first['status'], ['pending','confirmed'])): ?>
        <form method="POST" style="display:inline-flex;gap:6px;align-items:center;">
          <?= csrf_field() ?>
          <input type="hidden" name="update_status" value="1">
          <input type="hidden" name="order_id" value="<?= $oid ?>">
          <select name="status" class="form-control" style="padding:5px 8px;font-size:.82rem;border:1.5px solid var(--c-border);border-radius:7px;">
            <option value="confirmed" <?= $first['status']==='confirmed'?'selected':'' ?>>Mark Confirmed</option>
            <option value="completed" <?= $first['status']==='completed'?'selected':'' ?>>Mark Completed</option>
            <option value="cancelled">Mark Cancelled</option>
          </select>
          <button type="submit" class="btn btn-sm btn-outline">Update</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Pending reviews tab -->
<?php if ($pending_reviews): ?>
<div class="tab-panel" id="tab-reviews">
  <p class="text-muted" style="margin-bottom:18px;">Rate sellers for completed purchases.</p>
  <?php foreach ($pending_reviews as $pr): ?>
  <div class="card" style="padding:20px;margin-bottom:14px;">
    <strong><?= e($pr['title']) ?></strong>
    <div style="font-size:.85rem;color:var(--c-muted);margin-bottom:12px;">Sold by <?= e($pr['seller_name']) ?></div>

    <form method="POST" action="<?= BASE_URL ?>/pages/submit_review.php">
      <?= csrf_field() ?>
      <input type="hidden" name="order_item_id" value="<?= $pr['item_id'] ?>">
      <input type="hidden" name="seller_id" value="<?= $pr['seller_id'] ?>">

      <div class="form-group" style="margin-bottom:10px;">
        <label>Rating</label>
        <div class="star-input" id="stars-<?= $pr['item_id'] ?>">
          <?php for ($i = 5; $i >= 1; $i--): ?>
            <input type="radio" name="rating" id="s<?= $pr['item_id'] ?>-<?= $i ?>" value="<?= $i ?>" required>
            <label for="s<?= $pr['item_id'] ?>-<?= $i ?>">&#9733;</label>
          <?php endfor; ?>
        </div>
      </div>

      <div class="form-group" style="margin-bottom:10px;">
        <label>Comment (optional)</label>
        <textarea name="comment" rows="2" placeholder="Share your experience with this seller…"></textarea>
      </div>

      <button type="submit" class="btn btn-primary btn-sm">Submit Review</button>
    </form>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
