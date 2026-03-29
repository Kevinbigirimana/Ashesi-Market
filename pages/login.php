<?php
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) redirect(BASE_URL);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $errors[] = 'Email and password are required.';
    } else {
        $stmt = $conn->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user']    = $user;

            // Ensure cart row exists
            $ck = $conn->prepare('INSERT IGNORE INTO cart (user_id) VALUES (?)');
            $ck->bind_param('i', $user['id']);
            $ck->execute();
            $ck->close();

            // Redirect sellers without complete profile
            $redir = $_GET['redirect'] ?? BASE_URL;
            redirect($redir);
        } else {
            $errors[] = 'Invalid email or password.';
        }
    }
}

$page_title = 'Log In';
include __DIR__ . '/../includes/header.php';
?>

<div class="card form-card">
  <div class="page-header" style="margin-bottom:24px;">
    <h1>Log In</h1>
    <p>Welcome back to <?= APP_NAME ?></p>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-error"><?= implode('<br>', array_map('e', $errors)) ?></div>
  <?php endif; ?>

  <form method="POST">
    <?= csrf_field() ?>

    <div class="form-group">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required autofocus
             value="<?= e($_POST['email'] ?? '') ?>">
    </div>

    <div class="form-group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>
    </div>

    <div style="text-align:right;margin-bottom:16px;">
      <a href="<?= BASE_URL ?>/pages/reset_password.php" style="font-size:.85rem;">Forgot password?</a>
    </div>

    <button type="submit" class="btn btn-primary w-full">Log In</button>

    <p class="text-center mt-3" style="font-size:.88rem;">
      Don't have an account? <a href="<?= BASE_URL ?>/pages/register.php">Register</a>
    </p>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
