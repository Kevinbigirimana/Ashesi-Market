<?php
require_once __DIR__ . '/../includes/functions.php';
refresh_session_user($conn);

$q        = trim($_GET['q'] ?? '');
$cat_id   = (int) ($_GET['category'] ?? 0);
$sort     = in_array($_GET['sort'] ?? '', ['newest','price_asc','price_desc','rating']) ? $_GET['sort'] : 'newest';
$min_p    = (float) ($_GET['min_price'] ?? 0);
$max_p    = (float) ($_GET['max_price'] ?? 0);
$cond     = in_array($_GET['condition'] ?? '', ['new','like_new','good','fair']) ? $_GET['condition'] : '';

// Build query dynamically
$where   = ['p.is_available = 1'];
$params  = [];
$types   = '';

if ($q) {
    $like = '%' . $q . '%';
    $where[] = '(p.title LIKE ? OR p.description LIKE ? OR u.name LIKE ?)';
    $params  = array_merge($params, [$like, $like, $like]);
    $types  .= 'sss';
}
if ($cat_id) {
    $where[] = 'p.category_id = ?';
    $params[] = $cat_id; $types .= 'i';
}
if ($min_p > 0) {
    $where[] = 'p.price >= ?';
    $params[] = $min_p; $types .= 'd';
}
if ($max_p > 0) {
    $where[] = 'p.price <= ?';
    $params[] = $max_p; $types .= 'd';
}
if ($cond) {
    $where[] = 'p.`condition` = ?';
    $params[] = $cond; $types .= 's';
}

$order_map = [
    'newest'     => 'p.created_at DESC',
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'rating'     => 'u.avg_rating DESC',
];
$order_sql = $order_map[$sort];
$where_sql = implode(' AND ', $where);

$sql = "SELECT p.*, c.name AS category_name,
               pi.image_path,
               u.name AS seller_name, u.avg_rating AS seller_rating
        FROM products p
        JOIN categories c ON c.id = p.category_id
        JOIN users u ON u.id = p.seller_id
        LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
        WHERE $where_sql
        ORDER BY $order_sql
        LIMIT 60";

$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$cats = $conn->query('SELECT * FROM categories ORDER BY name')->fetch_all(MYSQLI_ASSOC);

$page_title = $q ? 'Search: ' . $q : 'Browse Products';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1><?= $q ? 'Results for "' . e($q) . '"' : 'Browse Products' ?></h1>
  <p><?= count($products) ?> product<?= count($products) !== 1 ? 's' : '' ?> found</p>
</div>

<div class="search-layout">

  <!-- Filters sidebar -->
  <aside>
    <div class="card filters-card">
      <h3>Filters</h3>
      <form method="GET">
        <?php if ($q): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>

        <div class="filter-group">
          <label>Category</label>
          <select name="category" onchange="this.form.submit()">
            <option value="">All categories</option>
            <?php foreach ($cats as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $cat_id === $c['id'] ? 'selected' : '' ?>>
                <?= e($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="filter-group">
          <label>Condition</label>
          <select name="condition" onchange="this.form.submit()">
            <option value="">Any condition</option>
            <?php foreach (['new'=>'New','like_new'=>'Like New','good'=>'Good','fair'=>'Fair'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= $cond === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="filter-group">
          <label>Price Range (GH₵)</label>
          <div class="price-range">
            <input type="number" name="min_price" placeholder="Min" value="<?= $min_p ?: '' ?>" min="0">
            <span>–</span>
            <input type="number" name="max_price" placeholder="Max" value="<?= $max_p ?: '' ?>" min="0">
          </div>
        </div>

        <div class="filter-group">
          <label>Sort By</label>
          <select name="sort" onchange="this.form.submit()">
            <option value="newest"     <?= $sort==='newest'     ? 'selected':'' ?>>Newest first</option>
            <option value="price_asc"  <?= $sort==='price_asc'  ? 'selected':'' ?>>Price: Low to High</option>
            <option value="price_desc" <?= $sort==='price_desc' ? 'selected':'' ?>>Price: High to Low</option>
            <option value="rating"     <?= $sort==='rating'     ? 'selected':'' ?>>Seller Rating</option>
          </select>
        </div>

        <button type="submit" class="btn btn-secondary w-full btn-sm">Apply</button>
        <a href="<?= BASE_URL ?>/pages/search.php" class="btn btn-outline w-full btn-sm mt-2">Clear filters</a>
      </form>
    </div>
  </aside>

  <!-- Results -->
  <div>
    <?php if (empty($products)): ?>
      <div class="empty-state">
        <div class="icon">🔍</div>
        <p>No products match your search. Try different filters.</p>
      </div>
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
            <span class="pill pill-condition"><?= e(str_replace('_',' ',$p['condition'])) ?></span>
          </div>
          <div class="card-title"><?= e($p['title']) ?></div>
          <div class="card-meta"><?= e($p['seller_name']) ?></div>
          <div class="card-price">GH₵ <?= number_format($p['price'], 2) ?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
