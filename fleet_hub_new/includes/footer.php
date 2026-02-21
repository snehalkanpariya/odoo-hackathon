  </main>
</div><!-- main-content -->
</div><!-- app-layout -->

<div id="toast-container"></div>

<script>
// Mobile sidebar
const mBtn = document.getElementById('menuBtn');
if (window.innerWidth <= 900 && mBtn) mBtn.style.display = 'flex';
window.addEventListener('resize', () => {
  if (mBtn) mBtn.style.display = window.innerWidth <= 900 ? 'flex' : 'none';
});

// Toast
function showToast(msg, type='success') {
  const t = document.createElement('div');
  t.className = `toast toast-${type}`;
  t.textContent = msg;
  document.getElementById('toast-container').appendChild(t);
  setTimeout(() => t.remove(), 3500);
}

// Modal helpers
function openModal(id) {
  document.getElementById(id).classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
  document.body.style.overflow = '';
}
document.querySelectorAll('.modal-overlay').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) closeModal(m.id); });
});

// Confirm delete
function confirmDelete(msg) { return confirm(msg || 'Are you sure you want to delete this?'); }

// Status pill helper
function pillClass(status) {
  return 'pill pill-' + status.toLowerCase().replace(/\s+/g, '-');
}
</script>
</body>
</html>
