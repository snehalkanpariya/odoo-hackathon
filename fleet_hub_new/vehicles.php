<?php
require_once 'includes/auth.php';
$pageTitle = 'Vehicle Registry';
$activeNav = 'vehicles.php';

$db = getDB();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'add' || $act === 'edit') {
        $name     = trim($_POST['name'] ?? '');
        $model    = trim($_POST['model'] ?? '');
        $type     = $_POST['type'] ?? 'Van';
        $plate    = strtoupper(trim($_POST['license_plate'] ?? ''));
        $cap      = (float)($_POST['max_capacity'] ?? 0);
        $odo      = (float)($_POST['odometer'] ?? 0);
        $region   = trim($_POST['region'] ?? '');
        $acq      = (float)($_POST['acquisition_cost'] ?? 0);
        $status   = $_POST['status'] ?? 'Available';

        if (!$name || !$plate || $cap <= 0) {
            redirect('vehicles.php', 'Name, License Plate, and Capacity are required.', 'error');
        }

       if ($act === 'add') {
    // Check if license plate already exists
    $stmt_check = $db->prepare("SELECT id FROM vehicles WHERE license_plate = ?");
    $stmt_check->bind_param("s", $plate);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        redirect('vehicles.php', "Error: License plate '$plate' already exists!", 'error');
    } else {
        $stmt = $db->prepare("INSERT INTO vehicles (name,model,type,license_plate,max_capacity,odometer,region,acquisition_cost,status) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('ssssdddss', $name,$model,$type,$plate,$cap,$odo,$region,$acq,$status);
    }
}
        if ($stmt->execute()) redirect('vehicles.php', $act==='add' ? 'Vehicle added!' : 'Vehicle updated!');
        else redirect('vehicles.php', 'Error: '.$db->error, 'error');
    }

    if ($act === 'delete') {
        $id = (int)$_POST['id'];
        $db->query("DELETE FROM vehicles WHERE id=$id");
        redirect('vehicles.php', 'Vehicle removed.');
    }

    if ($act === 'toggle_status') {
        $id = (int)$_POST['id'];
        $cur = $_POST['current'] ?? '';
        $new = $cur === 'Retired' ? 'Available' : 'Retired';
        $db->query("UPDATE vehicles SET status='$new' WHERE id=$id");
        redirect('vehicles.php', "Vehicle status set to $new.");
    }
}

// Filters
$where = '1=1';
$filterType   = $_GET['type'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');
if ($filterType)   $where .= " AND type='" . $db->real_escape_string($filterType) . "'";
if ($filterStatus) $where .= " AND status='" . $db->real_escape_string($filterStatus) . "'";
if ($search) $where .= " AND (name LIKE '%{$db->real_escape_string($search)}%' OR license_plate LIKE '%{$db->real_escape_string($search)}%')";

$vehicles = $db->query("SELECT * FROM vehicles WHERE $where ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

include 'includes/layout.php';
?>

<div class="page-header">
  <div><h1>Vehicle Registry</h1><p>Manage your fleet assets</p></div>
  <?php if (hasRole(['manager','dispatcher'])): ?>
  <button class="btn btn-primary" onclick="openModal('vehicleModal')">+ Add Vehicle</button>
  <?php endif; ?>
</div>

<!-- Filters -->
<form class="filter-bar" method="GET">
  <div class="search-input">
    <span class="icon">🔍</span>
    <input class="form-control" type="text" name="q" placeholder="Search name or plate..." value="<?= e($search) ?>">
  </div>
  <select class="form-control" name="type" onchange="this.form.submit()">
    <option value="">All Types</option>
    <?php foreach (['Truck','Van','Bike'] as $t): ?>
      <option value="<?= $t ?>" <?= $filterType===$t?'selected':'' ?>><?= $t ?></option>
    <?php endforeach; ?>
  </select>
  <select class="form-control" name="status" onchange="this.form.submit()">
    <option value="">All Status</option>
    <?php foreach (['Available','On Trip','In Shop','Retired'] as $s): ?>
      <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= $s ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-ghost btn-sm" type="submit">Filter</button>
  <?php if ($search || $filterType || $filterStatus): ?>
    <a href="vehicles.php" class="btn btn-ghost btn-sm">Clear</a>
  <?php endif; ?>
</form>

<div class="card">
  <div style="overflow-x:auto;">
    <table class="data-table">
      <thead>
        <tr>
          <th>Vehicle</th><th>Type</th><th>Plate</th><th>Capacity</th><th>Odometer</th><th>Region</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($vehicles as $v):
          $sc = strtolower(str_replace(' ','-',$v['status']));
        ?>
        <tr>
          <td>
            <div style="font-weight:600;"><?= e($v['name']) ?></div>
            <div class="text-sm text-muted"><?= e($v['model']) ?></div>
          </td>
          <td><span class="tag"><?= e($v['type']) ?></span></td>
          <td><code style="font-size:.8rem;background:var(--surface-2);padding:.2rem .5rem;border-radius:5px;"><?= e($v['license_plate']) ?></code></td>
          <td><?= number_format($v['max_capacity']) ?> kg</td>
          <td class="odo"><?= number_format($v['odometer']) ?> km</td>
          <td class="text-muted"><?= e($v['region'] ?: '—') ?></td>
          <td><span class="pill pill-<?= $sc ?>"><?= e($v['status']) ?></span></td>
          <td>
            <div class="actions">
              <?php if (hasRole(['manager','dispatcher'])): ?>
              <button class="btn btn-ghost btn-sm btn-icon" title="Edit"
                onclick='editVehicle(<?= json_encode($v) ?>)'>✏️</button>
              <form method="POST" style="display:inline;" onsubmit="return confirmDelete('Retire/restore this vehicle?')">
                <input type="hidden" name="act" value="toggle_status">
                <input type="hidden" name="id" value="<?= $v['id'] ?>">
                <input type="hidden" name="current" value="<?= e($v['status']) ?>">
                <button class="btn btn-ghost btn-sm btn-icon" title="Toggle Status">
                  <?= $v['status']==='Retired' ? '♻️' : '🚫' ?>
                </button>
              </form>
              <?php if (hasRole('manager')): ?>
              <form method="POST" style="display:inline;" onsubmit="return confirmDelete('Delete this vehicle permanently?')">
                <input type="hidden" name="act" value="delete">
                <input type="hidden" name="id" value="<?= $v['id'] ?>">
                <button class="btn btn-danger btn-sm btn-icon" title="Delete">🗑️</button>
              </form>
              <?php endif; ?>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; if (!$vehicles): ?>
        <tr><td colspan="8">
          <div class="empty-state"><div class="icon">🚛</div><h4>No vehicles found</h4><p>Add your first vehicle to get started.</p></div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal-overlay" id="vehicleModal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modalTitle">Add Vehicle</h3>
      <button class="modal-close" onclick="closeModal('vehicleModal')">×</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="act" id="formAct" value="add">
        <input type="hidden" name="id" id="formId">
        <div class="form-row">
          <div class="form-group">
            <label>Vehicle Name *</label>
            <input class="form-control" type="text" name="name" id="fName" placeholder="e.g. Van-05" required>
          </div>
          <div class="form-group">
            <label>Model</label>
            <input class="form-control" type="text" name="model" id="fModel" placeholder="e.g. Toyota Hiace">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Type</label>
            <select class="form-control" name="type" id="fType">
              <option>Truck</option><option>Van</option><option>Bike</option>
            </select>
          </div>
          <div class="form-group">
            <label>License Plate *</label>
            <input class="form-control" type="text" name="license_plate" id="fPlate" placeholder="ABC-1234" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Max Capacity (kg) *</label>
            <input class="form-control" type="number" name="max_capacity" id="fCap" step="0.01" min="1" required>
          </div>
          <div class="form-group">
            <label>Odometer (km)</label>
            <input class="form-control" type="number" name="odometer" id="fOdo" step="0.01" min="0" value="0">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Region</label>
            <input class="form-control" type="text" name="region" id="fRegion" placeholder="e.g. North Zone">
          </div>
          <div class="form-group">
            <label>Acquisition Cost ($)</label>
            <input class="form-control" type="number" name="acquisition_cost" id="fAcq" step="0.01" min="0" value="0">
          </div>
        </div>
        <div class="form-group" id="statusGroup">
          <label>Status</label>
          <select class="form-control" name="status" id="fStatus">
            <option>Available</option><option>In Shop</option><option>Retired</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('vehicleModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="submitBtn">Add Vehicle</button>
      </div>
    </form>
  </div>
</div>

<script>
function editVehicle(v) {
  document.getElementById('modalTitle').textContent = 'Edit Vehicle';
  document.getElementById('formAct').value = 'edit';
  document.getElementById('submitBtn').textContent = 'Save Changes';
  document.getElementById('formId').value = v.id;
  document.getElementById('fName').value = v.name;
  document.getElementById('fModel').value = v.model || '';
  document.getElementById('fType').value = v.type;
  document.getElementById('fPlate').value = v.license_plate;
  document.getElementById('fCap').value = v.max_capacity;
  document.getElementById('fOdo').value = v.odometer;
  document.getElementById('fRegion').value = v.region || '';
  document.getElementById('fAcq').value = v.acquisition_cost;
  document.getElementById('fStatus').value = v.status;
  openModal('vehicleModal');
}
// Reset modal on close
document.querySelector('#vehicleModal .modal-close').addEventListener('click', () => {
  document.getElementById('vehicleModal').querySelector('form').reset();
  document.getElementById('modalTitle').textContent = 'Add Vehicle';
  document.getElementById('formAct').value = 'add';
  document.getElementById('formId').value = '';
  document.getElementById('submitBtn').textContent = 'Add Vehicle';
});
</script>
<?php include 'includes/footer.php'; ?>
