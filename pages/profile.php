<?php
require_once __DIR__ . '/../includes/functions.php';
refresh_session_user($conn);

$profile_id = (int) ($_GET['id'] ?? 0);
if (!$profile_id) redirect(BASE_URL);

// Load user
$stmt = $conn->prepare('SELECT * FROM users WHERE id = ?');
$stmt->bind_param('i', $profile_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$profile) { set_flash('error', 'User not found.'); redirect(BASE_URL); }

$is_own = is_logged_in() && $_SESSION['user_id'] === $profile_id;

// Load their active products
$stmt = $conn->prepare(
    'SELECT p.*, c.name AS category_name, pi.image_path
     FROM products p
     JOIN categories c ON c.id = p.category_id
     LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
     WHERE p.seller_id = ?
     ORDER BY p.created_at DESC'
);
$stmt->bind_param('i', $profile_id);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Load reviews
$stmt = $conn->prepare(
    'SELECT r.*, u.name AS reviewer_name
     FROM reviews r
     JOIN users u ON u.id = r.reviewer_id
     WHERE r.seller_id = ?
     ORDER BY r.created_at DESC'
);
$stmt->bind_param('i', $profile_id);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = $profile['name'] . '\'s Profile';
include __DIR__ . '/../includes/header.php';
?>

<!-- Profile header -->
<div class="card profile-header">
  <div class="profile-avatar"><?= strtoupper(substr($profile['name'], 0, 1)) ?></div>
  <div style="flex:1;">
    <div class="profile-name"><?= e($profile['name']) ?></div>
    <div class="profile-meta">
      <?= e($profile['year_group'] ?? '') ?>
      <?php if ($profile['avg_rating'] > 0): ?>
        · <?= stars($profile['avg_rating']) ?>
        <?= number_format($profile['avg_rating'], 1) ?> (<?= $profile['review_count'] ?> reviews)
      <?php endif; ?>
      <?php if ($profile['is_verified']): ?>
        · <span style="color:var(--c-success);font-size:.82rem;">✓ Verified</span>
      <?php endif; ?>
    </div>
    <?php if ($profile['bio']): ?>
      <p style="margin-top:8px;font-size:.9rem;"><?= e($profile['bio']) ?></p>
    <?php endif; ?>
  </div>

  <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
    <?php if ($is_own): ?>
      <a href="<?= BASE_URL ?>/pages/setup_profile.php" class="btn btn-outline btn-sm">Edit Profile</a>
    <?php endif; ?>
    <?php if (!$is_own && $profile['phone_whatsapp'] && is_logged_in()): ?>
      <?php $wa = whatsapp_url($profile['phone_whatsapp'], "Hi {$profile['name']}! I found you on Ashesi Market."); ?>
      <a href="<?= e($wa) ?>" target="_blank" class="btn btn-wa btn-sm">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
        WhatsApp
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- Tabs -->
<div class="tabs">
  <button class="tab-btn" data-tab="tab-listings">Listings (<?= count($products) ?>)</button>
  <button class="tab-btn" data-tab="tab-seller-reviews">Reviews (<?= count($reviews) ?>)</button>
</div>

<!-- Listings -->
<div class="tab-panel" id="tab-listings">
  <?php if (empty($products)): ?>
    <div class="empty-state"><div class="icon">📦</div><p>No listings yet.</p></div>
  <?php else: ?>
    <div class="product-grid">
      <?php foreach ($products as $p): ?>
      <div class="card product-card">
        <a href="<?= BASE_URL ?>/pages/product.php?id=<?= $p['id'] ?>" style="text-decoration:none;color:inherit;">
          <div class="thumb">
            <?php if ($p['image_path']): ?>
              <img src="<?= UPLOAD_URL . e($p['image_path']) ?>" alt="<?= e($p['title']) ?>" loading="lazy">
            <?php else: ?>
              <div class="no-img">📦</div>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <div class="flex gap-2"><span class="pill pill-category"><?= e($p['category_name']) ?></span></div>
            <div class="card-title"><?= e($p['title']) ?></div>
            <?php if (!$p['is_available']): ?><span class="pill pill-oos">Out of Stock</span><?php endif; ?>
            <div class="card-price">GH₵ <?= number_format($p['price'], 2) ?></div>
          </div>
        </a>
        <?php if ($is_own): ?>
        <div class="card-footer flex gap-2">
          <a href="<?= BASE_URL ?>/pages/product_form.php?id=<?= $p['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
          <a href="<?= BASE_URL ?>/pages/delete_product.php?id=<?= $p['id'] ?>"
             class="btn btn-danger btn-sm"
             data-confirm="Delete this product? This cannot be undone."
             onclick="return confirm('Delete this product?')">Delete</a>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Reviews -->
<div class="tab-panel" id="tab-seller-reviews">
  <?php if (empty($reviews)): ?>
    <div class="empty-state"><div class="icon">⭐</div><p>No reviews yet.</p></div>
  <?php else: ?>
    <?php foreach ($reviews as $r): ?>
    <div class="card" style="padding:16px;margin-bottom:12px;">
      <div class="flex justify-between items-center mb-1">
        <strong style="font-size:.9rem;"><?= e($r['reviewer_name']) ?></strong>
        <span style="font-size:.78rem;color:var(--c-muted);"><?= date('M j, Y', strtotime($r['created_at'])) ?></span>
      </div>
      <div class="rating-row"><?= stars($r['rating']) ?> <span style="font-size:.82rem;"><?= $r['rating'] ?>/5</span></div>
      <?php if ($r['comment']): ?>
        <p style="margin-top:8px;font-size:.88rem;"><?= e($r['comment']) ?></p>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
