<?php
require_once 'includes/auth.php';
$pageTitle = 'Command Center';
$activeNav = 'dashboard.php';
include 'includes/layout.php';

$db = getDB();

// KPI queries
$kpi = [];
$kpi['total']       = $db->query("SELECT COUNT(*) c FROM vehicles")->fetch_assoc()['c'];
$kpi['on_trip']     = $db->query("SELECT COUNT(*) c FROM vehicles WHERE status='On Trip'")->fetch_assoc()['c'];
$kpi['in_shop']     = $db->query("SELECT COUNT(*) c FROM vehicles WHERE status='In Shop'")->fetch_assoc()['c'];
$kpi['available']   = $db->query("SELECT COUNT(*) c FROM vehicles WHERE status='Available'")->fetch_assoc()['c'];
$kpi['pending']     = $db->query("SELECT COUNT(*) c FROM trips WHERE status='Draft'")->fetch_assoc()['c'];
$kpi['drivers']     = $db->query("SELECT COUNT(*) c FROM drivers WHERE status='On Duty'")->fetch_assoc()['c'];
$utilRate = $kpi['total'] > 0 ? round(($kpi['on_trip'] / $kpi['total']) * 100) : 0;

// Expiring licenses (within 30 days)
$expiring = $db->query("SELECT name, license_expiry, DATEDIFF(license_expiry, CURDATE()) d FROM drivers WHERE license_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY d ASC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

// Recent trips
$trips = $db->query("SELECT t.*, v.name vname, d.name dname FROM trips t JOIN vehicles v ON t.vehicle_id=v.id JOIN drivers d ON t.driver_id=d.id ORDER BY t.created_at DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);

// Vehicle summary by type
$byType = $db->query("SELECT type, COUNT(*) c, SUM(status='Available') av, SUM(status='On Trip') ot, SUM(status='In Shop') sh FROM vehicles GROUP BY type")->fetch_all(MYSQLI_ASSOC);

// Recent maintenance
$maint = $db->query("SELECT m.*, v.name vname FROM maintenance_logs m JOIN vehicles v ON m.vehicle_id=v.id WHERE m.status != 'Completed' ORDER BY m.created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
?>

<div class="page-header">
  <div>
    <h1>Command Center</h1>
    <p>Real-time fleet overview · <?= date('l, d F Y') ?></p>
  </div>
  <a href="trips.php?new=1" class="btn btn-primary">+ New Trip</a>
</div>

<!-- KPI Grid -->
<div class="kpi-grid">
  <div class="kpi-card">
    <div class="kpi-header">
      <span class="kpi-label">Total Fleet</span>
      <div class="kpi-icon navy">🚛</div>
    </div>
    <div class="kpi-value"><?= $kpi['total'] ?></div>
    <div class="kpi-sub"><?= $kpi['available'] ?> available</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-header">
      <span class="kpi-label">Active Trips</span>
      <div class="kpi-icon teal">📦</div>
    </div>
    <div class="kpi-value"><?= $kpi['on_trip'] ?></div>
    <div class="kpi-sub">Vehicles on route</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-header">
      <span class="kpi-label">In Maintenance</span>
      <div class="kpi-icon orange">🔧</div>
    </div>
    <div class="kpi-value"><?= $kpi['in_shop'] ?></div>
    <div class="kpi-sub">Vehicles in shop</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-header">
      <span class="kpi-label">Utilization Rate</span>
      <div class="kpi-icon green">📈</div>
    </div>
    <div class="kpi-value"><?= $utilRate ?>%</div>
    <div class="progress-bar mt-1" style="height:5px;"><div class="fill fill-teal" style="width:<?= $utilRate ?>%"></div></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-header">
      <span class="kpi-label">Pending Cargo</span>
      <div class="kpi-icon red">⏳</div>
    </div>
    <div class="kpi-value"><?= $kpi['pending'] ?></div>
    <div class="kpi-sub">Awaiting dispatch</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-header">
      <span class="kpi-label">Drivers On Duty</span>
      <div class="kpi-icon teal">👤</div>
    </div>
    <div class="kpi-value"><?= $kpi['drivers'] ?></div>
    <div class="kpi-sub">Active drivers</div>
  </div>
</div>

<div class="two-col" style="gap:1.25rem;margin-bottom:1.25rem;">
  <!-- Fleet by Type -->
  <div class="card">
    <div class="card-header">
      <h3>Fleet by Vehicle Type</h3>
    </div>
    <div style="overflow-x:auto;">
      <table class="data-table">
        <thead><tr><th>Type</th><th>Total</th><th>Available</th><th>On Trip</th><th>In Shop</th></tr></thead>
        <tbody>
          <?php foreach ($byType as $r): ?>
          <tr>
            <td><strong><?= e($r['type']) ?></strong></td>
            <td><?= $r['c'] ?></td>
            <td><span class="pill pill-available"><?= $r['av'] ?></span></td>
            <td><span class="pill pill-on-trip"><?= $r['ot'] ?></span></td>
            <td><span class="pill pill-in-shop"><?= $r['sh'] ?></span></td>
          </tr>
          <?php endforeach; if(!$byType): ?>
          <tr><td colspan="5"><div class="empty-state"><p>No vehicles registered yet.</p></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- License Expiry Alerts -->
  <div class="card">
    <div class="card-header">
      <h3>⚠️ License Expiry Alerts</h3>
      <a href="drivers.php" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <div class="card-body">
      <?php if ($expiring): foreach ($expiring as $d): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:.6rem 0;border-bottom:1px solid var(--border);">
          <div>
            <div style="font-weight:600;font-size:.875rem;"><?= e($d['name']) ?></div>
            <div class="text-sm text-muted">Expires <?= date('d M Y', strtotime($d['license_expiry'])) ?></div>
          </div>
          <span class="pill <?= $d['d'] <= 7 ? 'pill-retired' : 'pill-in-shop' ?>"><?= $d['d'] ?> days</span>
        </div>
      <?php endforeach; else: ?>
        <div class="empty-state" style="padding:2rem 0;"><div class="icon">✅</div><p>No licenses expiring soon</p></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Recent Trips -->
<div class="card" style="margin-bottom:1.25rem;">
  <div class="card-header">
    <h3>Recent Trips</h3>
    <a href="trips.php" class="btn btn-ghost btn-sm">View All</a>
  </div>
  <div style="overflow-x:auto;">
    <table class="data-table">
      <thead><tr><th>#</th><th>Vehicle</th><th>Driver</th><th>Route</th><th>Cargo (kg)</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach ($trips as $t): $sc = strtolower(str_replace(' ','-',$t['status'])); ?>
        <tr>
          <td class="text-muted">#<?= $t['id'] ?></td>
          <td><strong><?= e($t['vname']) ?></strong></td>
          <td><?= e($t['dname']) ?></td>
          <td><?= e($t['origin']) ?> → <?= e($t['destination']) ?></td>
          <td><?= number_format($t['cargo_weight']) ?></td>
          <td><span class="pill pill-<?= $sc ?>"><?= e($t['status']) ?></span></td>
          <td class="text-muted"><?= date('d M', strtotime($t['created_at'])) ?></td>
        </tr>
        <?php endforeach; if(!$trips): ?>
        <tr><td colspan="7"><div class="empty-state"><p>No trips yet.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Active Maintenance -->
<?php if ($maint): ?>
<div class="card">
  <div class="card-header">
    <h3>🔧 Active Maintenance</h3>
    <a href="maintenance.php" class="btn btn-ghost btn-sm">View All</a>
  </div>
  <div style="overflow-x:auto;">
    <table class="data-table">
      <thead><tr><th>Vehicle</th><th>Service</th><th>Date</th><th>Cost</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($maint as $m): $sc = strtolower(str_replace(' ','-',$m['status'])); ?>
        <tr>
          <td><strong><?= e($m['vname']) ?></strong></td>
          <td><?= e($m['service_type']) ?></td>
          <td><?= date('d M Y', strtotime($m['service_date'])) ?></td>
          <td>$<?= number_format($m['cost'], 2) ?></td>
          <td><span class="pill pill-<?= $sc ?>"><?= e($m['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
