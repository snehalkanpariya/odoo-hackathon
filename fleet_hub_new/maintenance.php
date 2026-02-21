<?php
require_once 'includes/auth.php';
$pageTitle = 'Maintenance Logs';
$activeNav = 'maintenance.php';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'add' || $act === 'edit') {
        $vid   = (int)$_POST['vehicle_id'];
        $stype = trim($_POST['service_type'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $cost  = (float)($_POST['cost'] ?? 0);
        $tech  = trim($_POST['technician'] ?? '');
        $sdate = $_POST['service_date'] ?? '';
        $status= $_POST['status'] ?? 'In Progress';

        if (!$vid || !$stype || !$sdate) redirect('maintenance.php', 'Vehicle, service type and date are required.', 'error');

        if ($act === 'add') {
            $stmt = $db->prepare("INSERT INTO maintenance_logs (vehicle_id,service_type,description,cost,technician,service_date,status) VALUES (?,?,?,?,?,?,?)");
           // fix: 7 types needed
           
            $stmt->bind_param('issdsss', $vid,$stype,$desc,$cost,$tech,$sdate,$status);
            $stmt->execute();
            // Auto: set vehicle to In Shop
            $db->query("UPDATE vehicles SET status='In Shop' WHERE id=$vid AND status='Available'");
            redirect('maintenance.php', '🔧 Maintenance log added. Vehicle status set to In Shop.');
        } else {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("UPDATE maintenance_logs SET vehicle_id=?,service_type=?,description=?,cost=?,technician=?,service_date=?,status=? WHERE id=?");
            $stmt->bind_param('issdsssi', $vid,$stype,$desc,$cost,$tech,$sdate,$status,$id);
            $stmt->execute();
            // If completed, restore vehicle
            if ($status === 'Completed') {
                $log = $db->query("UPDATE maintenance_logs SET completed_date=CURDATE() WHERE id=$id AND completed_date IS NULL");
                $db->query("UPDATE vehicles SET status='Available' WHERE id=$vid AND status='In Shop'");
            }
            redirect('maintenance.php', 'Log updated.');
        }
    }

    if ($act === 'delete') {
        $id = (int)$_POST['id'];
        $log = $db->query("SELECT * FROM maintenance_logs WHERE id=$id")->fetch_assoc();
        $db->query("DELETE FROM maintenance_logs WHERE id=$id");
        // If no more active logs for vehicle, restore it
        if ($log) {
            $active = $db->query("SELECT COUNT(*) c FROM maintenance_logs WHERE vehicle_id={$log['vehicle_id']} AND status != 'Completed'")->fetch_assoc()['c'];
            if ($active == 0) $db->query("UPDATE vehicles SET status='Available' WHERE id={$log['vehicle_id']} AND status='In Shop'");
        }
        redirect('maintenance.php', 'Log deleted.');
    }
}

$vehicles = $db->query("SELECT id, name, license_plate FROM vehicles ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$filterStatus = $_GET['status'] ?? '';
$where = '1=1';
if ($filterStatus) $where .= " AND m.status='".$db->real_escape_string($filterStatus)."'";

$logs = $db->query("
    SELECT m.*, v.name vname, v.license_plate
    FROM maintenance_logs m
    JOIN vehicles v ON m.vehicle_id=v.id
    WHERE $where
    ORDER BY m.service_date DESC
")->fetch_all(MYSQLI_ASSOC);

// Totals
$totals = $db->query("SELECT COUNT(*) c, SUM(cost) total FROM maintenance_logs WHERE status!='Completed'")->fetch_assoc();

include 'includes/layout.php';
?>

<div class="page-header">
  <div><h1>Maintenance & Service Logs</h1><p>Preventative and reactive vehicle health tracking</p></div>
  <button class="btn btn-primary" onclick="openModal('maintModal')">+ Log Service</button>
</div>

<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);max-width:600px;margin-bottom:1.5rem;">
  <div class="kpi-card">
    <div class="kpi-header"><span class="kpi-label">Active</span><div class="kpi-icon orange">🔧</div></div>
    <div class="kpi-value"><?= $totals['c'] ?></div>
    <div class="kpi-sub">Open service logs</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-header"><span class="kpi-label">In Shop</span><div class="kpi-icon red">🚫</div></div>
    <div class="kpi-value"><?= $db->query("SELECT COUNT(*) c FROM vehicles WHERE status='In Shop'")->fetch_assoc()['c'] ?></div>
    <div class="kpi-sub">Vehicles unavailable</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-header"><span class="kpi-label">Open Cost</span><div class="kpi-icon navy">💰</div></div>
    <div class="kpi-value">$<?= number_format($totals['total'] ?? 0) ?></div>
    <div class="kpi-sub">Maintenance spend</div>
  </div>
</div>

<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
  <?php foreach ([''=>'All', 'Scheduled'=>'Scheduled', 'In Progress'=>'In Progress', 'Completed'=>'Completed'] as $v=>$l): ?>
    <a href="?status=<?= urlencode($v) ?>" class="btn <?= $filterStatus===$v?'btn-navy':'btn-ghost' ?> btn-sm"><?= $l ?></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div style="overflow-x:auto;">
    <table class="data-table">
      <thead>
        <tr><th>Vehicle</th><th>Service Type</th><th>Technician</th><th>Cost</th><th>Date</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $m):
          $sc = strtolower(str_replace(' ','-',$m['status']));
        ?>
        <tr>
          <td>
            <div style="font-weight:600;"><?= e($m['vname']) ?></div>
            <code style="font-size:.78rem;"><?= e($m['license_plate']) ?></code>
          </td>
          <td>
            <div style="font-weight:600;"><?= e($m['service_type']) ?></div>
            <?php if ($m['description']): ?>
              <div class="text-sm text-muted"><?= e(substr($m['description'],0,50)) ?>...</div>
            <?php endif; ?>
          </td>
          <td><?= e($m['technician'] ?: '—') ?></td>
          <td style="font-weight:600;">$<?= number_format($m['cost'],2) ?></td>
          <td>
            <div class="text-sm"><?= date('d M Y', strtotime($m['service_date'])) ?></div>
            <?php if ($m['completed_date']): ?>
              <div class="text-sm text-muted">Done: <?= date('d M Y', strtotime($m['completed_date'])) ?></div>
            <?php endif; ?>
          </td>
          <td><span class="pill pill-<?= $sc ?>"><?= e($m['status']) ?></span></td>
          <td>
            <div class="actions">
              <button class="btn btn-ghost btn-sm btn-icon" onclick='editMaint(<?= json_encode($m) ?>)'>✏️</button>
              <form method="POST" style="display:inline;" onsubmit="return confirmDelete()">
                <input type="hidden" name="act" value="delete">
                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                <button class="btn btn-danger btn-sm btn-icon">🗑️</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; if (!$logs): ?>
        <tr><td colspan="7"><div class="empty-state"><div class="icon">🔧</div><h4>No maintenance logs found</h4></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="maintModal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="mModalTitle">Log Service</h3>
      <button class="modal-close" onclick="closeModal('maintModal')">×</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="act" id="mFormAct" value="add">
        <input type="hidden" name="id" id="mFormId">
        <div class="form-group">
          <label>Vehicle *</label>
          <select class="form-control" name="vehicle_id" id="mVehicle" required>
            <option value="">— Select Vehicle —</option>
            <?php foreach ($vehicles as $v): ?>
              <option value="<?= $v['id'] ?>"><?= e($v['name']) ?> (<?= e($v['license_plate']) ?>)</option>
            <?php endforeach; ?>
          </select>
          <p class="form-hint">Adding a log will set this vehicle's status to "In Shop"</p>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Service Type *</label>
            <input class="form-control" type="text" name="service_type" id="mType" placeholder="e.g. Oil Change" required>
          </div>
          <div class="form-group">
            <label>Technician</label>
            <input class="form-control" type="text" name="technician" id="mTech" placeholder="Name">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Cost ($)</label>
            <input class="form-control" type="number" name="cost" id="mCost" step="0.01" min="0" value="0">
          </div>
          <div class="form-group">
            <label>Service Date *</label>
            <input class="form-control" type="date" name="service_date" id="mDate" required value="<?= date('Y-m-d') ?>">
          </div>
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea class="form-control" name="description" id="mDesc" rows="2"></textarea>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select class="form-control" name="status" id="mStatus">
            <option>Scheduled</option><option selected>In Progress</option><option>Completed</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('maintModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="mSubmitBtn">Log Service</button>
      </div>
    </form>
  </div>
</div>

<script>
function editMaint(m) {
  document.getElementById('mModalTitle').textContent = 'Edit Service Log';
  document.getElementById('mFormAct').value = 'edit';
  document.getElementById('mSubmitBtn').textContent = 'Save Changes';
  document.getElementById('mFormId').value = m.id;
  document.getElementById('mVehicle').value = m.vehicle_id;
  document.getElementById('mType').value = m.service_type;
  document.getElementById('mTech').value = m.technician || '';
  document.getElementById('mCost').value = m.cost;
  document.getElementById('mDate').value = m.service_date;
  document.getElementById('mDesc').value = m.description || '';
  document.getElementById('mStatus').value = m.status;
  openModal('maintModal');
}
</script>
<?php include 'includes/footer.php'; ?>
