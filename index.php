<?php
require_once __DIR__ . '/includes/functions.php';
refresh_session_user($conn);

$page_title = 'Home';

// Fetch latest products with primary image
$stmt = $conn->prepare(
    'SELECT p.*, c.name AS category_name,
            pi.image_path,
            u.name AS seller_name,
            u.avg_rating AS seller_rating
     FROM products p
     JOIN categories c ON c.id = p.category_id
     JOIN users u ON u.id = p.seller_id
     LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
     WHERE p.is_available = 1
     ORDER BY p.created_at DESC
     LIMIT 20'
);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch categories
$cats = $conn->query('SELECT * FROM categories ORDER BY name')->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<div class="hero">
  <h1>Buy & Sell on Campus 🎓</h1>
  <p>Ashesi's student marketplace — discover products from your fellow students, or start selling today.</p>
  <div class="hero-actions">
    <?php if (!is_logged_in()): ?>
      <a href="<?= BASE_URL ?>/pages/register.php" class="btn btn-hero-primary">Start Selling</a>
      <a href="<?= BASE_URL ?>/pages/search.php" class="btn btn-hero-outline">Browse Products</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/pages/product_form.php" class="btn btn-hero-primary">+ List a Product</a>
      <a href="<?= BASE_URL ?>/pages/search.php" class="btn btn-hero-outline">Browse All</a>
    <?php endif; ?>
  </div>
</div>

<!-- Category pills -->
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:28px;">
  <?php foreach ($cats as $cat): ?>
    <a href="<?= BASE_URL ?>/pages/search.php?category=<?= $cat['id'] ?>"
       class="pill pill-category" style="font-size:.85rem;padding:6px 14px;">
      <?= e($cat['name']) ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="section-heading">
  <h2>Latest Listings</h2>
  <a href="<?= BASE_URL ?>/pages/search.php" class="btn btn-sm btn-outline">View all</a>
</div>

<?php if (empty($products)): ?>
  <div class="empty-state"><div class="icon">🛒</div><p>No products yet. Be the first to list something!</p></div>
<?php else: ?>
<div class="product-grid">
  <?php foreach ($products as $p): ?>
  <a href="<?= BASE_URL ?>/pages/product.php?id=<?= $p['id'] ?>" class="card product-card" style="text-decoration:none;color:inherit;">
    <div class="thumb">
      <?php if ($p['image_path']): ?>
        <img src="<?= UPLOAD_URL . e($p['image_path']) ?>" alt="<?= e($p['title']) ?>" loading="lazy">
      <?php else: ?>
        <div class="no-img">📦</div>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <div class="flex gap-2" style="flex-wrap:wrap;">
        <span class="pill pill-category"><?= e($p['category_name']) ?></span>
        <span class="pill pill-condition"><?= e(str_replace('_', ' ', $p['condition'])) ?></span>
      </div>
      <div class="card-title"><?= e($p['title']) ?></div>
      <div class="card-meta"><?= e($p['seller_name']) ?> · <?= e($p['location'] ?? '') ?></div>
      <div class="card-price">GH₵ <?= number_format($p['price'], 2) ?></div>
    </div>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
