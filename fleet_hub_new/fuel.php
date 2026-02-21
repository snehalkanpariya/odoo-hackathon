<?php
require_once 'includes/auth.php';
$pageTitle = 'Fuel & Expenses';
$activeNav = 'fuel.php';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'add' || $act === 'edit') {
        $vid   = (int)$_POST['vehicle_id'];
        $tid   = $_POST['trip_id'] ? (int)$_POST['trip_id'] : null;
        $liters = (float)$_POST['liters'];
        $cost  = (float)$_POST['cost'];
        $odo   = (float)($_POST['odometer_reading'] ?? 0);
        $date  = $_POST['fuel_date'];
        $station = trim($_POST['station'] ?? '');

        if (!$vid || $liters <= 0 || $cost <= 0 || !$date) redirect('fuel.php', 'Vehicle, liters, cost and date are required.', 'error');

        if ($act === 'add') {
            $stmt = $db->prepare("INSERT INTO fuel_logs (vehicle_id,trip_id,liters,cost,odometer_reading,fuel_date,station) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param('iidddss', $vid,$tid,$liters,$cost,$odo,$date,$station);
        } else {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("UPDATE fuel_logs SET vehicle_id=?,trip_id=?,liters=?,cost=?,odometer_reading=?,fuel_date=?,station=? WHERE id=?");
            $stmt->bind_param('iidddss'.'i', $vid,$tid,$liters,$cost,$odo,$date,$station,$id);
        }
        if ($stmt->execute()) redirect('fuel.php', $act==='add'?'Fuel log added!':'Updated.');
        else redirect('fuel.php', 'Error: '.$db->error, 'error');
    }

    if ($act === 'delete') {
        $id = (int)$_POST['id'];
        $db->query("DELETE FROM fuel_logs WHERE id=$id");
        redirect('fuel.php', 'Log deleted.');
    }
}

$vehicles = $db->query("SELECT id, name FROM vehicles ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$trips    = $db->query("SELECT t.id, v.name vname, t.origin, t.destination FROM trips t JOIN vehicles v ON t.vehicle_id=v.id WHERE t.status IN ('Dispatched','Completed') ORDER BY t.id DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);

// Per-vehicle cost summary
$summary = $db->query("
    SELECT v.id, v.name, v.odometer,
        COALESCE(SUM(f.cost),0) fuel_cost,
        COALESCE(SUM(f.liters),0) fuel_liters,
        COALESCE((SELECT SUM(m.cost) FROM maintenance_logs m WHERE m.vehicle_id=v.id),0) maint_cost
    FROM vehicles v
    LEFT JOIN fuel_logs f ON f.vehicle_id=v.id
    GROUP BY v.id
    ORDER BY fuel_cost DESC
")->fetch_all(MYSQLI_ASSOC);

$logs = $db->query("
    SELECT f.*, v.name vname, t.origin, t.destination
    FROM fuel_logs f
    JOIN vehicles v ON f.vehicle_id=v.id
    LEFT JOIN trips t ON f.trip_id=t.id
    ORDER BY f.fuel_date DESC
")->fetch_all(MYSQLI_ASSOC);

// Totals
$grandFuel  = array_sum(array_column($summary, 'fuel_cost'));
$grandMaint = array_sum(array_column($summary, 'maint_cost'));

include 'includes/layout.php';
?>

<div class="page-header">
  <div><h1>Fuel & Expenses</h1><p>Financial tracking per vehicle asset</p></div>
  <button class="btn btn-primary" onclick="openModal('fuelModal')">+ Log Fuel</button>
</div>

<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);max-width:650px;margin-bottom:1.5rem;">
  <div class="kpi-card">
    <div class="kpi-header"><span class="kpi-label">Total Fuel Cost</span><div class="kpi-icon orange">⛽</div></div>
    <div class="kpi-value">$<?= number_format($grandFuel) ?></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-header"><span class="kpi-label">Maintenance</span><div class="kpi-icon red">🔧</div></div>
    <div class="kpi-value">$<?= number_format($grandMaint) ?></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-header"><span class="kpi-label">Total OpEx</span><div class="kpi-icon navy">💰</div></div>
    <div class="kpi-value">$<?= number_format($grandFuel + $grandMaint) ?></div>
  </div>
</div>

<!-- Per-vehicle summary -->
<div class="card" style="margin-bottom:1.25rem;">
  <div class="card-header"><h3>Operational Cost by Vehicle</h3></div>
  <div style="overflow-x:auto;">
    <table class="data-table">
      <thead>
        <tr><th>Vehicle</th><th>Fuel Cost</th><th>Maint. Cost</th><th>Total OpEx</th><th>Odometer</th></tr>
      </thead>
      <tbody>
        <?php foreach ($summary as $s):
          $total = $s['fuel_cost'] + $s['maint_cost'];
          $grandTotal = ($grandFuel + $grandMaint) ?: 1;
          $pct = round(($total / $grandTotal) * 100);
        ?>
        <tr>
          <td><strong><?= e($s['name']) ?></strong></td>
          <td>$<?= number_format($s['fuel_cost'], 2) ?></td>
          <td>$<?= number_format($s['maint_cost'], 2) ?></td>
          <td>
            <strong>$<?= number_format($total, 2) ?></strong>
            <div class="progress-bar" style="width:100px;margin-top:4px;">
              <div class="fill fill-teal" style="width:<?= $pct ?>%"></div>
            </div>
          </td>
          <td class="odo"><?= number_format($s['odometer']) ?> km</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Fuel Logs -->
<div class="card">
  <div class="card-header"><h3>Fuel Log Entries</h3></div>
  <div style="overflow-x:auto;">
    <table class="data-table">
      <thead>
        <tr><th>Vehicle</th><th>Date</th><th>Liters</th><th>Cost</th><th>$/L</th><th>Odometer</th><th>Station</th><th>Trip</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $f): ?>
        <tr>
          <td><strong><?= e($f['vname']) ?></strong></td>
          <td><?= date('d M Y', strtotime($f['fuel_date'])) ?></td>
          <td><?= number_format($f['liters'], 2) ?> L</td>
          <td style="font-weight:600;">$<?= number_format($f['cost'], 2) ?></td>
          <td class="text-muted">$<?= number_format($f['cost']/$f['liters'], 3) ?></td>
          <td class="odo"><?= $f['odometer_reading'] ? number_format($f['odometer_reading']).' km' : '—' ?></td>
          <td><?= e($f['station'] ?: '—') ?></td>
          <td class="text-muted">
            <?php if ($f['trip_id']): ?>
              <a href="trips.php">#<?= $f['trip_id'] ?></a>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td>
            <div class="actions">
              <button class="btn btn-ghost btn-sm btn-icon" onclick='editFuel(<?= json_encode($f) ?>)'>✏️</button>
              <form method="POST" style="display:inline;" onsubmit="return confirmDelete()">
                <input type="hidden" name="act" value="delete">
                <input type="hidden" name="id" value="<?= $f['id'] ?>">
                <button class="btn btn-danger btn-sm btn-icon">🗑️</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; if (!$logs): ?>
        <tr><td colspan="9"><div class="empty-state"><div class="icon">⛽</div><h4>No fuel logs yet</h4></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Fuel Modal -->
<div class="modal-overlay" id="fuelModal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="fModalTitle">Log Fuel</h3>
      <button class="modal-close" onclick="closeModal('fuelModal')">×</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="act" id="fFormAct" value="add">
        <input type="hidden" name="id" id="fFormId">
        <div class="form-row">
          <div class="form-group">
            <label>Vehicle *</label>
            <select class="form-control" name="vehicle_id" id="fVehicle" required>
              <option value="">— Select —</option>
              <?php foreach ($vehicles as $v): ?>
                <option value="<?= $v['id'] ?>"><?= e($v['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Trip (Optional)</label>
            <select class="form-control" name="trip_id" id="fTrip">
              <option value="">— None —</option>
              <?php foreach ($trips as $t): ?>
                <option value="<?= $t['id'] ?>">#<?= $t['id'] ?> – <?= e($t['vname']) ?> (<?= e($t['origin']) ?>→<?= e($t['destination']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Liters *</label>
            <input class="form-control" type="number" name="liters" id="fLiters" step="0.01" min="0.1" required>
          </div>
          <div class="form-group">
            <label>Cost ($) *</label>
            <input class="form-control" type="number" name="cost" id="fCost" step="0.01" min="0.01" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Odometer (km)</label>
            <input class="form-control" type="number" name="odometer_reading" id="fOdo" step="0.01" min="0">
          </div>
          <div class="form-group">
            <label>Date *</label>
            <input class="form-control" type="date" name="fuel_date" id="fDate" required value="<?= date('Y-m-d') ?>">
          </div>
        </div>
        <div class="form-group">
          <label>Station</label>
          <input class="form-control" type="text" name="station" id="fStation" placeholder="e.g. Shell – Main St">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('fuelModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="fSubmitBtn">Log Fuel</button>
      </div>
    </form>
  </div>
</div>

<script>
function editFuel(f) {
  document.getElementById('fModalTitle').textContent = 'Edit Fuel Log';
  document.getElementById('fFormAct').value = 'edit';
  document.getElementById('fSubmitBtn').textContent = 'Save';
  document.getElementById('fFormId').value = f.id;
  document.getElementById('fVehicle').value = f.vehicle_id;
  document.getElementById('fTrip').value = f.trip_id || '';
  document.getElementById('fLiters').value = f.liters;
  document.getElementById('fCost').value = f.cost;
  document.getElementById('fOdo').value = f.odometer_reading || '';
  document.getElementById('fDate').value = f.fuel_date;
  document.getElementById('fStation').value = f.station || '';
  openModal('fuelModal');
}
</script>
<?php include 'includes/footer.php'; ?>
