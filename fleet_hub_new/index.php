<?php
require_once 'includes/auth.php';
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$action = $_GET['action'] ?? 'login';
$error = '';
$success = '';

// ── LOGIN ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if ($user && password_verify($pass, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name']    = $user['name'];
        $_SESSION['email']   = $user['email'];
        $_SESSION['role']    = $user['role'];
        redirect('dashboard.php', 'Welcome back, ' . $user['name'] . '!');
    } else {
        $error = 'Invalid email or password. Please try again.';
    }
}

// ── REGISTER ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'register') {
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';
    $role  = $_POST['role'] ?? 'dispatcher';

    if (!$name || !$email || !$pass) {
        $error = 'All fields are required.';
    } elseif ($pass !== $pass2) {
        $error = 'Passwords do not match.';
    } elseif (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        $db = getDB();
        $check = $db->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param('s', $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'An account with this email already exists.';
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('ssss', $name, $email, $hash, $role);
            $stmt->execute();
            $success = 'Account created! You can now log in.';
            $action = 'login';
        }
    }
}

// ── FORGOT PASSWORD ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'forgot') {
    $email = trim($_POST['email'] ?? '');
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    if ($found) {
        $token = bin2hex(random_bytes(20));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $upd = $db->prepare("UPDATE users SET reset_token=?, reset_expires=? WHERE id=?");
        $upd->bind_param('ssi', $token, $expires, $found['id']);
        $upd->execute();
        $success = "A password reset link would be sent to <strong>$email</strong>. (Demo: token = <code>$token</code>)";
    } else {
        $success = "If that email exists, a reset link has been sent.";
    }
}

// ── RESET PASSWORD ──
$resetToken = $_GET['token'] ?? '';
$resetUser = null;
if ($action === 'reset' && $resetToken) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE reset_token=? AND reset_expires > NOW()");
    $stmt->bind_param('s', $resetToken);
    $stmt->execute();
    $resetUser = $stmt->get_result()->fetch_assoc();
    if (!$resetUser) $error = 'Invalid or expired reset link.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reset' && $resetToken) {
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE reset_token=? AND reset_expires > NOW()");
    $stmt->bind_param('s', $resetToken);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    if (!$u) { $error = 'Invalid or expired link.'; }
    elseif ($pass !== $pass2) { $error = 'Passwords do not match.'; }
    elseif (strlen($pass) < 8) { $error = 'Password must be at least 8 characters.'; }
    else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $upd = $db->prepare("UPDATE users SET password=?, reset_token=NULL, reset_expires=NULL WHERE id=?");
        $upd->bind_param('si', $hash, $u['id']);
        $upd->execute();
        $success = 'Password reset! You can now log in.'; $action = 'login';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Fleet Hub · <?= ucfirst($action) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="auth-wrapper">
  <!-- Brand Panel -->
  <div class="auth-brand">
    <div class="brand-icon">🚛</div>
    <h1>Fleet Hub</h1>
    <p>Intelligent Fleet Management Platform</p>
    <div class="brand-features">
      <div class="feat"><div class="feat-icon">📊</div><span>Real-time asset tracking & analytics</span></div>
      <div class="feat"><div class="feat-icon">🛡️</div><span>Driver safety & compliance monitoring</span></div>
      <div class="feat"><div class="feat-icon">💰</div><span>Automated fuel & maintenance ROI</span></div>
      <div class="feat"><div class="feat-icon">📦</div><span>Smart cargo dispatch workflow</span></div>
    </div>
  </div>

  <!-- Form Panel -->
  <div class="auth-form-side">
    <div class="auth-card">

      <?php if ($action === 'login'): ?>
        <h2>Welcome back</h2>
        <p class="subtitle">Sign in to your Fleet Hub account</p>
        <?php if ($error): ?><div class="alert alert-error"><span><?= $error ?></span></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><span><?= $success ?></span></div><?php endif; ?>
        <form method="POST">
          <div class="form-group">
            <label>Email Address</label>
            <div class="input-group">
              <span class="input-icon">✉️</span>
              <input class="form-control" type="email" name="email" placeholder="you@company.com" required autofocus>
            </div>
          </div>
          <div class="form-group">
            <label>Password</label>
            <div class="input-group">
              <span class="input-icon">🔒</span>
              <input class="form-control" type="password" name="password" id="pwField" placeholder="Enter password" required>
              <button type="button" class="input-action" onclick="togglePw('pwField',this)">👁️</button>
            </div>
          </div>
          <div style="display:flex;justify-content:flex-end;margin-top:-.5rem;margin-bottom:1rem;">
            <a href="?action=forgot" style="font-size:.825rem;color:var(--accent);">Forgot password?</a>
          </div>
          <button class="btn btn-primary btn-full btn-lg" type="submit">Sign In →</button>
        </form>
        <div class="divider"></div>
        <p class="text-center text-sm text-muted">Don't have an account? <a href="?action=register">Create one</a></p>
        <div style="margin-top:1.5rem;padding:1rem;background:var(--surface-2);border-radius:var(--radius);border:1px solid var(--border);">
          <p class="text-sm text-muted" style="margin-bottom:.5rem;font-weight:600;">Demo Credentials</p>
          <p class="text-sm text-muted">📧 admin@fleethub.com</p>
          <p class="text-sm text-muted">🔒 Admin@123</p>
        </div>

      <?php elseif ($action === 'register'): ?>
        <h2>Create Account</h2>
        <p class="subtitle">Join your team on Fleet Hub</p>
        <?php if ($error): ?><div class="alert alert-error"><span><?= $error ?></span></div><?php endif; ?>
        <form method="POST">
          <div class="form-group">
            <label>Full Name</label>
            <div class="input-group">
              <span class="input-icon">👤</span>
              <input class="form-control" type="text" name="name" placeholder="John Smith" required>
            </div>
          </div>
          <div class="form-group">
            <label>Email Address</label>
            <div class="input-group">
              <span class="input-icon">✉️</span>
              <input class="form-control" type="email" name="email" placeholder="you@company.com" required>
            </div>
          </div>
          <div class="form-group">
            <label>Role</label>
            <select class="form-control" name="role">
              <option value="dispatcher">Dispatcher</option>
              <option value="manager">Fleet Manager</option>
              <option value="safety_officer">Safety Officer</option>
              <option value="financial_analyst">Financial Analyst</option>
            </select>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Password</label>
              <input class="form-control" type="password" name="password" placeholder="Min 8 chars" required>
            </div>
            <div class="form-group">
              <label>Confirm</label>
              <input class="form-control" type="password" name="password2" placeholder="Repeat" required>
            </div>
          </div>
          <button class="btn btn-primary btn-full btn-lg" type="submit">Create Account</button>
        </form>
        <div class="divider"></div>
        <p class="text-center text-sm text-muted">Already have an account? <a href="?action=login">Sign in</a></p>

      <?php elseif ($action === 'forgot'): ?>
        <a href="?action=login" style="font-size:.825rem;color:var(--text-muted);display:inline-flex;align-items:center;gap:.3rem;margin-bottom:1.5rem;">← Back to login</a>
        <h2>Reset Password</h2>
        <p class="subtitle">Enter your email and we'll send a reset link</p>
        <?php if ($error): ?><div class="alert alert-error"><span><?= $error ?></span></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><span><?= $success ?></span></div><?php endif; ?>
        <?php if (!$success): ?>
        <form method="POST">
          <div class="form-group">
            <label>Email Address</label>
            <div class="input-group">
              <span class="input-icon">✉️</span>
              <input class="form-control" type="email" name="email" placeholder="you@company.com" required autofocus>
            </div>
          </div>
          <button class="btn btn-primary btn-full btn-lg" type="submit">Send Reset Link</button>
        </form>
        <?php endif; ?>

      <?php elseif ($action === 'reset'): ?>
        <h2>Set New Password</h2>
        <p class="subtitle">Choose a strong new password</p>
        <?php if ($error): ?><div class="alert alert-error"><span><?= $error ?></span></div><?php endif; ?>
        <?php if ($resetUser): ?>
        <form method="POST">
          <div class="form-group">
            <label>New Password</label>
            <input class="form-control" type="password" name="password" placeholder="Min 8 characters" required>
          </div>
          <div class="form-group">
            <label>Confirm Password</label>
            <input class="form-control" type="password" name="password2" placeholder="Repeat password" required>
          </div>
          <button class="btn btn-primary btn-full btn-lg" type="submit">Update Password</button>
        </form>
        <?php endif; ?>
      <?php endif; ?>

    </div>
  </div>
</div>

<script>
function togglePw(id, btn) {
  const f = document.getElementById(id);
  f.type = f.type === 'password' ? 'text' : 'password';
  btn.textContent = f.type === 'password' ? '👁️' : '🙈';
}
</script>
</body>
</html>
