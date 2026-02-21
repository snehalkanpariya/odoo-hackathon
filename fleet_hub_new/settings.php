<?php
require_once 'includes/auth.php';
$pageTitle = 'Settings';
$activeNav = 'settings.php';

$db = getDB();
$uid = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'profile') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if (!$name || !$email) redirect('settings.php', 'Name and email are required.', 'error');
        $stmt = $db->prepare("UPDATE users SET name=?, email=? WHERE id=?");
        $stmt->bind_param('ssi', $name, $email, $uid);
        $stmt->execute();
        $_SESSION['name'] = $name;
        $_SESSION['email'] = $email;
        redirect('settings.php', 'Profile updated!');
    }

    if ($act === 'password') {
        $cur  = $_POST['current_password'] ?? '';
        $new  = $_POST['new_password'] ?? '';
        $conf = $_POST['confirm_password'] ?? '';
        $user = $db->query("SELECT password FROM users WHERE id=$uid")->fetch_assoc();
        if (!password_verify($cur, $user['password'])) redirect('settings.php', 'Current password is incorrect.', 'error');
        if ($new !== $conf) redirect('settings.php', 'New passwords do not match.', 'error');
        if (strlen($new) < 8) redirect('settings.php', 'Password must be at least 8 characters.', 'error');
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $db->query("UPDATE users SET password='$hash' WHERE id=$uid");
        redirect('settings.php', 'Password changed successfully!');
    }
}

$user = $db->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
$allUsers = $db->query("SELECT * FROM users ORDER BY name")->fetch_all(MYSQLI_ASSOC);

include 'includes/layout.php';
?>

<div class="page-header">
  <div><h1>Settings</h1><p>Account and system configuration</p></div>
</div>

<div class="two-col" style="align-items:start;">
  <!-- Profile -->
  <div>
    <div class="card" style="margin-bottom:1.25rem;">
      <div class="card-header"><h3>👤 My Profile</h3></div>
      <form method="POST" class="card-body">
        <input type="hidden" name="act" value="profile">
        <div class="form-group">
          <label>Full Name</label>
          <input class="form-control" type="text" name="name" value="<?= e($user['name']) ?>" required>
        </div>
        <div class="form-group">
          <label>Email</label>
          <input class="form-control" type="email" name="email" value="<?= e($user['email']) ?>" required>
        </div>
        <div class="form-group">
          <label>Role</label>
          <input class="form-control" type="text" value="<?= e(ucfirst(str_replace('_',' ',$user['role']))) ?>" readonly style="background:var(--surface-2);">
        </div>
        <button class="btn btn-primary" type="submit">Save Profile</button>
      </form>
    </div>

    <div class="card">
      <div class="card-header"><h3>🔒 Change Password</h3></div>
      <form method="POST" class="card-body">
        <input type="hidden" name="act" value="password">
        <div class="form-group">
          <label>Current Password</label>
          <input class="form-control" type="password" name="current_password" required>
        </div>
        <div class="form-group">
          <label>New Password</label>
          <input class="form-control" type="password" name="new_password" required>
        </div>
        <div class="form-group">
          <label>Confirm New Password</label>
          <input class="form-control" type="password" name="confirm_password" required>
        </div>
        <button class="btn btn-primary" type="submit">Update Password</button>
      </form>
    </div>
  </div>

  <!-- Team -->
  <div class="card">
    <div class="card-header"><h3>👥 Team Members</h3></div>
    <div style="overflow-x:auto;">
      <table class="data-table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Joined</th></tr></thead>
        <tbody>
          <?php foreach ($allUsers as $u): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:.6rem;">
                <div style="width:30px;height:30px;background:var(--accent);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.75rem;flex-shrink:0;">
                  <?= strtoupper(substr($u['name'],0,1)) ?>
                </div>
                <strong><?= e($u['name']) ?></strong>
              </div>
            </td>
            <td class="text-muted"><?= e($u['email']) ?></td>
            <td><span class="tag"><?= e(ucfirst(str_replace('_',' ',$u['role']))) ?></span></td>
            <td class="text-muted"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
