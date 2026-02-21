<?php
require_once 'includes/auth.php';
$pageTitle = 'Trip Dispatcher';
$activeNav = 'trips.php';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'create') {
        $vid    = (int)$_POST['vehicle_id'];
        $did    = (int)$_POST['driver_id'];
        $origin = trim($_POST['origin'] ?? '');
        $dest   = trim($_POST['destination'] ?? '');
        $cargo  = (float)$_POST['cargo_weight'];
        $desc   = trim($_POST['cargo_description'] ?? '');
        $rev    = (float)($_POST['revenue'] ?? 0);

        // Validation: capacity check
        $veh = $db->query("SELECT * FROM vehicles WHERE id=$vid")->fetch_assoc();
        if (!$veh) redirect('trips.php', 'Invalid vehicle.', 'error');
        if ($cargo > $veh['max_capacity']) {
            redirect('trips.php', "❌ Cargo ($cargo kg) exceeds vehicle capacity ({$veh['max_capacity']} kg). Trip rejected.", 'error');
        }

        // License check
        $drv = $db->query("SELECT * FROM drivers WHERE id=$did")->fetch_assoc();
        if (!$drv) redirect('trips.php', 'Invalid driver.', 'error');
        if (strtotime($drv['license_expiry']) < time()) {
            redirect('trips.php', "❌ Driver license is expired. Cannot dispatch.", 'error');
        }

    $startOdo = $veh['odometer'];

$stmt = $db->prepare("INSERT INTO trips 
    (vehicle_id, driver_id, origin, destination, cargo_weight, cargo_description, status, start_odometer, revenue) 
    VALUES (?, ?, ?, ?, ?, ?, 'Draft', ?, ?)");

// i = integer, i = integer, s = string, s = string, d = double, s = string, d = double, d = double
$stmt->bind_param('iissdsdd', $vid, $did, $origin, $dest, $cargo, $desc, $startOdo, $rev);

$stmt->execute();

redirect('trips.php', 'Trip created as Draft.');
    }

    if ($act === 'dispatch') {
        $id = (int)$_POST['id'];
        $db->query("UPDATE trips SET status='Dispatched', dispatched_at=NOW() WHERE id=$id AND status='Draft'");
        // Set vehicle & driver to On Trip
        $trip = $db->query("SELECT * FROM trips WHERE id=$id")->fetch_assoc();
        if ($trip) {
            $db->query("UPDATE vehicles SET status='On Trip' WHERE id={$trip['vehicle_id']}");
            $db->query("UPDATE drivers SET status='On Duty' WHERE id={$trip['driver_id']}");
        }
        redirect('trips.php', 'Trip dispatched!');
    }

    if ($act === 'complete') {
        $id    = (int)$_POST['id'];
        $endOdo = (float)($_POST['end_odometer'] ?? 0);
        $trip  = $db->query("SELECT * FROM trips WHERE id=$id")->fetch_assoc();
        $dist  = max(0, $endOdo - $trip['start_odometer']);

        $db->query("UPDATE trips SET status='Completed', completed_at=NOW(), end_odometer=$endOdo, distance_km=$dist WHERE id=$id");
        $db->query("UPDATE vehicles SET status='Available', odometer=$endOdo WHERE id={$trip['vehicle_id']}");
        $db->query("UPDATE drivers SET status='Off Duty', trips_completed=trips_completed+1, trips_total=trips_total+1 WHERE id={$trip['driver_id']}");
        // Also increment total for dispatched (if not already)
        redirect('trips.php', '✅ Trip completed successfully!');
    }

    if ($act === 'cancel') {
        $id   = (int)$_POST['id'];
        $trip = $db->query("SELECT * FROM trips WHERE id=$id")->fetch_assoc();
        $db->query("UPDATE trips SET status='Cancelled' WHERE id=$id");
        if ($trip && $trip['status'] === 'Dispatched') {
            $db->query("UPDATE vehicles SET status='Available' WHERE id={$trip['vehicle_id']}");
            $db->query("UPDATE drivers SET status='Off Duty', trips_total=trips_total+1 WHERE id={$trip['driver_id']}");
        }
        redirect('trips.php', 'Trip cancelled.');
    }
}

// Available resources for new trip
$availVehicles = $db->query("SELECT * FROM vehicles WHERE status='Available' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$availDrivers  = $db->query("SELECT * FROM drivers WHERE status IN ('Off Duty') AND license_expiry >= CURDATE() ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Filters
$filterStatus = $_GET['status'] ?? '';
$where = '1=1';
if ($filterStatus) $where .= " AND t.status='".$db->real_escape_string($filterStatus)."'";

$trips = $db->query("
    SELECT t.*, v.name vname, v.max_capacity, d.name dname
    FROM trips t
    JOIN vehicles v ON t.vehicle_id=v.id
    JOIN drivers d ON t.driver_id=d.id
    WHERE $where
    ORDER BY t.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$openNew = isset($_GET['new']);
include 'includes/layout.php';
?>

<div class="page-header">
  <div><h1>Trip Dispatcher</h1><p>Create and manage cargo trips</p></div>
  <button class="btn btn-primary" onclick="openModal('tripModal')">+ New Trip</button>
</div>

<!-- Status filter tabs -->
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;flex-wrap:wrap;">
  <?php foreach ([''=>'All', 'Draft'=>'Draft', 'Dispatched'=>'Dispatched', 'Completed'=>'Completed', 'Cancelled'=>'Cancelled'] as $v=>$l): ?>
    <a href="?status=<?= $v ?>" class="btn <?= $filterStatus===$v?'btn-navy':'btn-ghost' ?> btn-sm"><?= $l ?></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div style="overflow-x:auto;">
    <table class="data-table">
      <thead>
        <tr><th>#</th><th>Vehicle</th><th>Driver</th><th>Route</th><th>Cargo (kg)</th><th>Revenue</th><th>Status</th><th>Date</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($trips as $t):
          $sc = strtolower(str_replace(' ','-',$t['status']));
          $utilPct = min(100, round($t['cargo_weight']/$t['max_capacity']*100));
        ?>
        <tr>
          <td class="text-muted">#<?= $t['id'] ?></td>
          <td><strong><?= e($t['vname']) ?></strong></td>
          <td><?= e($t['dname']) ?></td>
          <td>
            <div style="font-size:.875rem;"><?= e($t['origin']) ?></div>
            <div class="text-sm text-muted">→ <?= e($t['destination']) ?></div>
          </td>
          <td>
            <div><?= number_format($t['cargo_weight']) ?></div>
            <div class="progress-bar" style="width:70px;margin-top:3px;">
              <div class="fill <?= $utilPct>90?'fill-red':'fill-teal' ?>" style="width:<?= $utilPct ?>%"></div>
            </div>
          </td>
          <td><?= $t['revenue'] > 0 ? '$'.number_format($t['revenue'],2) : '—' ?></td>
          <td><span class="pill pill-<?= $sc ?>"><?= e($t['status']) ?></span></td>
          <td class="text-muted"><?= date('d M Y', strtotime($t['created_at'])) ?></td>
          <td>
            <div class="actions">
              <?php if ($t['status'] === 'Draft'): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="act" value="dispatch">
                  <input type="hidden" name="id" value="<?= $t['id'] ?>">
                  <button class="btn btn-primary btn-sm">🚀 Dispatch</button>
                </form>
                <form method="POST" style="display:inline;" onsubmit="return confirmDelete('Cancel this trip?')">
                  <input type="hidden" name="act" value="cancel">
                  <input type="hidden" name="id" value="<?= $t['id'] ?>">
                  <button class="btn btn-ghost btn-sm">✕ Cancel</button>
                </form>
              <?php elseif ($t['status'] === 'Dispatched'): ?>
                <button class="btn btn-primary btn-sm" onclick="openComplete(<?= $t['id'] ?>, <?= $t['start_odometer'] ?>)">✅ Complete</button>
                <form method="POST" style="display:inline;" onsubmit="return confirmDelete('Cancel this trip?')">
                  <input type="hidden" name="act" value="cancel">
                  <input type="hidden" name="id" value="<?= $t['id'] ?>">
                  <button class="btn btn-ghost btn-sm">✕ Cancel</button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; if (!$trips): ?>
        <tr><td colspan="9"><div class="empty-state"><div class="icon">📦</div><h4>No trips found</h4></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- New Trip Modal -->
<div class="modal-overlay" id="tripModal">
  <div class="modal" style="max-width:580px;">
    <div class="modal-header">
      <h3>Create New Trip</h3>
      <button class="modal-close" onclick="closeModal('tripModal')">×</button>
    </div>
    <form method="POST" id="tripForm">
      <div class="modal-body">
        <input type="hidden" name="act" value="create">
        <div class="form-row">
          <div class="form-group">
            <label>Vehicle *</label>
            <select class="form-control" name="vehicle_id" id="tripVehicle" required onchange="updateCapacity()">
              <option value="">— Select Vehicle —</option>
              <?php foreach ($availVehicles as $v): ?>
                <option value="<?= $v['id'] ?>" data-cap="<?= $v['max_capacity'] ?>"><?= e($v['name']) ?> (<?= number_format($v['max_capacity']) ?> kg)</option>
              <?php endforeach; ?>
            </select>
            <p class="form-hint" id="capHint"></p>
          </div>
          <div class="form-group">
            <label>Driver *</label>
            <select class="form-control" name="driver_id" required>
              <option value="">— Select Driver —</option>
              <?php foreach ($availDrivers as $d): ?>
                <option value="<?= $d['id'] ?>"><?= e($d['name']) ?> (<?= e($d['license_category'] ?: 'Any') ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Origin *</label>
            <input class="form-control" type="text" name="origin" placeholder="Pickup location" required>
          </div>
          <div class="form-group">
            <label>Destination *</label>
            <input class="form-control" type="text" name="destination" placeholder="Delivery location" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Cargo Weight (kg) *</label>
            <input class="form-control" type="number" name="cargo_weight" id="cargoWeight" step="0.01" min="1" required>
          </div>
          <div class="form-group">
            <label>Revenue ($)</label>
            <input class="form-control" type="number" name="revenue" step="0.01" min="0" value="0">
          </div>
        </div>
        <div class="form-group">
          <label>Cargo Description</label>
          <textarea class="form-control" name="cargo_description" rows="2" placeholder="Optional description of goods..."></textarea>
        </div>
        <div id="cargoAlert" class="alert alert-error" style="display:none;">
          ⚠️ Cargo weight exceeds vehicle capacity!
        </div>
        <?php if (!$availVehicles || !$availDrivers): ?>
        <div class="alert alert-warning">
          <?php if (!$availVehicles): ?>No vehicles available. <?php endif; ?>
          <?php if (!$availDrivers): ?>No licensed drivers available. <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('tripModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Trip</button>
      </div>
    </form>
  </div>
</div>

<!-- Complete Trip Modal -->
<div class="modal-overlay" id="completeModal">
  <div class="modal" style="max-width:400px;">
    <div class="modal-header">
      <h3>Complete Trip</h3>
      <button class="modal-close" onclick="closeModal('completeModal')">×</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="act" value="complete">
        <input type="hidden" name="id" id="completeId">
        <div class="form-group">
          <label>Starting Odometer (km)</label>
          <input class="form-control" type="text" id="startOdo" readonly style="background:var(--surface-2);">
        </div>
        <div class="form-group">
          <label>Final Odometer Reading (km) *</label>
          <input class="form-control" type="number" name="end_odometer" id="endOdo" step="0.01" required>
          <p class="form-hint">Enter the vehicle's odometer at trip end</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('completeModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Mark Completed</button>
      </div>
    </form>
  </div>
</div>

<script>
function updateCapacity() {
  const sel = document.getElementById('tripVehicle');
  const opt = sel.options[sel.selectedIndex];
  const cap = opt ? parseFloat(opt.dataset.cap || 0) : 0;
  document.getElementById('capHint').textContent = cap ? `Max capacity: ${cap.toLocaleString()} kg` : '';
  checkCargo(cap);
}
function checkCargo(cap) {
  const cargo = parseFloat(document.getElementById('cargoWeight').value || 0);
  const alert = document.getElementById('cargoAlert');
  alert.style.display = cap && cargo > cap ? 'flex' : 'none';
}
document.getElementById('cargoWeight').addEventListener('input', () => {
  const sel = document.getElementById('tripVehicle');
  const opt = sel.options[sel.selectedIndex];
  checkCargo(opt ? parseFloat(opt.dataset.cap || 0) : 0);
});
function openComplete(id, startOdo) {
  document.getElementById('completeId').value = id;
  document.getElementById('startOdo').value = startOdo;
  document.getElementById('endOdo').value = startOdo;
  document.getElementById('endOdo').min = startOdo;
  openModal('completeModal');
}
<?php if ($openNew): ?>window.onload = () => openModal('tripModal');<?php endif; ?>
</script>
<?php include 'includes/footer.php'; ?>
