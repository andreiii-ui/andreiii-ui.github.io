let selectionMode = false;

function toggleSelectionMode() {
  selectionMode = document.getElementById('selectionModeCheckbox').checked;
  document.querySelectorAll('.select-checkbox').forEach(cb => {
    cb.style.display = selectionMode ? 'inline-block' : 'none';
    cb.checked = false;
  });
  document.getElementById('selectAllCheckbox').style.display = selectionMode ? 'inline-block' : 'none';

}

function selectAll() {
  const checked = document.getElementById('selectAllCheckbox').checked;
  document.querySelectorAll('.select-checkbox').forEach(cb => cb.checked = checked);
}

// Open diff preview modal when clicking restore
document.querySelectorAll('.restore-btn').forEach(btn => {
  btn.addEventListener('click', function (e) {
    e.preventDefault();
    const url = this.getAttribute('data-url');
    const idMatch = url.match(/id=(\d+)/);
    if (!idMatch) return alert('Invalid restore id');
    const id = idMatch[1];
    openPreviewModal(id, url);
  });
});

// Modal helpers and improved diff UI
function normalize(v) {
  if (v === null || v === undefined) return '';
  return String(v).replace(/\s+/g, ' ').trim();
}

function escapeHtml(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function openPreviewModal(id, restoreUrl) {
  const modal = document.getElementById('previewModal');
  const content = document.getElementById('previewTableBody');
  const changedCountEl = document.getElementById('changedCount');
  const toggle = document.getElementById('onlyChangesToggle');
  content.innerHTML = '<tr><td colspan="3">Loading...</td></tr>';
  changedCountEl.innerText = '';
  modal.style.display = 'block';

  fetch('restore_preview.php?id=' + encodeURIComponent(id))
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        content.innerHTML = '<tr><td colspan="3">' + escapeHtml(data.error) + '</td></tr>';
        return;
      }
      const activity = data.activity || {};
      const record = data.record || {};
      const fields = ['record_id', 'access_code', 'library', 'location_code', 'author', 'department', 'title', 'type', 'record_status', 'retention_period', 'description', 'document_number', 'supplier', 'amount_due', 'notes_keywords', 'addressee', 'accountable_officer', 'sender', 'source_funds', 'property_number', 'total_cost', 'attachment_name', 'status', 'date_created'];

      // Build rows and collect changed fields
      const rows = [];
      let changedCount = 0;
      fields.forEach(f => {
        const aVal = activity[f] ?? '';
        const rVal = record[f] ?? '';
        const na = normalize(aVal);
        const nr = normalize(rVal);
        const changed = na !== nr;
        if (changed) changedCount++;
        rows.push({ field: f, aVal: aVal, rVal: rVal, changed: changed });
      });

      // Sort so changed fields appear first
      rows.sort((x, y) => (y.changed === x.changed) ? 0 : (x.changed ? -1 : 1));

      // Render table
      content.innerHTML = '';
      rows.forEach(r => {
        const tr = document.createElement('tr');
        tr.className = r.changed ? 'changed' : '';
        const fieldTd = document.createElement('td');
        fieldTd.innerText = r.field;
        const aTd = document.createElement('td');
        const ra = escapeHtml(r.aVal == null ? '' : r.aVal);
        const rr = escapeHtml(r.rVal == null ? '' : r.rVal);
        // If this is the attachment column, render a link using the stored path but display the name
        if (r.field === 'attachment_name') {
          const href = activity && activity['attachment'] ? activity['attachment'] : '';
          const display = r.aVal || (href ? href.split('/').pop() : '');
          if (href) aTd.innerHTML = '<a href="' + escapeHtml(href) + '" target="_blank">' + escapeHtml(display) + '</a>';
          else aTd.innerText = display;
        } else {
          aTd.innerText = r.aVal;
        }
        const rTd = document.createElement('td');
        if (r.field === 'attachment_name') {
          const href = record && record['attachment'] ? record['attachment'] : '';
          const display = r.rVal || (href ? href.split('/').pop() : '');
          if (href) rTd.innerHTML = '<a href="' + escapeHtml(href) + '" target="_blank">' + escapeHtml(display) + '</a>';
          else rTd.innerText = display;
        } else {
          rTd.innerText = r.rVal;
        }
        tr.appendChild(fieldTd);
        tr.appendChild(aTd);
        tr.appendChild(rTd);
        content.appendChild(tr);
      });

      changedCountEl.innerText = changedCount + ' field(s) changed';
      // store restore url on confirm button
      document.getElementById('confirmRestoreBtn').setAttribute('data-url', restoreUrl);

      // Toggle logic
      toggle.checked = false;
      toggle.onchange = () => {
        const only = toggle.checked;
        document.querySelectorAll('#previewTableBody tr').forEach(tr => {
          if (!tr.classList.contains('changed')) {
            tr.style.display = only ? 'none' : '';
          }
        });
      };
    })
    .catch(err => {
      content.innerHTML = '<tr><td colspan="3">Failed to load preview</td></tr>';
    });
}

// Close modal on close button, overlay click, or ESC
function closeModal() { document.getElementById('previewModal').style.display = 'none'; }
document.getElementById('closePreview').addEventListener('click', closeModal);
document.getElementById('previewModal').addEventListener('click', function (e) { if (e.target.id === 'previewModal') closeModal(); });
document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });

// Perform restore when user confirms
document.getElementById('confirmRestoreBtn').addEventListener('click', function () {
  const url = this.getAttribute('data-url');
  if (!url) return alert('No restore URL found');
  if (confirm('Proceed with restore? This will modify records.')) {
    window.location.href = url;
  }
});