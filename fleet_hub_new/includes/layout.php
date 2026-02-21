<?php
// includes/layout.php
requireLogin();
$user = currentUser();
$role = $user['role'] ?? 'dispatcher';
$userName = $user['name'] ?? 'User';
$initial = strtoupper(substr($userName, 0, 1));

$navItems = [
    ['section' => 'Core'],
    ['🗂️', 'Dashboard', 'dashboard.php', null],
    ['🚛', 'Vehicles', 'vehicles.php', null],
    ['👤', 'Drivers', 'drivers.php', null],
    ['section' => 'Operations'],
    ['📦', 'Trip Dispatch', 'trips.php', null],
    ['🔧', 'Maintenance', 'maintenance.php', ['manager','dispatcher','safety_officer']],
    ['⛽', 'Fuel & Expenses', 'fuel.php', ['manager','financial_analyst']],
    ['section' => 'Insights'],
    ['📊', 'Analytics', 'analytics.php', ['manager','financial_analyst']],
    ['section' => 'Account'],
    ['⚙️', 'Settings', 'settings.php', ['manager']],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Fleet Hub') ?> · Fleet Hub</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700;9..40,800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- Overlay backdrop -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="app-layout">

<!-- Sidebar (slide-over) -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-logo">🚛</div>
    <div>
      <div class="brand-text">Fleet Hub</div>
      <span class="brand-sub">Management System</span>
    </div>
    <button class="sidebar-close" onclick="closeSidebar()" title="Close menu">✕</button>
  </div>
  <nav class="sidebar-nav">
    <?php foreach ($navItems as $item):
      if (isset($item['section'])): ?>
        <div class="nav-section-title"><?= $item['section'] ?></div>
      <?php continue; endif;
      [$icon, $label, $href, $roles] = $item;
      if ($roles && !in_array($role, $roles)) continue;
      $active = ($activeNav ?? '') === $href ? 'active' : '';
    ?>
      <a class="nav-item <?= $active ?>" href="<?= $href ?>" onclick="closeSidebar()">
        <span class="nav-icon"><?= $icon ?></span>
        <span><?= $label ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="avatar"><?= $initial ?></div>
      <div class="user-info">
        <div class="user-name"><?= e($userName) ?></div>
        <div class="user-role"><?= ucfirst(str_replace('_', ' ', $role)) ?></div>
      </div>
      <a href="logout.php" title="Logout" style="color:rgba(255,255,255,.45);font-size:1.1rem;margin-left:auto;">⏻</a>
    </div>
  </div>
</aside>

<!-- Main -->
<div class="main-content">
  <header class="topbar">
    <!-- 3-dot / hamburger trigger -->
    <button class="menu-trigger" id="menuTrigger" onclick="openSidebar()" title="Open navigation">
      <svg viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="9" cy="3"  r="1.6" fill="currentColor"/>
        <circle cx="9" cy="9"  r="1.6" fill="currentColor"/>
        <circle cx="9" cy="15" r="1.6" fill="currentColor"/>
      </svg>
    </button>

    <div class="topbar-brand">
      <div class="topbar-brand-logo">🚛</div>
      Fleet Hub
    </div>

    <div class="topbar-title"><?= e($pageTitle ?? 'Dashboard') ?></div>

    <div class="topbar-actions">
      <span class="text-sm text-muted"><?= date('D, d M Y') ?></span>
      <div class="topbar-sep"></div>
      <span class="text-sm" style="color:var(--teal);font-weight:600;"><?= e($userName) ?></span>
    </div>
  </header>
  <main class="page-content">
  <?php flash(); ?>

<script>
function openSidebar() {
  var sb = document.getElementById('sidebar');
  var ov = document.getElementById('sidebarOverlay');
  sb.classList.add('open');
  ov.classList.add('visible');
  // next tick so transition fires
  requestAnimationFrame(function(){ ov.classList.add('active'); });
  document.body.style.overflow = 'hidden';
}
function closeSidebar() {
  var sb = document.getElementById('sidebar');
  var ov = document.getElementById('sidebarOverlay');
  sb.classList.remove('open');
  ov.classList.remove('active');
  setTimeout(function(){ ov.classList.remove('visible'); }, 220);
  document.body.style.overflow = '';
}
// Close on Escape
document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeSidebar(); });
</script>
