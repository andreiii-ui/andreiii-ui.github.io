<?php
include 'connect.php';
include_once 'rolePermissionHandler.php';

// Select deleted entries from document_history (new canonical trash table)
// Discover columns for document_history so we can render a dynamic table
$cols_stmt = $conn->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION");
$tableName = 'document_history';
$cols_stmt->bind_param('s', $tableName);
$cols_stmt->execute();
$cols_res = $cols_stmt->get_result();
$columns = [];
while ($c = $cols_res->fetch_assoc()) {
  $columns[] = $c['COLUMN_NAME'];
}
$cols_stmt->close();

if (empty($columns)) {
  $result = false;
} else {
  $result = $conn->query("SELECT * FROM document_history ORDER BY timestamp DESC");
}

// Ensure user is logged in
if (!isset($_SESSION['username'])) {
  header('Location: LogInpage.php');
  exit;
}

// Define role for menu and permission checks
$role = strtolower($_SESSION['role'] ?? 'viewer'); // default to 'viewer' if not set

// Only Admins can view certain pages (if needed, adjust below)
if ($role !== 'admin' && $role !== 'encoder') {
  header('Location: index.php'); // redirect viewers
  exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Trash Bin - Deleted Records</title>
  <link rel="stylesheet" href="trash_bin_style.css">
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>

<body>
  <nav class="menu-bar">
    <div class="logo">
      <a href="index.php">
        <img src="z_Images/logoNavUi.png" alt="Library Logo" style="height:54px; vertical-align:middle;">
      </a>
    </div>
    <ul class="menu-items">
      <li class="dropdown">
        <a href="javascript:void(0)"><i class='bx bx-menu-alt-right'></i></a>
        <ul class="dropdown-menu">
          <li><a href="index.php">Home</a></li>
          <li><a href="Searchpage.php">Search</a></li>

          <?php if ($role === 'admin'): ?>
            <li><a href="manage_users.php">Manage Users</a></li>
            <li><a href="Import.php">Import Files</a></li>
          <?php endif; ?>

          <li><a href="Export.php">Export Files</a></li>

          <?php if ($role === 'admin' || $role === 'encoder'): ?>
            <li><a href="history.php">History</a></li>
            <li><a href="Trash_bin.php">Trash Bin</a></li>
          <?php endif; ?>

          <?php if (isset($_SESSION['username'])): ?>
            <li><a href="logout.php">Log Out</a></li>
          <?php else: ?>
            <li><a href="LogInpage.php">Log In</a></li>
          <?php endif; ?>
        </ul>
      </li>
    </ul>
  </nav>

  <h2 class="page-title">Trash Bin</h2>

  <div class="table-container">
    <div style="display:flex;gap:8px;align-items:center;margin:10px 0;">
      <button id="restoreSelected" class="restore-btn" disabled>Restore Selected</button>
      <span id="bulkCount" style="margin-left:6px;color:#555;"></span>
    </div>
    <table id="trashTable">
      <?php if (empty($columns)): ?>
        <tr>
          <td>No `document_history` table or no columns found.</td>
        </tr>
      <?php else: ?>
        <tr>
          <th><input type="checkbox" id="selectAll"></th>
          <th>Action</th>
          <?php foreach ($columns as $col): ?>
            <th><?= htmlspecialchars($col) ?></th>
          <?php endforeach; ?>
        </tr>

        <?php if ($result && $result->num_rows > 0): ?>
          <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
              <?php $histId = isset($row['id']) ? $row['id'] : (isset($row[$columns[0]]) ? $row[$columns[0]] : ''); ?>
              <td style="text-align:center;">
                <?php if ($histId !== ''): ?>
                  <input type="checkbox" class="rowCheckbox" value="<?= htmlspecialchars($histId) ?>">
                <?php endif; ?>
              </td>
              <td>
                <?php if ($histId !== ''): ?>
                  <div style="display:flex;gap:6px;align-items:center;">
                    <form method="POST" action="restore_document_history.php" class="restore-form"
                      data-id="<?= htmlspecialchars($histId) ?>" style="margin:0;">
                      <input type="hidden" name="id" value="<?= htmlspecialchars($histId) ?>">
                      <input type="hidden" name="action" value="auto">
                      <input type="submit" value="Restore" class="restore-btn">
                    </form>

                  </div>
                <?php else: ?>
                  <span class="small">No id</span>
                <?php endif; ?>
              </td>
              <?php foreach ($columns as $col): ?>
                <td><?= htmlspecialchars(isset($row[$col]) ? $row[$col] : '') ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td colspan="<?= count($columns) + 2 ?>">No deleted records found in history.</td>
          </tr>
        <?php endif; ?>
      <?php endif; ?>
    </table>
  </div>

  <!-- Restore Preview Modal (rich structure) -->
  <div id="restoreModal" style="display:none;">
    <div id="restoreContent">
      <div id="restoreHeader">
        <div>
          <h3 style="margin:0 0 6px 0;">Restore Preview</h3>
          <div id="changedCount"></div>
        </div>
        <div id="restoreControls">
          <label><input type="checkbox" id="onlyChangesToggle"> Show only changes</label>
          <button id="closeRestore" class="modal-close">✖</button>
        </div>
      </div>
      <div id="restoreTableBodyDiv">
        <table id="restoreTable">
          <thead>
            <tr>
              <th>Field</th>
              <th>Deleted Record</th>
              <th>Active Record</th>
            </tr>
          </thead>
          <tbody id="restoreTableBody">
            <tr>
              <td colspan="3">No data</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div id="restoreFooter">
        <button id="overwriteExisting" class="save-btn"
          style="display:none;background:#c0392b;margin-right:8px;">Overwrite
          Existing</button>
      </div>
    </div>
  </div>

  <!-- Bulk Conflict Modal -->
  <div id="bulkConflictModal" style="display:none;">
    <div id="bulkConflictContent">
      <div id="bulkConflictHeader">
        <h3 style="margin:0 0 6px 0;">Conflicting Records</h3>
        <div id="bulkConflictControls">
          <button id="bulkConflictCancel" class="modal-close">X</button>
        </div>
      </div>
      <div id="bulkConflictBody">
        <p id="bulkConflictSummary"></p>
        <table id="bulkConflictTable">
          <thead>
            <tr>
              <th><input type="checkbox" id="bulkSelectAllConflicts"></th>
              <th>ID</th>
              <th>Field</th>
              <th>Deleted</th>
              <th>Existing</th>
            </tr>
          </thead>
          <tbody id="bulkConflictTbody"></tbody>
        </table>
      </div>
      <div id="bulkConflictFooter">
        <button id="bulkOverwriteSelected" class="save-btn" style="background:#c0392b;margin-right:8px;">Overwrite
          Selected</button>
      </div>
    </div>
  </div>

  <script>
    // Use the richer preview modal structure and style from trash_bin_style.css
    document.addEventListener('DOMContentLoaded', function () {
      try {
        const forms = document.querySelectorAll('.restore-form');
        const modal = document.getElementById('restoreModal');
        const closeRestore = document.getElementById('closeRestore');
        const onlyChangesToggle = document.getElementById('onlyChangesToggle');
        const restoreTableBody = document.getElementById('restoreTableBody');
        const changedCountEl = document.getElementById('changedCount');
        const overwriteBtn = document.getElementById('overwriteExisting');

        // deletion confirmation
        function setupDeleteConfirmation() {
          document.querySelectorAll('.delete-form').forEach(function (df) {
            df.addEventListener('submit', function (ev) {
              ev.preventDefault();
              const id = df.querySelector('input[name="id"]').value;
              if (confirm('Permanently delete this history entry? This cannot be undone.')) {
                df.submit();
              }
            });
          });
        }

        function openModal() { modal.style.display = 'block'; }
        function closeModal() { modal.style.display = 'none'; restoreTableBody.innerHTML = '<tr><td colspan="3">No data</td></tr>'; changedCountEl.innerText = ''; }
        if (closeRestore) closeRestore.addEventListener('click', closeModal);
        if (modal) modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });

        if (forms && forms.length) {
          forms.forEach(function (f) {
            f.addEventListener('submit', function (ev) {
              ev.preventDefault();
              const id = f.dataset.id;
              // fetch preview
              fetch('restore_preview.php?id=' + encodeURIComponent(id), { credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => {
                  if (data.error) {
                    alert(data.error);
                    return;
                  }
                  const deleted = data.deleted || {};
                  const existing = data.existing || {};

                  // Determine whether an existing (concurrent) record actually exists.
                  // The modal should appear if the existing record matches by record_id
                  // OR access_code. If those keys aren't available, fall back to the
                  // previous heuristic (any non-empty field).
                  const hasRecordId = existing && existing.record_id != null && String(existing.record_id).trim() !== '';
                  const hasAccessCode = existing && existing.access_code != null && String(existing.access_code).trim() !== '';
                  const anyNonEmpty = existing && Object.keys(existing).length > 0 &&
                    Object.values(existing).some(v => v !== null && v !== undefined && String(v).trim() !== '');
                  const existingHasData = hasRecordId || hasAccessCode || anyNonEmpty;

                  // If there is no concurrent existing record, skip the modal and perform
                  // the simple insert restore immediately.
                  if (!existingHasData) {
                    // Directly proceed with insert restore (no overwrite option needed)
                    postAction(id, 'insert');
                    return;
                  }

                  // At this point there is a concurrent record: show the modal and include overwrite
                  // option only for this case.
                  overwriteBtn.style.display = '';

                  const fields = ['record_id', 'access_code', 'library', 'location_code', 'author', 'department', 'title', 'type', 'record_status', 'retention_period', 'description', 'document_number', 'supplier', 'amount_due', 'notes_keywords', 'addressee', 'accountable_officer', 'sender', 'source_funds', 'property_number', 'total_cost', 'attachment_name', 'status', 'date_created'];

                  const rows = [];
                  let changedCount = 0;
                  fields.forEach(function (fkey) {
                    const dVal = deleted[fkey] == null ? '' : deleted[fkey];
                    const eVal = existing[fkey] == null ? '' : existing[fkey];
                    const nd = normalize(dVal);
                    const ne = normalize(eVal);
                    const changed = nd !== ne;
                    if (changed) changedCount++;
                    rows.push({ field: fkey, deleted: dVal, existing: eVal, changed: changed });
                  });

                  // sort changed first
                  rows.sort(function (a, b) { return (b.changed === a.changed) ? 0 : (a.changed ? -1 : 1); });

                  // render
                  restoreTableBody.innerHTML = '';
                  rows.forEach(function (r) {
                    const tr = document.createElement('tr');
                    if (r.changed) tr.classList.add('changed');
                    const tdField = document.createElement('td'); tdField.innerText = r.field;
                    const tdDeleted = document.createElement('td');
                    const tdExisting = document.createElement('td');
                    if (r.field === 'attachment_name') {
                      // Support multiple attachments arrays (preferred), then legacy single 'attachment'/'attachment_name'
                      function extractAttachments(obj) {
                        if (!obj) return [];
                        // Prefer .attachments array if present
                        if (Array.isArray(obj.attachments) && obj.attachments.length) return obj.attachments;
                        // If there's a JSON string in attachment(s), try to parse
                        if (typeof obj.attachment === 'string') {
                          try {
                            const parsed = JSON.parse(obj.attachment);
                            if (Array.isArray(parsed)) return parsed;
                          } catch (e) {
                            // not JSON
                          }
                        }
                        // fallback: legacy single fields
                        if (obj.attachment_name) return [{ file_path: obj.attachment || '', original_name: obj.attachment_name }];
                        if (obj.attachment) return [obj.attachment];
                        return [];
                      }

                      function attToItem(a) {
                        // Normalize attachment item to {href, text, id?}
                        if (!a) return null;
                        if (typeof a === 'string') {
                          const txt = a.split('/').pop();
                          return { href: a, text: txt };
                        }
                        if (typeof a === 'object') {
                          const id = a.id || a.attachment_id || a.attachmentId || null;
                          if (id) return { href: 'download_attachment.php?attachment_id=' + encodeURIComponent(id), text: a.original_name || a.file_path && a.file_path.split('/').pop() || String(id) };
                          if (a.file_path) return { href: a.file_path, text: a.original_name || a.file_path.split('/').pop() };
                          if (a.original_name) return { href: '', text: a.original_name };
                        }
                        return null;
                      }

                      function normalizeName(it) { if (!it) return ''; return String(it.text || it.href || it).replace(/\s+/g, ' ').trim(); }

                      const dArr = extractAttachments(deleted);
                      const eArr = extractAttachments(existing);
                      const dItems = dArr.map(attToItem).filter(Boolean);
                      const eItems = eArr.map(attToItem).filter(Boolean);

                      const dNames = dItems.map(normalizeName);
                      const eNames = eItems.map(normalizeName);

                      // Build HTML lists and mark added/removed
                      function buildHtml(items, otherNames, side) {
                        if (!items.length) return '';
                        const parts = items.map(function (it) {
                          const name = normalizeName(it);
                          const isAdded = (side === 'existing') && otherNames.indexOf(name) === -1;
                          const isRemoved = (side === 'deleted') && otherNames.indexOf(name) === -1;
                          const note = isAdded ? ' (added)' : (isRemoved ? ' (removed)' : '');
                          const href = it.href || '';
                          if (href) return '<div class="att-item">' + (href ? '<a href="' + escapeHtml(href) + '" target="_blank">' + escapeHtml(name) + '</a>' : escapeHtml(name)) + escapeHtml(note) + '</div>';
                          return '<div class="att-item">' + escapeHtml(name) + escapeHtml(note) + '</div>';
                        });
                        return parts.join('');
                      }

                      tdDeleted.innerHTML = buildHtml(dItems, eNames, 'deleted') || (r.deleted ? escapeHtml(r.deleted) : '');
                      tdExisting.innerHTML = buildHtml(eItems, dNames, 'existing') || (r.existing ? escapeHtml(r.existing) : '');

                      // If attachment lists differ, ensure the row is marked changed
                      if (dNames.join('|') !== eNames.join('|')) tr.classList.add('changed');
                    } else {
                      tdDeleted.innerText = r.deleted;
                      tdExisting.innerText = r.existing;
                    }
                    tr.appendChild(tdField); tr.appendChild(tdDeleted); tr.appendChild(tdExisting);
                    restoreTableBody.appendChild(tr);
                  });

                  changedCountEl.innerText = changedCount + ' field(s) changed';

                  // wire confirm button to perform overwrite only (modal only appears on conflict)
                  overwriteBtn.onclick = function () { postAction(id, 'overwrite'); };

                  // only-changes toggle
                  onlyChangesToggle.checked = false;
                  onlyChangesToggle.onchange = function () {
                    const only = onlyChangesToggle.checked;
                    document.querySelectorAll('#restoreTableBody tr').forEach(function (tr) {
                      if (!tr.classList.contains('changed')) tr.style.display = only ? 'none' : '';
                    });
                  };

                  openModal();
                })
                .catch(function (err) { console.error(err); alert('Failed to load preview'); });
            });
          });
        }

        // wire delete confirmations
        setupDeleteConfirmation();

        // bulk selection logic
        const selectAll = document.getElementById('selectAll');
        const rowCheckboxes = () => Array.from(document.querySelectorAll('.rowCheckbox'));
        const restoreSelectedBtn = document.getElementById('restoreSelected');
        const deleteSelectedBtn = document.getElementById('deleteSelected');
        const bulkCountEl = document.getElementById('bulkCount');

        function updateBulkState() {
          const checked = rowCheckboxes().filter(c => c.checked).map(c => c.value);
          const count = checked.length;
          restoreSelectedBtn.disabled = count === 0;
          deleteSelectedBtn.disabled = count === 0;
          bulkCountEl.innerText = count ? (count + ' selected') : '';
        }

        if (selectAll) {
          selectAll.addEventListener('change', function () {
            rowCheckboxes().forEach(cb => cb.checked = selectAll.checked);
            updateBulkState();
          });
        }

        document.addEventListener('change', function (e) {
          if (e.target && e.target.classList && e.target.classList.contains('rowCheckbox')) {
            updateBulkState();
            // if any unchecked, uncheck selectAll
            if (!Array.from(document.querySelectorAll('.rowCheckbox')).every(cb => cb.checked)) {
              selectAll.checked = false;
            } else {
              selectAll.checked = true;
            }
          }
        });

        // helper to post array of ids
        function postJson(url, data) {
          return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
            credentials: 'same-origin'
          }).then(r => r.json());
        }

        if (restoreSelectedBtn) {
          restoreSelectedBtn.addEventListener('click', function () {
            const ids = rowCheckboxes().filter(c => c.checked).map(c => c.value);
            if (!ids.length) return;
            restoreSelectedBtn.disabled = true;
            postJson('restore_document_history.php', { ids: ids, action: 'bulk_restore' })
              .then(resp => {
                if (resp && resp.success) {
                  location.reload();
                  return;
                }

                // If there are failed items, check for conflicts
                const failed = (resp && resp.results && resp.results.failed) || [];
                const conflicts = failed.filter(f => f.conflict);

                if (conflicts.length > 0) {
                  // Build and show the conflict modal so the user can pick which conflicts to overwrite
                  const modal = document.getElementById('bulkConflictModal');
                  const tbody = document.getElementById('bulkConflictTbody');
                  const summary = document.getElementById('bulkConflictSummary');
                  const selectAllConflicts = document.getElementById('bulkSelectAllConflicts');
                  const bulkOverwriteBtn = document.getElementById('bulkOverwriteSelected');
                  const bulkCancel = document.getElementById('bulkConflictCancel');

                  tbody.innerHTML = '';
                  summary.innerText = conflicts.length + ' conflicting record(s) detected. Review and select which ones to overwrite.';

                  conflicts.forEach(function (f) {
                    const id = f.id;
                    const del = f.deleted || {};
                    const ex = f.existing || {};
                    const tr = document.createElement('tr');
                    const tdChk = document.createElement('td');
                    const chk = document.createElement('input'); chk.type = 'checkbox'; chk.className = 'conflictCheckbox'; chk.value = id;
                    tdChk.appendChild(chk);
                    const tdId = document.createElement('td'); tdId.innerText = id;
                    const tdField = document.createElement('td'); tdField.innerText = 'record_id / access_code / title';
                    const tdDel = document.createElement('td'); tdDel.innerText = (del.record_id || '') + ' / ' + (del.access_code || '') + ' / ' + (del.title || '');
                    const tdEx = document.createElement('td'); tdEx.innerText = (ex.record_id || '') + ' / ' + (ex.access_code || '') + ' / ' + (ex.title || '');
                    tr.appendChild(tdChk); tr.appendChild(tdId); tr.appendChild(tdField); tr.appendChild(tdDel); tr.appendChild(tdEx);
                    tbody.appendChild(tr);
                  });

                  // show modal
                  modal.style.display = 'block';

                  // select-all behavior inside modal
                  selectAllConflicts.checked = false;
                  selectAllConflicts.onchange = function () { document.querySelectorAll('.conflictCheckbox').forEach(c => c.checked = selectAllConflicts.checked); };

                  bulkCancel.onclick = function () { modal.style.display = 'none'; restoreSelectedBtn.disabled = false; };

                  bulkOverwriteBtn.onclick = function () {
                    const sel = Array.from(document.querySelectorAll('.conflictCheckbox')).filter(c => c.checked).map(c => c.value);
                    if (!sel.length) { alert('No conflicts selected'); return; }
                    bulkOverwriteBtn.disabled = true;
                    postJson('restore_document_history.php', { ids: sel, action: 'bulk_overwrite' })
                      .then(resp3 => {
                        if (resp3 && resp3.success) {
                          location.reload();
                        } else {
                          alert((resp3 && resp3.error) || 'Bulk overwrite failed');
                          bulkOverwriteBtn.disabled = false;
                          modal.style.display = 'none';
                          restoreSelectedBtn.disabled = false;
                        }
                      })
                      .catch(err => { console.error(err); alert('Bulk overwrite failed'); bulkOverwriteBtn.disabled = false; modal.style.display = 'none'; restoreSelectedBtn.disabled = false; });
                  };

                  // allow clicking outside to close
                  modal.onclick = function (e) { if (e.target === modal) { modal.style.display = 'none'; restoreSelectedBtn.disabled = false; } };

                  // Esc key
                  document.addEventListener('keydown', function onEsc(e) { if (e.key === 'Escape') { modal.style.display = 'none'; restoreSelectedBtn.disabled = false; document.removeEventListener('keydown', onEsc); } });
                } else {
                  // no conflicts but failed for other reasons
                  alert(resp && resp.error ? resp.error : 'Bulk restore failed');
                  restoreSelectedBtn.disabled = false;
                }
              })
              .catch(err => { console.error(err); alert('Bulk restore failed'); restoreSelectedBtn.disabled = false; });
          });
        }

        if (deleteSelectedBtn) {
          deleteSelectedBtn.addEventListener('click', function () {
            const ids = rowCheckboxes().filter(c => c.checked).map(c => c.value);
            if (!ids.length) return;
            if (!confirm('Permanently delete the selected history entries? This cannot be undone.')) return;
            deleteSelectedBtn.disabled = true;
            postJson('delete_document_history.php', { ids: ids, action: 'bulk_delete' })
              .then(resp => {
                if (resp && resp.success) {
                  location.reload();
                } else {
                  alert(resp && resp.error ? resp.error : 'Bulk delete failed');
                  deleteSelectedBtn.disabled = false;
                }
              })
              .catch(err => { console.error(err); alert('Bulk delete failed'); deleteSelectedBtn.disabled = false; });
          }

        function postAction(id, action) {
              const form = document.createElement('form');
              form.method = 'POST';
              form.action = 'restore_document_history.php';
              form.style.display = 'none';
              const i1 = document.createElement('input'); i1.name = 'id'; i1.value = id; form.appendChild(i1);
              const i2 = document.createElement('input'); i2.name = 'action'; i2.value = action; form.appendChild(i2);
              document.body.appendChild(form);
              form.submit();
            }

        function normalize(v) { if (v === null || v === undefined) return ''; return String(v).replace(/\s+/g, ' ').trim(); }
        function escapeHtml(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;'); }
      } catch (err) {
          console.error('Error in trash_bin.js', err);
          alert('An error occurred initializing the Trash UI: ' + (err && err.message ? err.message : err));
        }
      });
  </script>
</body>

</html>