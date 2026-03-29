<?php
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) redirect(BASE_URL);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name     = trim($_POST['name'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $phone    = trim($_POST['phone'] ?? '');
    $year     = trim($_POST['year_group'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $role     = in_array($_POST['role'] ?? '', ['buyer','seller','both']) ? $_POST['role'] : 'buyer';

    if (!$name)                       $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (strlen($password) < 8)        $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)       $errors[] = 'Passwords do not match.';

    // Check duplicate email
    if (empty($errors)) {
        $chk = $conn->prepare('SELECT id FROM users WHERE email = ?');
        $chk->bind_param('s', $email);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) $errors[] = 'An account with that email already exists.';
        $chk->close();
    }

    // Handle ID image upload
    $id_image_path = null;
    if (!empty($_FILES['id_image']['name'])) {
        $id_image_path = handle_image_upload($_FILES['id_image'], 'id_images');
        if (!$id_image_path) $errors[] = 'ID image upload failed. Use JPG/PNG under 3 MB.';
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare(
            'INSERT INTO users (name, email, password_hash, phone_whatsapp, year_group, role, id_image)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('sssssss', $name, $email, $hash, $phone, $year, $role, $id_image_path);
        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            // Create empty cart for user
            $c = $conn->prepare('INSERT INTO cart (user_id) VALUES (?)');
            $c->bind_param('i', $new_id);
            $c->execute();
            $c->close();

            set_flash('success', 'Account created! Please log in.');
            redirect(BASE_URL . '/pages/login.php');
        } else {
            $errors[] = 'Registration failed. Please try again.';
        }
        $stmt->close();
    }
}

$page_title = 'Register';
include __DIR__ . '/../includes/header.php';
?>

<div class="card form-card">
  <div class="page-header" style="margin-bottom:24px;">
    <h1>Create Account</h1>
    <p>Join the Ashesi student marketplace</p>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-error"><?= implode('<br>', array_map('e', $errors)) ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="form-group">
      <label for="name">Full Name</label>
      <input type="text" id="name" name="name" required value="<?= e($_POST['name'] ?? '') ?>">
    </div>

    <div class="form-group">
      <label for="email">Ashesi Email</label>
      <input type="email" id="email" name="email" required placeholder="yourname@ashesi.edu.gh"
             value="<?= e($_POST['email'] ?? '') ?>">
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="phone">WhatsApp Number</label>
        <input type="tel" id="phone" name="phone" placeholder="0244123456"
               value="<?= e($_POST['phone'] ?? '') ?>">
        <span class="form-hint">Used for buyer contact button</span>
      </div>
      <div class="form-group">
        <label for="year_group">Year Group</label>
        <select id="year_group" name="year_group">
          <option value="">Select year</option>
          <?php foreach (['Year 1','Year 2','Year 3','Year 4','Graduate','Staff'] as $y): ?>
            <option value="<?= $y ?>" <?= (($_POST['year_group'] ?? '') === $y) ? 'selected' : '' ?>><?= $y ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label for="role">I want to</label>
      <select id="role" name="role">
        <option value="buyer"  <?= (($_POST['role'] ?? '') === 'buyer')  ? 'selected' : '' ?>>Buy only</option>
        <option value="seller" <?= (($_POST['role'] ?? '') === 'seller') ? 'selected' : '' ?>>Sell only</option>
        <option value="both"   <?= (($_POST['role'] ?? 'both') === 'both') ? 'selected' : '' ?>>Buy & Sell</option>
      </select>
    </div>

    <div class="form-group">
      <label for="id_image">Student ID Photo (for verification)</label>
      <input type="file" id="id_image" name="id_image" accept="image/*">
      <span class="form-hint">JPG or PNG, max 3 MB. Helps build trust.</span>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required minlength="8">
      </div>
      <div class="form-group">
        <label for="confirm_password">Confirm Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required>
      </div>
    </div>

    <button type="submit" class="btn btn-primary w-full">Create Account</button>

    <p class="text-center mt-3" style="font-size:.88rem;">
      Already have an account? <a href="<?= BASE_URL ?>/pages/login.php">Log in</a>
    </p>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
