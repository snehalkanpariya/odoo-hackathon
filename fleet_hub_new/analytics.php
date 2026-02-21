<?php
require_once 'includes/auth.php';
$pageTitle = 'Analytics & Reports';
$activeNav = 'analytics.php';
$db = getDB();

// Fleet overview
$fleetStats = $db->query("
    SELECT
        COUNT(*) total,
        SUM(status='Available') avail,
        SUM(status='On Trip') on_trip,
        SUM(status='In Shop') in_shop,
        SUM(status='Retired') retired,
        SUM(odometer) total_km
    FROM vehicles
")->fetch_assoc();

// Trip stats
$tripStats = $db->query("
    SELECT
        COUNT(*) total,
        SUM(status='Completed') completed,
        SUM(status='Cancelled') cancelled,
        SUM(distance_km) total_km,
        SUM(revenue) total_revenue,
        AVG(distance_km) avg_km
    FROM trips
")->fetch_assoc();

// Fuel efficiency per vehicle (km/L)
$efficiency = $db->query("
    SELECT v.name, v.type,
        COALESCE(SUM(f.liters),0) liters,
        COALESCE(SUM(f.cost),0) fuel_cost,
        COALESCE(SUM(t.distance_km),0) km,
        COALESCE(SUM(t.revenue),0) revenue,
        COALESCE((SELECT SUM(m.cost) FROM maintenance_logs m WHERE m.vehicle_id=v.id),0) maint_cost,
        v.acquisition_cost
    FROM vehicles v
    LEFT JOIN fuel_logs f ON f.vehicle_id=v.id
    LEFT JOIN trips t ON t.vehicle_id=v.id AND t.status='Completed'
    GROUP BY v.id
    HAVING liters > 0
    ORDER BY km DESC
")->fetch_all(MYSQLI_ASSOC);

// Monthly trip completions (last 6 months)
$monthly = $db->query("
    SELECT DATE_FORMAT(completed_at,'%Y-%m') mo, COUNT(*) c, SUM(distance_km) km, SUM(revenue) rev
    FROM trips
    WHERE status='Completed' AND completed_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY mo ORDER BY mo ASC
")->fetch_all(MYSQLI_ASSOC);

// Driver performance
$driverPerf = $db->query("
    SELECT d.name, d.safety_score, d.trips_completed, d.trips_total,
        COALESCE(SUM(t.distance_km),0) km,
        COALESCE(SUM(t.revenue),0) revenue
    FROM drivers d
    LEFT JOIN trips t ON t.driver_id=d.id AND t.status='Completed'
    GROUP BY d.id
    ORDER BY d.trips_completed DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

$completionRate = $tripStats['total'] > 0 ? round($tripStats['completed'] / $tripStats['total'] * 100) : 0;
$utilRate = $fleetStats['total'] > 0 ? round($fleetStats['on_trip'] / $fleetStats['total'] * 100) : 0;

include 'includes/layout.php';
?>

<div class="page-header">
  <div><h1>Analytics & Financial Reports</h1><p>Data-driven fleet performance insights</p></div>
  <div style="display:flex;gap:.5rem;">
    <button onclick="window.print()" class="btn btn-outline btn-sm">🖨️ Print Report</button>
  </div>
</div>

<!-- Top KPIs -->
<div class="kpi-grid" style="margin-bottom:1.5rem;">
  <div class="kpi-card">
    <div class="kpi-header"><span class="kpi-label">Total Revenue</span><div class="kpi-icon green">💵</div></div>
    <div class="kpi-value">$<?= number_format($tripStats['total_revenue'] ?? 0) ?></div>
    <div class="kpi-sub">From <?= $tripStats['completed'] ?> completed trips</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-header"><span class="kpi-label">Total Distance</span><div class="kpi-icon teal">🗺️</div></div>
    <div class="kpi-value"><?= number_format($tripStats['total_km'] ?? 0) ?></div>
    <div class="kpi-sub">Kilometers driven</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-header"><span class="kpi-label">Fleet Utilization</span><div class="kpi-icon navy">📊</div></div>
    <div class="kpi-value"><?= $utilRate ?>%</div>
    <div class="progress-bar mt-1"><div class="fill fill-teal" style="width:<?= $utilRate ?>%"></div></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-header"><span class="kpi-label">Trip Completion</span><div class="kpi-icon green">✅</div></div>
    <div class="kpi-value"><?= $completionRate ?>%</div>
    <div class="progress-bar mt-1"><div class="fill <?= $completionRate>=80?'fill-green':'fill-orange' ?>" style="width:<?= $completionRate ?>%"></div></div>
  </div>
</div>

<!-- Fuel Efficiency Table -->
<div class="card" style="margin-bottom:1.25rem;">
  <div class="card-header">
    <h3>⛽ Fuel Efficiency & Vehicle ROI</h3>
  </div>
  <div style="overflow-x:auto;">
    <table class="data-table">
      <thead>
        <tr>
          <th>Vehicle</th><th>Type</th><th>Distance (km)</th><th>Liters</th>
          <th>Efficiency (km/L)</th><th>Fuel Cost</th><th>Maint. Cost</th>
          <th>Revenue</th><th>ROI</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($efficiency as $e):
          $kmL = $e['liters'] > 0 ? round($e['km'] / $e['liters'], 2) : 0;
          $opex = $e['fuel_cost'] + $e['maint_cost'];
          $roi  = $e['acquisition_cost'] > 0 ? round(($e['revenue'] - $opex) / $e['acquisition_cost'] * 100, 1) : '—';
          $roiColor = is_numeric($roi) && $roi >= 0 ? 'var(--success)' : 'var(--danger)';
        ?>
        <tr>
          <td><strong><?= e($e['name']) ?></strong></td>
          <td><span class="tag"><?= e($e['type']) ?></span></td>
          <td><?= number_format($e['km'], 1) ?></td>
          <td><?= number_format($e['liters'], 1) ?> L</td>
          <td>
            <strong><?= $kmL ?> km/L</strong>
            <div class="progress-bar" style="width:80px;margin-top:3px;">
              <div class="fill fill-teal" style="width:<?= min(100, $kmL*10) ?>%"></div>
            </div>
          </td>
          <td>$<?= number_format($e['fuel_cost'],2) ?></td>
          <td>$<?= number_format($e['maint_cost'],2) ?></td>
          <td style="font-weight:600;">$<?= number_format($e['revenue'],2) ?></td>
          <td style="font-weight:700;color:<?= $roiColor ?>">
            <?= is_numeric($roi) ? $roi.'%' : $roi ?>
          </td>
        </tr>
        <?php endforeach; if (!$efficiency): ?>
        <tr><td colspan="9"><div class="empty-state"><p>No data yet. Add fuel logs to see efficiency metrics.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="two-col" style="gap:1.25rem;margin-bottom:1.25rem;">
  <!-- Monthly breakdown -->
  <div class="card">
    <div class="card-header"><h3>📅 Monthly Performance</h3></div>
    <div style="overflow-x:auto;">
      <table class="data-table">
        <thead><tr><th>Month</th><th>Trips</th><th>Distance</th><th>Revenue</th></tr></thead>
        <tbody>
          <?php foreach ($monthly as $m): ?>
          <tr>
            <td><?= date('M Y', strtotime($m['mo'].'-01')) ?></td>
            <td><?= $m['c'] ?></td>
            <td><?= number_format($m['km'],1) ?> km</td>
            <td style="font-weight:600;">$<?= number_format($m['rev'],2) ?></td>
          </tr>
          <?php endforeach; if (!$monthly): ?>
          <tr><td colspan="4"><div class="empty-state"><p>No completed trips yet.</p></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Driver performance -->
  <div class="card">
    <div class="card-header"><h3>🏆 Driver Performance</h3></div>
    <div style="overflow-x:auto;">
      <table class="data-table">
        <thead><tr><th>Driver</th><th>Trips</th><th>Safety</th><th>Revenue</th></tr></thead>
        <tbody>
          <?php foreach ($driverPerf as $d):
            $sc = $d['safety_score'] >= 80 ? 'fill-green' : ($d['safety_score'] >= 60 ? 'fill-orange' : 'fill-red');
          ?>
          <tr>
            <td><strong><?= e($d['name']) ?></strong></td>
            <td><?= $d['trips_completed'] ?></td>
            <td>
              <div style="font-size:.8rem;font-weight:700;"><?= $d['safety_score'] ?></div>
              <div class="progress-bar" style="width:60px;"><div class="fill <?= $sc ?>" style="width:<?= $d['safety_score'] ?>%"></div></div>
            </td>
            <td>$<?= number_format($d['revenue'],0) ?></td>
          </tr>
          <?php endforeach; if (!$driverPerf): ?>
          <tr><td colspan="4"><div class="empty-state"><p>No driver data.</p></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Fleet Status Summary -->
<div class="card">
  <div class="card-header"><h3>🚛 Fleet Status Summary</h3></div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:1rem;text-align:center;">
      <?php
      $statItems = [
        ['Available',  $fleetStats['avail'],   'pill-available'],
        ['On Trip',    $fleetStats['on_trip'],  'pill-on-trip'],
        ['In Shop',    $fleetStats['in_shop'],  'pill-in-shop'],
        ['Retired',    $fleetStats['retired'],  'pill-retired'],
      ];
      foreach ($statItems as [$label, $count, $cls]): ?>
        <div style="background:var(--surface-2);border-radius:var(--radius);padding:1rem;">
          <div style="font-size:1.5rem;font-weight:800;color:var(--primary);"><?= $count ?></div>
          <span class="pill <?= $cls ?>" style="margin-top:.3rem;"><?= $label ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
