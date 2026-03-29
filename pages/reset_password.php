<?php
require_once __DIR__ . '/../includes/functions.php';


$errors  = [];
$success = false;
$step    = 'find'; // 'find' | 'reset'
$uid     = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if ($_POST['step'] === 'find') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $name  = trim($_POST['name'] ?? '');

        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? AND name = ?');
        $stmt->bind_param('ss', $email, $name);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $step = 'reset';
            $uid  = $row['id'];
        } else {
            $errors[] = 'No account found with that name and email.';
        }

    } elseif ($_POST['step'] === 'reset') {
        $uid       = (int) ($_POST['uid'] ?? 0);
        $password  = $_POST['password'] ?? '';
        $confirm   = $_POST['confirm_password'] ?? '';

        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($password !== $confirm) $errors[] = 'Passwords do not match.';

        if (empty($errors) && $uid) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->bind_param('si', $hash, $uid);
            $stmt->execute();
            $stmt->close();
            $success = true;
        } else {
            $step = 'reset';
        }
    }
}

$page_title = 'Reset Password';
include __DIR__ . '/../includes/header.php';
?>

<div class="card form-card">
  <div class="page-header" style="margin-bottom:24px;">
    <h1>Reset Password</h1>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success">Password updated! <a href="<?= BASE_URL ?>/pages/login.php">Log in</a></div>

  <?php elseif ($step === 'find'): ?>
    <?php if ($errors): ?><div class="alert alert-error"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="step" value="find">
      <div class="form-group">
        <label>Full Name (as registered)</label>
        <input type="text" name="name" required value="<?= e($_POST['name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
      </div>
      <button type="submit" class="btn btn-primary w-full">Continue</button>
    </form>

  <?php elseif ($step === 'reset'): ?>
    <?php if ($errors): ?><div class="alert alert-error"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="step" value="reset">
      <input type="hidden" name="uid" value="<?= (int) $uid ?>">
      <div class="form-group">
        <label>New Password</label>
        <input type="password" name="password" required minlength="8">
      </div>
      <div class="form-group">
        <label>Confirm Password</label>
        <input type="password" name="confirm_password" required>
      </div>
      <button type="submit" class="btn btn-primary w-full">Set New Password</button>
    </form>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
