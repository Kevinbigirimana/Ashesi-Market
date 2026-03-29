<?php

$page_title = $page_title ?? APP_NAME;
$user       = current_user();
$cart_cnt   = cart_count($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($page_title) ?> — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Syne:wght@700&display=swap" rel="stylesheet">
</head>
<body>

<nav class="navbar">
  <div class="nav-inner">
    <a class="brand" href="<?= BASE_URL ?>">
      <span class="brand-icon">🛍</span>
      <span class="brand-text"><?= APP_NAME ?></span>
    </a>

    <form class="nav-search" method="GET" action="<?= BASE_URL ?>/pages/search.php">
      <input type="text" name="q" placeholder="Search products, sellers…"
             value="<?= e($_GET['q'] ?? '') ?>">
      <button type="submit">Search</button>
    </form>

    <div class="nav-actions">
      <?php if ($user): ?>
        <a class="nav-link" href="<?= BASE_URL ?>/pages/cart.php">
          Cart <?php if ($cart_cnt > 0): ?><span class="badge"><?= $cart_cnt ?></span><?php endif; ?>
        </a>
        <a class="nav-link" href="<?= BASE_URL ?>/pages/orders.php">Orders</a>
        <a class="nav-link" href="<?= BASE_URL ?>/pages/profile.php?id=<?= $user['id'] ?>">Profile</a>
        <?php if (in_array($user['role'], ['seller', 'both'])): ?>
          <a class="btn btn-sm" href="<?= BASE_URL ?>/pages/product_form.php">+ Sell</a>
        <?php endif; ?>
        <a class="nav-link" href="<?= BASE_URL ?>/pages/logout.php">Log out</a>
      <?php else: ?>
        <a class="nav-link" href="<?= BASE_URL ?>/pages/login.php">Log in</a>
        <a class="btn btn-sm" href="<?= BASE_URL ?>/pages/register.php">Register</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<main class="site-main">
  <div class="container">
    <?= render_flash() ?>
