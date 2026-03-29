<?php
require_once __DIR__ . '/../includes/functions.php';
refresh_session_user($conn);

$id = (int) ($_GET['id'] ?? 0);
if (!$id) redirect(BASE_URL);

// Load product
$stmt = $conn->prepare(
    'SELECT p.*, c.name AS category_name,
            u.id AS seller_id, u.name AS seller_name,
            u.phone_whatsapp, u.avg_rating AS seller_rating,
            u.review_count AS seller_reviews,
            u.year_group AS seller_year, u.bio AS seller_bio
     FROM products p
     JOIN categories c ON c.id = p.category_id
     JOIN users u ON u.id = p.seller_id
     WHERE p.id = ?'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$p) { set_flash('error', 'Product not found.'); redirect(BASE_URL); }

// Load images
$imgs_stmt = $conn->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC');
$imgs_stmt->bind_param('i', $id);
$imgs_stmt->execute();
$images = $imgs_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$imgs_stmt->close();

// Load recent reviews for this seller
$rev_stmt = $conn->prepare(
    'SELECT r.*, u.name AS reviewer_name
     FROM reviews r
     JOIN users u ON u.id = r.reviewer_id
     WHERE r.seller_id = ?
     ORDER BY r.created_at DESC LIMIT 5'
);
$rev_stmt->bind_param('i', $p['seller_id']);
$rev_stmt->execute();
$reviews = $rev_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$rev_stmt->close();

// WhatsApp prefilled message
$wa_msg = "Hi {$p['seller_name']}! I'm interested in your listing: {$p['title']} (GH₵" . number_format($p['price'],2) . ") on Ashesi Market.";
$wa_url = $p['phone_whatsapp'] ? whatsapp_url($p['phone_whatsapp'], $wa_msg) : null;

$page_title = $p['title'];
include __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom:10px;">
  <a href="javascript:history.back()" style="font-size:.85rem;color:var(--c-muted);">← Back</a>
</div>

<div class="product-detail">

  <!-- Gallery -->
  <div class="gallery">
    <div class="gallery-main">
      <?php $main_img = $images[0]['image_path'] ?? null; ?>
      <?php if ($main_img): ?>
        <img id="main-product-img" src="<?= UPLOAD_URL . e($main_img) ?>" alt="<?= e($p['title']) ?>">
      <?php else: ?>
        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:4rem;background:var(--c-bg)">📦</div>
      <?php endif; ?>
    </div>
    <?php if (count($images) > 1): ?>
    <div class="gallery-thumbs">
      <?php foreach ($images as $i => $img): ?>
        <div class="gallery-thumb <?= $i === 0 ? 'active' : '' ?>"
             data-src="<?= UPLOAD_URL . e($img['image_path']) ?>">
          <img src="<?= UPLOAD_URL . e($img['image_path']) ?>" alt="">
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Info -->
  <div>
    <div class="card product-info-card">
      <div class="flex gap-2" style="flex-wrap:wrap;">
        <span class="pill pill-category"><?= e($p['category_name']) ?></span>
        <span class="pill pill-condition"><?= e(str_replace('_',' ',$p['condition'])) ?></span>
        <?php if (!$p['is_available']): ?>
          <span class="pill pill-oos">Out of Stock</span>
        <?php endif; ?>
      </div>

      <h1><?= e($p['title']) ?></h1>
      <div class="product-price">GH₵ <?= number_format($p['price'], 2) ?></div>

      <?php if ($p['location']): ?>
        <div class="text-muted" style="font-size:.88rem;">📍 <?= e($p['location']) ?></div>
      <?php endif; ?>

      <div style="font-size:.88rem;color:var(--c-muted);">
        <?= $p['quantity'] ?> left in stock
      </div>

      <!-- Add to cart -->
      <?php if (is_logged_in() && $p['is_available'] && current_user()['id'] !== $p['seller_id']): ?>
        <form method="POST" action="<?= BASE_URL ?>/pages/cart_action.php">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
          <div class="flex gap-2 items-center">
            <div class="qty-stepper">
              <button type="button" class="qty-minus">−</button>
              <input type="number" name="quantity" value="1" min="1" max="<?= $p['quantity'] ?>">
              <button type="button" class="qty-plus">+</button>
            </div>
            <button type="submit" class="btn btn-primary">Add to Cart</button>
          </div>
        </form>
      <?php elseif (!is_logged_in()): ?>
        <a href="<?= BASE_URL ?>/pages/login.php" class="btn btn-primary">Log in to Buy</a>
      <?php elseif (!$p['is_available']): ?>
        <div class="alert alert-info">This product is currently unavailable.</div>
      <?php endif; ?>

      <!-- WhatsApp contact -->
      <?php if ($wa_url && is_logged_in()): ?>
        <a href="<?= e($wa_url) ?>" target="_blank" rel="noopener" class="btn btn-wa">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
          Chat on WhatsApp
        </a>
      <?php endif; ?>
    </div>

    <!-- Seller mini card -->
    <a href="<?= BASE_URL ?>/pages/profile.php?id=<?= $p['seller_id'] ?>" class="seller-mini card mt-3" style="display:flex;margin-top:16px;">
      <div class="seller-avatar"><?= strtoupper(substr($p['seller_name'], 0, 1)) ?></div>
      <div class="seller-mini-info">
        <div class="seller-mini-name"><?= e($p['seller_name']) ?></div>
        <div class="seller-mini-sub">
          <?= stars($p['seller_rating']) ?>
          <?= number_format($p['seller_rating'], 1) ?> (<?= $p['seller_reviews'] ?> reviews)
          · <?= e($p['seller_year'] ?? '') ?>
        </div>
      </div>
      <span style="color:var(--c-muted);font-size:.85rem;align-self:center;">View →</span>
    </a>
  </div>
</div>

<!-- Description -->
<div class="card" style="padding:24px;margin-top:28px;">
  <h2 style="font-size:1.1rem;font-weight:600;margin-bottom:12px;">Description</h2>
  <div style="white-space:pre-wrap;line-height:1.7;"><?= e($p['description']) ?></div>
</div>

<!-- Seller reviews -->
<div style="margin-top:28px;">
  <h2 style="font-family:var(--font-brand);font-size:1.2rem;margin-bottom:16px;">
    Seller Reviews (<?= $p['seller_reviews'] ?>)
  </h2>
  <?php if (empty($reviews)): ?>
    <p class="text-muted">No reviews yet.</p>
  <?php else: ?>
    <?php foreach ($reviews as $r): ?>
    <div class="card" style="padding:16px;margin-bottom:12px;">
      <div class="flex justify-between items-center mb-2">
        <strong style="font-size:.9rem;"><?= e($r['reviewer_name']) ?></strong>
        <span style="font-size:.78rem;color:var(--c-muted);"><?= date('M j, Y', strtotime($r['created_at'])) ?></span>
      </div>
      <div class="rating-row"><?= stars($r['rating']) ?></div>
      <?php if ($r['comment']): ?>
        <p style="margin-top:8px;font-size:.88rem;"><?= e($r['comment']) ?></p>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
// Gallery thumbnail click
document.querySelectorAll('.gallery-thumb').forEach(t => {
  t.addEventListener('click', function() {
    document.getElementById('main-product-img').src = this.dataset.src;
    document.querySelectorAll('.gallery-thumb').forEach(x => x.classList.remove('active'));
    this.classList.add('active');
  });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
