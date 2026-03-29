<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
refresh_session_user($conn);

$user   = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $phone = trim($_POST['phone'] ?? '');
    $year  = trim($_POST['year_group'] ?? '');
    $bio   = trim($_POST['bio'] ?? '');

    if (!$phone) $errors[] = 'WhatsApp number is required to sell.';

    if (empty($errors)) {
        $complete = 1;
        $stmt = $conn->prepare(
            'UPDATE users SET phone_whatsapp = ?, year_group = ?, bio = ?, profile_complete = ? WHERE id = ?'
        );
        $stmt->bind_param('sssii', $phone, $year, $bio, $complete, $user['id']);
        $stmt->execute();
        $stmt->close();

        refresh_session_user($conn);
        set_flash('success', 'Profile updated!');
        redirect(BASE_URL . '/pages/profile.php?id=' . $user['id']);
    }
}

$page_title = 'Complete Profile';
include __DIR__ . '/../includes/header.php';
?>

<div class="card form-card">
  <div class="page-header" style="margin-bottom:24px;">
    <h1>Complete Your Profile</h1>
    <p>Required before you can list products for sale.</p>
  </div>

  <?php if ($errors): ?><div class="alert alert-error"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>

  <form method="POST">
    <?= csrf_field() ?>

    <div class="form-group">
      <label>WhatsApp Number <span style="color:var(--c-warn)">*</span></label>
      <input type="tel" name="phone" required placeholder="0244123456"
             value="<?= e($_POST['phone'] ?? $user['phone_whatsapp'] ?? '') ?>">
      <span class="form-hint">Buyers will use this to contact you on WhatsApp.</span>
    </div>

    <div class="form-group">
      <label>Year Group</label>
      <select name="year_group">
        <?php foreach (['Year 1','Year 2','Year 3','Year 4','Graduate','Staff'] as $y): ?>
          <option value="<?= $y ?>" <?= (($user['year_group'] ?? '') === $y) ? 'selected' : '' ?>><?= $y ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label>Bio</label>
      <textarea name="bio" placeholder="Tell buyers a bit about yourself…"><?= e($_POST['bio'] ?? $user['bio'] ?? '') ?></textarea>
    </div>

    <button type="submit" class="btn btn-primary w-full">Save Profile</button>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
