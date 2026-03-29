<?php
require_once __DIR__ . '/../includes/functions.php';
require_complete_profile();
refresh_session_user($conn);

$user    = current_user();
$errors  = [];
$edit_id = (int) ($_GET['id'] ?? 0);
$product = null;
$p_images = [];

// Load existing product for edit
if ($edit_id) {
    $stmt = $conn->prepare('SELECT * FROM products WHERE id = ? AND seller_id = ?');
    $stmt->bind_param('ii', $edit_id, $user['id']);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$product) { set_flash('error', 'Product not found.'); redirect(BASE_URL); }

    $img_stmt = $conn->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC');
    $img_stmt->bind_param('i', $edit_id);
    $img_stmt->execute();
    $p_images = $img_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $img_stmt->close();
}

// Load categories
$cats = $conn->query('SELECT * FROM categories ORDER BY name')->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $title      = trim($_POST['title'] ?? '');
    $desc       = trim($_POST['description'] ?? '');
    $price      = (float) ($_POST['price'] ?? 0);
    $qty        = max(0, (int) ($_POST['quantity'] ?? 1));
    $cat_id     = (int) ($_POST['category_id'] ?? 0);
    $condition  = in_array($_POST['condition'] ?? '', ['new','like_new','good','fair']) ? $_POST['condition'] : 'good';
    $location   = trim($_POST['location'] ?? '');
    $available  = isset($_POST['is_available']) ? 1 : 0;

    if (!$title)   $errors[] = 'Title is required.';
    if (!$desc)    $errors[] = 'Description is required.';
    if ($price <= 0) $errors[] = 'Price must be greater than 0.';
    if (!$cat_id)  $errors[] = 'Please select a category.';

    if (empty($errors)) {
        if ($edit_id) {
            $stmt = $conn->prepare(
                'UPDATE products SET title=?, description=?, price=?, quantity=?, category_id=?,
                 `condition`=?, location=?, is_available=? WHERE id=? AND seller_id=?'
            );
            $stmt->bind_param('ssdiissiii', $title, $desc, $price, $qty, $cat_id, $condition, $location, $available, $edit_id, $user['id']);
            $stmt->execute();
            $stmt->close();
            $product_id = $edit_id;
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO products (seller_id, category_id, title, description, price, quantity, `condition`, location)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('iissdiss', $user['id'], $cat_id, $title, $desc, $price, $qty, $condition, $location);
            $stmt->execute();
            $product_id = $conn->insert_id;
            $stmt->close();
        }

        // Handle image uploads
        if (!empty($_FILES['images']['name'][0])) {
            $files = $_FILES['images'];
            $existing_count = $edit_id ? count($p_images) : 0;
            $first = true;

            foreach ($files['name'] as $i => $fname) {
                if (!$fname) continue;
                if ($existing_count >= MAX_IMAGES) break;

                $file = [
                    'name'     => $files['name'][$i],
                    'type'     => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'size'     => $files['size'][$i],
                ];
                $path = handle_image_upload($file, 'products');
                if ($path) {
                    $is_primary = ($first && !$edit_id) ? 1 : 0;
                    // If editing, only set primary if no existing images
                    if ($edit_id && empty($p_images) && $first) $is_primary = 1;

                    $img_stmt = $conn->prepare('INSERT INTO product_images (product_id, image_path, is_primary) VALUES (?, ?, ?)');
                    $img_stmt->bind_param('isi', $product_id, $path, $is_primary);
                    $img_stmt->execute();
                    $img_stmt->close();
                    $existing_count++;
                    $first = false;
                }
            }
        }

        set_flash('success', $edit_id ? 'Product updated!' : 'Product listed successfully!');
        redirect(BASE_URL . '/pages/product.php?id=' . $product_id);
    }
}

$page_title = $edit_id ? 'Edit Product' : 'List a Product';
include __DIR__ . '/../includes/header.php';
?>

<div class="card form-card form-wide">
  <div class="page-header" style="margin-bottom:24px;">
    <h1><?= $edit_id ? 'Edit Product' : 'List a Product' ?></h1>
    <p><?= $edit_id ? 'Update your listing.' : 'Fill in the details to post your product.' ?></p>
  </div>

  <?php if ($errors): ?><div class="alert alert-error"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="form-group">
      <label>Product Title <span style="color:var(--c-warn)">*</span></label>
      <input type="text" name="title" required maxlength="200"
             value="<?= e($_POST['title'] ?? $product['title'] ?? '') ?>">
    </div>

    <div class="form-group">
      <label>Description <span style="color:var(--c-warn)">*</span></label>
      <textarea name="description" required rows="5"><?= e($_POST['description'] ?? $product['description'] ?? '') ?></textarea>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Price (GH₵) <span style="color:var(--c-warn)">*</span></label>
        <input type="number" name="price" required min="0.01" step="0.01"
               value="<?= e($_POST['price'] ?? $product['price'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Quantity Available</label>
        <input type="number" name="quantity" min="0" value="<?= e($_POST['quantity'] ?? $product['quantity'] ?? 1) ?>">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Category <span style="color:var(--c-warn)">*</span></label>
        <select name="category_id" required>
          <option value="">Select category</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= $c['id'] ?>"
              <?= (int)($_POST['category_id'] ?? $product['category_id'] ?? 0) === $c['id'] ? 'selected' : '' ?>>
              <?= e($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Condition</label>
        <select name="condition">
          <?php foreach (['new'=>'New','like_new'=>'Like New','good'=>'Good','fair'=>'Fair'] as $v=>$l): ?>
            <option value="<?= $v ?>"
              <?= ($_POST['condition'] ?? $product['condition'] ?? 'good') === $v ? 'selected' : '' ?>>
              <?= $l ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label>Pickup / Location on Campus</label>
      <input type="text" name="location" placeholder="e.g. Block C, Main Café, Library"
             value="<?= e($_POST['location'] ?? $product['location'] ?? '') ?>">
    </div>

    <?php if ($edit_id && !empty($p_images)): ?>
    <div class="form-group">
      <label>Current Images</label>
      <div class="img-preview-strip">
        <?php foreach ($p_images as $img): ?>
          <div class="img-preview-item">
            <img src="<?= UPLOAD_URL . e($img['image_path']) ?>" alt="">
            <a href="<?= BASE_URL ?>/pages/delete_image.php?id=<?= $img['id'] ?>&product_id=<?= $edit_id ?>"
               class="remove-img"
               data-confirm="Remove this image?"
               onclick="return confirm('Remove this image?')">×</a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="form-group">
      <label>Product Images (up to <?= MAX_IMAGES ?>)</label>
      <input type="file" id="product-images" name="images[]" accept="image/*" multiple>
      <span class="form-hint">JPG/PNG/WebP, max <?= IMG_MAX_MB ?>MB each. First image is the cover.</span>
      <div class="img-preview-strip" id="img-preview-strip"></div>
    </div>

    <?php if ($edit_id): ?>
    <div class="form-group" style="flex-direction:row;align-items:center;gap:10px;">
      <input type="checkbox" id="is_available" name="is_available" value="1"
             <?= ($product['is_available'] ?? 1) ? 'checked' : '' ?> style="width:auto;">
      <label for="is_available" style="margin:0;">Mark as available (uncheck to hide)</label>
    </div>
    <?php endif; ?>

    <div class="flex gap-2">
      <button type="submit" class="btn btn-primary"><?= $edit_id ? 'Update Listing' : 'Post Listing' ?></button>
      <?php if ($edit_id): ?>
        <a href="<?= BASE_URL ?>/pages/product.php?id=<?= $edit_id ?>" class="btn btn-outline">Cancel</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
