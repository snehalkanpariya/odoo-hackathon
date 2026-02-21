<?php
require_once 'includes/auth.php';
$pageTitle = 'Driver Profiles';
$activeNav = 'drivers.php';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'add' || $act === 'edit') {
        $name    = trim($_POST['name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $licNum  = trim($_POST['license_number'] ?? '');
        $licCat  = $_POST['license_category'] ?? '';
        $licExp  = $_POST['license_expiry'] ?? '';
        $status  = $_POST['status'] ?? 'Off Duty';
        $score   = (float)($_POST['safety_score'] ?? 100);

        if (!$name || !$licNum || !$licExp) redirect('drivers.php', 'Name, License Number and Expiry are required.', 'error');

        if ($act === 'add') {
            $stmt = $db->prepare("INSERT INTO drivers (name,email,phone,license_number,license_category,license_expiry,status,safety_score) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param('sssssssd', $name,$email,$phone,$licNum,$licCat,$licExp,$status,$score);
        } else {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("UPDATE drivers SET name=?,email=?,phone=?,license_number=?,license_category=?,license_expiry=?,status=?,safety_score=? WHERE id=?");
            $stmt->bind_param('sssssssdi', $name,$email,$phone,$licNum,$licCat,$licExp,$status,$score,$id);
        }
        if ($stmt->execute()) redirect('drivers.php', $act==='add' ? 'Driver added!' : 'Driver updated!');
        else redirect('drivers.php', 'Error: '.$db->error, 'error');
    }

    if ($act === 'delete') {
        $id = (int)$_POST['id'];
        $db->query("DELETE FROM drivers WHERE id=$id");
        redirect('drivers.php', 'Driver removed.');
    }

    if ($act === 'status') {
        $id = (int)$_POST['id'];
        $s  = $_POST['status'] ?? 'Off Duty';
        $db->query("UPDATE drivers SET status='".($db->real_escape_string($s))."' WHERE id=$id");
        redirect('drivers.php', 'Driver status updated.');
    }
}

$filterStatus = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');
$where = '1=1';
if ($filterStatus) $where .= " AND status='".$db->real_escape_string($filterStatus)."'";
if ($search) $where .= " AND (name LIKE '%{$db->real_escape_string($search)}%' OR license_number LIKE '%{$db->real_escape_string($search)}%')";

$drivers = $db->query("SELECT *, DATEDIFF(license_expiry, CURDATE()) days_left FROM drivers WHERE $where ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

include 'includes/layout.php';
?>

<div class="page-header">
  <div><h1>Driver Profiles</h1><p>Compliance & performance management</p></div>
  <button class="btn btn-primary" onclick="openModal('driverModal')">+ Add Driver</button>
</div>

<form class="filter-bar" method="GET">
  <div class="search-input">
    <span class="icon">🔍</span>
    <input class="form-control" type="text" name="q" placeholder="Search driver or license..." value="<?= e($search) ?>">
  </div>
  <select class="form-control" name="status" onchange="this.form.submit()">
    <option value="">All Status</option>
    <?php foreach (['On Duty','Off Duty','Suspended'] as $s): ?>
      <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= $s ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-ghost btn-sm" type="submit">Filter</button>
  <?php if ($search || $filterStatus): ?><a href="drivers.php" class="btn btn-ghost btn-sm">Clear</a><?php endif; ?>
</form>

<div class="card">
  <div style="overflow-x:auto;">
    <table class="data-table">
      <thead>
        <tr><th>Driver</th><th>License</th><th>Category</th><th>Expiry</th><th>Trips</th><th>Safety Score</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($drivers as $d):
          $sc = strtolower(str_replace(' ','-',$d['status']));
          $expired = $d['days_left'] < 0;
          $expiring = $d['days_left'] >= 0 && $d['days_left'] <= 30;
          $rate = $d['trips_total'] > 0 ? round($d['trips_completed']/$d['trips_total']*100) : 100;
          $scoreColor = $d['safety_score'] >= 80 ? 'fill-green' : ($d['safety_score'] >= 60 ? 'fill-orange' : 'fill-red');
        ?>
        <tr>
          <td>
            <div style="font-weight:600;"><?= e($d['name']) ?></div>
            <div class="text-sm text-muted"><?= e($d['email'] ?: '—') ?></div>
          </td>
          <td><code style="font-size:.8rem;"><?= e($d['license_number']) ?></code></td>
          <td><span class="tag"><?= e($d['license_category'] ?: 'Any') ?></span></td>
          <td>
            <div class="<?= $expired ? 'text-sm' : 'text-sm' ?>" style="color:<?= $expired?'var(--danger)':($expiring?'var(--warning)':'var(--text)') ?>">
              <?= date('d M Y', strtotime($d['license_expiry'])) ?>
            </div>
            <?php if ($expired): ?><div class="text-sm" style="color:var(--danger);font-weight:600;">⚠️ EXPIRED</div>
            <?php elseif ($expiring): ?><div class="text-sm" style="color:var(--warning);"><?= $d['days_left'] ?> days left</div><?php endif; ?>
          </td>
          <td>
            <div class="text-sm"><?= $d['trips_completed'] ?>/<?= $d['trips_total'] ?></div>
            <div class="progress-bar" style="margin-top:3px;width:70px;"><div class="fill fill-teal" style="width:<?= $rate ?>%"></div></div>
          </td>
          <td>
            <div style="font-weight:700;font-size:.95rem;color:<?= $d['safety_score']>=80?'var(--success)':($d['safety_score']>=60?'var(--warning)':'var(--danger)') ?>">
              <?= $d['safety_score'] ?>
            </div>
            <div class="progress-bar" style="width:70px;"><div class="fill <?= $scoreColor ?>" style="width:<?= $d['safety_score'] ?>%"></div></div>
          </td>
          <td>
            <select class="form-control" style="width:auto;font-size:.78rem;padding:.3rem .6rem;" onchange="updateStatus(<?= $d['id'] ?>, this.value)">
              <?php foreach (['On Duty','Off Duty','Suspended'] as $s): ?>
                <option value="<?= $s ?>" <?= $d['status']===$s?'selected':'' ?>><?= $s ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td>
            <div class="actions">
              <button class="btn btn-ghost btn-sm btn-icon" onclick='editDriver(<?= json_encode($d) ?>)'>✏️</button>
              <form method="POST" style="display:inline;" onsubmit="return confirmDelete()">
                <input type="hidden" name="act" value="delete">
                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                <button class="btn btn-danger btn-sm btn-icon">🗑️</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; if (!$drivers): ?>
        <tr><td colspan="8"><div class="empty-state"><div class="icon">👤</div><h4>No drivers found</h4></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Driver Modal -->
<div class="modal-overlay" id="driverModal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="dModalTitle">Add Driver</h3>
      <button class="modal-close" onclick="closeModal('driverModal')">×</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="act" id="dFormAct" value="add">
        <input type="hidden" name="id" id="dFormId">
        <div class="form-row">
          <div class="form-group">
            <label>Full Name *</label>
            <input class="form-control" type="text" name="name" id="dfName" required>
          </div>
          <div class="form-group">
            <label>Email</label>
            <input class="form-control" type="email" name="email" id="dfEmail">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Phone</label>
            <input class="form-control" type="text" name="phone" id="dfPhone">
          </div>
          <div class="form-group">
            <label>License Category</label>
            <select class="form-control" name="license_category" id="dfCat">
              <option value="">Any</option>
              <option value="Truck">Truck</option>
              <option value="Van">Van</option>
              <option value="Bike">Bike</option>
              <option value="Heavy">Heavy</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>License Number *</label>
            <input class="form-control" type="text" name="license_number" id="dfLic" required>
          </div>
          <div class="form-group">
            <label>License Expiry *</label>
            <input class="form-control" type="date" name="license_expiry" id="dfExp" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Status</label>
            <select class="form-control" name="status" id="dfStatus">
              <option>On Duty</option><option>Off Duty</option><option>Suspended</option>
            </select>
          </div>
          <div class="form-group">
            <label>Safety Score (0-100)</label>
            <input class="form-control" type="number" name="safety_score" id="dfScore" min="0" max="100" step="0.01" value="100">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('driverModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="dSubmitBtn">Add Driver</button>
      </div>
    </form>
  </div>
</div>

<!-- Hidden form for quick status update -->
<form method="POST" id="statusForm" style="display:none;">
  <input type="hidden" name="act" value="status">
  <input type="hidden" name="id" id="statusId">
  <input type="hidden" name="status" id="statusVal">
</form>

<script>
function updateStatus(id, val) {
  document.getElementById('statusId').value = id;
  document.getElementById('statusVal').value = val;
  document.getElementById('statusForm').submit();
}
function editDriver(d) {
  document.getElementById('dModalTitle').textContent = 'Edit Driver';
  document.getElementById('dFormAct').value = 'edit';
  document.getElementById('dSubmitBtn').textContent = 'Save Changes';
  document.getElementById('dFormId').value = d.id;
  document.getElementById('dfName').value = d.name;
  document.getElementById('dfEmail').value = d.email || '';
  document.getElementById('dfPhone').value = d.phone || '';
  document.getElementById('dfLic').value = d.license_number;
  document.getElementById('dfCat').value = d.license_category || '';
  document.getElementById('dfExp').value = d.license_expiry;
  document.getElementById('dfStatus').value = d.status;
  document.getElementById('dfScore').value = d.safety_score;
  openModal('driverModal');
}
</script>
<?php include 'includes/footer.php'; ?>
