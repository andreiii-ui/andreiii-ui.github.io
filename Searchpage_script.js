let rowsPerPage = 10;
let currentPage = 1;
let totalRecords = 0;
let currentSearchField = '';
let currentSearchInput = '';
let currentSortOrder = 'desc';
let currentSortOrder2 = '';
let selectionMode = false;
let _searchDebounceTimer = null;
let isEditingEnabled = false;

// ====================== FETCH RECORDS ======================
function fetchRecords(page = 1) {
  const params = new URLSearchParams({
    page: page,
    per_page: rowsPerPage === 'all' ? 0 : rowsPerPage,
    search_field: currentSearchField,
    search_input: currentSearchInput,
    sort_order: currentSortOrder
  });

  if (currentSortOrder2) params.append('sort_order2', currentSortOrder2);

  fetch('api_fetch_records.php?' + params)
    .then(response => response.json())
    .then(data => {
      totalRecords = data.total ?? 0;
      // server indicates whether the current user can modify records
      window.canModify = (data.can_modify == 1);
      populateTable(data.records || []);
      updatePagination();
      document.getElementById('noResults').style.display = (data.records && data.records.length === 0) ? 'block' : 'none';
    })
    .catch(error => {
      console.error('Error fetching records:', error);
      document.getElementById('noResults').style.display = 'block';
    });
}

// ====================== POPULATE TABLE ======================
// Only display the required columns in the table body:
// Actions (first col) | Access Code | Location Code | Author | Department | Title | Type
function populateTable(records) {
  const tbody = document.getElementById('tableBody');
  tbody.innerHTML = '';

  records.forEach(record => {
    const tr = document.createElement('tr');
    tr.dataset.recordId = record.record_id;

    // If selection mode => show a checkbox in the first column; otherwise show actions (View/Trash/Bookmark)
    let firstCellHtml = '';
    if (selectionMode) {
      firstCellHtml = `<input type="checkbox" class="select-checkbox" data-record-id="${record.record_id}">`;
    } else {
      // View always visible for everyone
      const viewBtn = `<button class='view-btn' onclick='openViewModal(${record.record_id})'>View</button>`;
      // Trash visible only if can modify
      const trashBtn = window.canModify ? `<button class='delete-btn' onclick='deleteRecord(${record.record_id})'>Trash</button>` : '';
      // Bookmark for all
      const bookmarkBtn = `
        <button class='bookmark-btn' id='bookmark-${record.record_id}' onclick='toggleSave(${record.record_id})' title="Bookmark">
          <i class='bx ${record.is_saved == 1 ? "bxs-bookmarks" : "bx-bookmarks"}'></i>
        </button>`;
      firstCellHtml = `${viewBtn} ${trashBtn} ${bookmarkBtn}`;
    }

    tr.innerHTML = `
      <td>${firstCellHtml}</td>
      <td>${escapeHtml(record.access_code || '')}</td>
      <td>${escapeHtml(record.location_code || '')}</td>
      <td>${escapeHtml(record.author || '')}</td>
      <td>${escapeHtml(record.department || '')}</td>
      <td>${escapeHtml(record.title || '')}</td>
      <td>${escapeHtml(record.type || '')}</td>
    `;
    tbody.appendChild(tr);
  });

  // If no records, show a single row (keeps layout consistent)
  if (!records || records.length === 0) {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td colspan="7" style="text-align:center;">No records found</td>`;
    tbody.appendChild(tr);
  }

  // After table is rendered, wire up select-checkbox events if in selection mode
  wireRowCheckboxes();
  // Update header select all visibility
  updateHeaderSelectAllVisibility();
}

// small helper to avoid XSS when inserting text content from server
function escapeHtml(text) {
  return String(text)
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

// ====================== SELECTION MODE ======================
function toggleSelectionMode() {
  selectionMode = document.getElementById('selectionModeCheckbox').checked;

  document.querySelectorAll('.select-checkbox').forEach(cb => {
    cb.style.display = selectionMode ? 'inline-block' : 'none';
    cb.checked = false;
  });

  updateHeaderSelectAllVisibility();
  wireRowCheckboxes();
}

// ====================== WIRED ROW CHECKBOXES ======================
function wireRowCheckboxes() {
  const rowCheckboxes = document.querySelectorAll('.select-checkbox');

  // Remove existing listeners by cloning (avoids duplicates)
  rowCheckboxes.forEach(cb => {
    const newCb = cb.cloneNode(true);
    cb.parentNode.replaceChild(newCb, cb);
  });

  const freshCheckboxes = document.querySelectorAll('.select-checkbox');
  freshCheckboxes.forEach(cb => {
    cb.addEventListener('change', () => {
      updateSelectAllState();

      const anyChecked = document.querySelectorAll('.select-checkbox:checked').length > 0;
      const deleteBtn = document.getElementById('deleteSelectedBtn');
      const savedBtn = document.getElementById('savedSelectedBtn');

      if (deleteBtn) deleteBtn.style.display = anyChecked ? 'inline-block' : 'none';
      if (savedBtn) savedBtn.style.display = anyChecked ? 'inline-block' : 'none';
    });
  });

  if (selectionMode) updateSelectAllState();
}

// ====================== HEADER SELECT ALL ======================
function updateHeaderSelectAllVisibility() {
  const headerSelectAll = document.getElementById('selectAllCheckbox');
  if (!headerSelectAll) return;

  headerSelectAll.style.display = selectionMode ? 'inline-block' : 'none';

  const deleteBtn = document.getElementById('deleteSelectedBtn');
  const savedBtn = document.getElementById('savedSelectedBtn');

  if (selectionMode) {
    headerSelectAll.checked = false;
    headerSelectAll.indeterminate = false;
    if (deleteBtn) deleteBtn.style.display = 'none';
    if (savedBtn) savedBtn.style.display = 'none';
  } else {
    if (deleteBtn) deleteBtn.style.display = 'none';
    if (savedBtn) savedBtn.style.display = 'none';
  }
}

// Show transient attachment feedback message in modal
function showAttachmentMessage(text, isSuccess = true, timeout = 5000) {
  const msgEl = document.getElementById('attachmentMessage');
  if (!msgEl) return;
  msgEl.textContent = text;
  msgEl.classList.remove('success', 'error');
  msgEl.classList.add(isSuccess ? 'success' : 'error');
  msgEl.style.display = 'inline-block';
  if (timeout > 0) {
    setTimeout(() => {
      try { msgEl.style.display = 'none'; msgEl.textContent = ''; } catch (e) { }
    }, timeout);
  }
}


function updateSelectAllState() {
  const header = document.getElementById('selectAllCheckbox');
  if (!header) return;
  const checkboxes = document.querySelectorAll('.select-checkbox');
  if (checkboxes.length === 0) {
    header.checked = false;
    header.indeterminate = false;
    return;
  }
  const checkedCount = document.querySelectorAll('.select-checkbox:checked').length;
  if (checkedCount === 0) {
    header.checked = false;
    header.indeterminate = false;
  } else if (checkedCount === checkboxes.length) {
    header.checked = true;
    header.indeterminate = false;
  } else {
    header.checked = false;
    header.indeterminate = true; // shows partial selection
  }
}

// ----------------- selectAll hooked to header checkbox -----------------
function selectAll() {
  const header = document.getElementById('selectAllCheckbox');
  if (!header) return;
  const shouldCheck = header.checked;
  document.querySelectorAll('.select-checkbox').forEach(cb => {
    cb.checked = shouldCheck;
    // dispatch change so handlers react (deleteSelected visibility, etc.)
    cb.dispatchEvent(new Event('change', { bubbles: true }));
  });
  header.indeterminate = false;
}

// ====================== PAGINATION ======================
function showPage(page) {
  currentPage = page;
  fetchRecords(page);
}

function updatePagination() {
  const pagination = document.getElementById('pagination');
  pagination.innerHTML = '';

  if (rowsPerPage === 'all' || totalRecords <= rowsPerPage) return;

  const totalPages = Math.ceil(totalRecords / rowsPerPage);
  if (totalPages <= 1) return;

  const maxVisible = 5;
  let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
  let endPage = Math.min(totalPages, startPage + maxVisible - 1);

  if (endPage - startPage < maxVisible - 1) {
    startPage = Math.max(1, endPage - maxVisible + 1);
  }

  const prevButton = document.createElement('button');
  prevButton.textContent = '← Prev';
  prevButton.className = currentPage === 1 ? 'disabled' : '';
  prevButton.onclick = () => {
    if (currentPage > 1) showPage(currentPage - 1);
  };
  pagination.appendChild(prevButton);

  if (startPage > 1) {
    const firstBtn = document.createElement('button');
    firstBtn.textContent = '1';
    firstBtn.onclick = () => showPage(1);
    pagination.appendChild(firstBtn);
    if (startPage > 2) pagination.append('...');
  }

  for (let i = startPage; i <= endPage; i++) {
    const btn = document.createElement('button');
    btn.textContent = i;
    if (i === currentPage) btn.classList.add('active');
    btn.onclick = () => showPage(i);
    pagination.appendChild(btn);
  }

  if (endPage < totalPages) {
    if (endPage < totalPages - 1) pagination.append('...');
    const lastBtn = document.createElement('button');
    lastBtn.textContent = totalPages;
    lastBtn.onclick = () => showPage(totalPages);
    pagination.appendChild(lastBtn);
  }

  const nextButton = document.createElement('button');
  nextButton.textContent = 'Next →';
  nextButton.className = currentPage === totalPages ? 'disabled' : '';
  nextButton.onclick = () => {
    if (currentPage < totalPages) showPage(currentPage + 1);
  };
  pagination.appendChild(nextButton);
}

// ====================== FILTERS & SORTING ======================
function updateRowsPerPage() {
  const value = document.getElementById('rowsPerPage').value;
  rowsPerPage = value === 'all' ? 'all' : parseInt(value);
  currentPage = 1;
  fetchRecords(1);
}

function filterTable() {
  // Use server-side search so pagination and totals update correctly.
  const input = document.getElementById('searchInput').value.trim();
  const field = document.getElementById('searchField').value || 'all';

  currentSearchInput = input;
  currentSearchField = field;
  currentPage = 1;

  // Debounce to avoid excessive requests while typing
  if (_searchDebounceTimer) clearTimeout(_searchDebounceTimer);
  _searchDebounceTimer = setTimeout(() => {
    fetchRecords(1);
  }, 300);
}

function sortTable() {
  currentSortOrder = document.getElementById('sortOrder').value;
  currentPage = 1;
  fetchRecords(1);
}

function sortTable2() {
  currentSortOrder2 = document.getElementById('sortOrder2').value;
  currentPage = 1;
  fetchRecords(1);
}

// ====================== SELECTION MODE ======================
function toggleSelectionMode() {
  selectionMode = document.getElementById('selectionModeCheckbox').checked;
  // Only toggle buttons if they exist in the DOM (delete may be restricted server-side)
  const deleteBtn = document.getElementById('deleteSelectedBtn');
  const savedBtn = document.getElementById('savedSelectedBtn');
  if (deleteBtn) deleteBtn.style.display = selectionMode ? 'inline-block' : 'none';
  if (savedBtn) savedBtn.style.display = selectionMode ? 'inline-block' : 'none';
  fetchRecords(currentPage); // reloads table if necessary
}



function deleteSelected() {
  const selected = Array.from(document.querySelectorAll('.select-checkbox:checked')).map(cb => cb.dataset.recordId);
  if (selected.length === 0) {
    alert('No records selected');
    return;
  }
  if (confirm(`Delete ${selected.length} selected records?`)) {
    window.location.href = 'delete_record.php?record_ids=' + selected.join(',');
  }
}
// ====================== SAVE SELECTED ======================
function savedSelected() {
  const selected = Array.from(document.querySelectorAll('.select-checkbox:checked'))
    .map(cb => cb.dataset.recordId);

  if (selected.length === 0) {
    alert('No records selected');
    return;
  }

  selected.forEach((recordId, index) => {
    fetch('toggle_save.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'record_id=' + encodeURIComponent(recordId)
    })
      .then(response => response.json())
      .then(data => {
        const icon = document.querySelector(`#bookmark-${recordId} i`);
        if (icon) {
          if (data.status === 'saved') {
            icon.classList.remove('bx-bookmarks');
            icon.classList.add('bxs-bookmarks');
          } else if (data.status === 'removed') {
            icon.classList.remove('bxs-bookmarks');
            icon.classList.add('bx-bookmarks');
          }
        }

        // After the last record is processed, refresh page
        if (index === selected.length - 1) {
          alert(`${selected.length} record(s) updated in saved list`);
          window.location.reload(); // Refresh page to show updated saved state
        }
      })
      .catch(err => console.error('Bookmark error:', err));
  });
}


// ====================== MODALS (View + Enable Edit) ======================
function openAddModal() {
  document.getElementById("modalTitle").innerText = "Add Record";
  document.getElementById("recordForm").reset();
  document.getElementById("action").value = "add";
  document.getElementById("date_created").value = new Date().toISOString().split('T')[0];
  // Ensure fields are editable in add mode
  setReadOnlyMode(false);
  document.getElementById("saveModalBtn").style.display = "inline-block";
  const enableBtn = document.getElementById("enableEditBtn");
  if (enableBtn) enableBtn.style.display = "none";
  // Clear any leftover attachment UI from previous viewed/edited record
  try {
    const attachmentsTable = document.getElementById('attachmentsTable');
    const attachmentsBody = document.getElementById('attachmentsTableBody');
    const existingAttachmentInput = document.getElementById('existing_attachment');
    const fileInput = document.getElementById('attachment_file');
    const selectedFilesSpan = document.getElementById('selectedFiles');
    const removeLabel = document.getElementById('removeAttachmentLabel');

    if (attachmentsBody) attachmentsBody.innerHTML = '';
    if (attachmentsTable) attachmentsTable.style.display = 'none';
    if (existingAttachmentInput) existingAttachmentInput.value = '';
    if (fileInput) {
      fileInput.value = '';
      fileInput.style.display = 'inline-block';
    }
    if (selectedFilesSpan) selectedFilesSpan.textContent = '';
    if (removeLabel) removeLabel.style.display = 'none';
  } catch (e) {
    // ignore any errors related to missing elements
    console.warn('openAddModal: cleanup error', e);
  }

  document.getElementById("recordModal").style.display = "flex";
}

// Opens modal in view-only mode (all users)
function openViewModal(recordId) {
  openRecordModal(recordId, true);
  document.getElementById("saveModalBtn").style.display = "none";
}

// Unified open that supports view-only or edit
function openRecordModal(recordId, viewOnly = false) {
  if (!recordId && recordId !== 0) {
    alert("Invalid record id");
    return;
  }

  // Try primary JSON endpoint
  fetch("api_get_record.php?id=" + encodeURIComponent(recordId), { cache: "no-store" })
    .then(res => res.json().catch(() => ({ error: "invalid_json" })))
    .then(data => {
      if (data && data.success && data.record) {
        fillFormFromRecord(data.record, viewOnly);
        return;
      }

      // Fallback to legacy endpoint
      return fetch("get_record.php?record_id=" + encodeURIComponent(recordId), { cache: "no-store" })
        .then(r => r.json().catch(() => ({ error: "invalid_json" })))
        .then(d2 => {
          if (d2 && !d2.error && Object.keys(d2).length > 0) {
            const rec = d2.record ?? d2;
            fillFormFromRecord(rec, viewOnly);
            return;
          }

          // Last resort: read what we can from the displayed table row
          const tr = document.querySelector(`#recordsTable tbody tr[data-record-id="${recordId}"]`);
          if (!tr) {
            alert("Record not found (server & DOM fallback failed).");
            return;
          }

          // Map visible headers to fields
          const headerCells = Array.from(document.querySelectorAll("#recordsTable thead th")).map(h => h.innerText.trim().toLowerCase());
          const cells = Array.from(tr.children);
          const recordObj = { record_id: recordId };

          // For each visible cell find header and map
          for (let i = 0; i < cells.length; i++) {
            const headerText = headerCells[i] || '';
            const text = cells[i].innerText.trim();
            if (/access code/i.test(headerText)) recordObj.access_code = text;
            else if (/location code/i.test(headerText)) recordObj.location_code = text;
            else if (/author/i.test(headerText)) recordObj.author = text;
            else if (/department/i.test(headerText)) recordObj.department = text;
            else if (/title/i.test(headerText)) recordObj.title = text;
            else if (/type/i.test(headerText)) recordObj.type = text;
            // other fields may not be present; ignore
          }

          fillFormFromRecord(recordObj, viewOnly);
        });
    })
    .catch(err => {
      console.error("Error opening modal.", err);
      alert("Error opening modal.");
    });
}

function fillFormFromRecord(record, viewOnly) {
  // Set modal title and action
  document.getElementById("modalTitle").innerText = viewOnly ? "View Record" : "Edit Record";
  document.getElementById("action").value = "edit";

  // Ensure hidden record id is set
  const rid = document.getElementById("record_id");
  if (rid) rid.value = record.record_id ?? record.id ?? record.recordId ?? '';

  // Fill form values for known inputs in the form
  const form = document.getElementById("recordForm");
  if (!form) {
    console.error("recordForm not found");
    return;
  }

  // For each input/select/textarea in the form, set value from record (if present)
  const elements = form.querySelectorAll("input, textarea, select");
  elements.forEach(el => {
    const name = el.name || el.id;
    if (!name) return;
    // Special handling for checkboxes
    if (el.type === 'checkbox') {
      el.checked = !!(record[name]);
    } else if (record.hasOwnProperty(name)) {
      el.value = record[name] ?? '';
    } else {
      // if no matching field in record, leave as-is (useful for add)
    }
  });

  // Show modal
  const modal = document.getElementById("recordModal");
  if (modal) modal.style.display = "flex";

  // Set read-only or editable mode
  setReadOnlyMode(viewOnly);

  // Show/hide Enable Edit button inside modal depending on permission & viewOnly
  const enableBtn = document.getElementById("enableEditBtn");
  if (enableBtn) {
    const canEdit = !!window.canModify; // admin/encoder flagged by server
    enableBtn.style.display = (viewOnly && canEdit) ? "inline-block" : "none";
  }

  // Reset isEditingEnabled flag
  isEditingEnabled = !viewOnly;

  // Show or hide the file input depending on edit mode
  const fileInput = document.getElementById('attachment_file');
  if (fileInput) fileInput.style.display = isEditingEnabled ? 'inline-block' : 'none';
  // Also toggle the visible "Choose files" button and clear selection preview
  const selectBtn = document.getElementById('selectAttachmentBtn');
  const selectedFilesSpan = document.getElementById('selectedFiles');
  if (selectBtn) selectBtn.style.display = isEditingEnabled ? 'inline-block' : 'none';
  if (selectedFilesSpan && !isEditingEnabled) selectedFilesSpan.textContent = '';

  // Handle attachment display as a table
  const attachmentsTable = document.getElementById('attachmentsTable');
  const attachmentsBody = document.getElementById('attachmentsTableBody');
  const existingAttachmentInput = document.getElementById('existing_attachment');
  if (attachmentsTable && attachmentsBody && existingAttachmentInput) {
    // clear
    attachmentsBody.innerHTML = '';
    const attachments = record.attachments || [];
    if (attachments.length > 0) {
      attachments.forEach(att => {
        const tr = document.createElement('tr');
        tr.dataset.attachmentId = att.id;

        const tdName = document.createElement('td');
        const link = document.createElement('a');
        link.href = 'download_attachment.php?attachment_id=' + encodeURIComponent(att.id);
        link.target = '_blank';
        link.textContent = att.original_name || (att.file_path ? att.file_path.split('/').pop() : 'Download');
        tdName.appendChild(link);

        const tdUploaded = document.createElement('td');
        tdUploaded.textContent = att.uploaded_at || '';

        const tdActions = document.createElement('td');
        tdActions.style.textAlign = 'center';

        // Delete button (visible to modifiers); disabled unless editing enabled
        if (window.canModify) {
          const delBtn = document.createElement('button');
          delBtn.type = 'button';
          delBtn.className = 'delete-btn attachment-delete-btn';
          delBtn.textContent = 'Delete';
          delBtn.style.padding = '4px 8px';
          delBtn.disabled = !isEditingEnabled;
          if (delBtn.disabled) delBtn.title = 'Enable edit to delete attachments';
          delBtn.addEventListener('click', function (ev) {
            ev.preventDefault();
            if (!confirm('Delete this attachment?')) return;
            delBtn.disabled = true;
            fetch('delete_attachment.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: 'attachment_id=' + encodeURIComponent(att.id)
            })
              .then(function (r) {
                const ct = r.headers.get('content-type') || '';
                // If server claims JSON, try parsing but tolerate parse failures
                if (ct.indexOf('application/json') !== -1) {
                  return r.json().then(json => ({ ok: r.ok, json: json })).catch(err => ({ ok: r.ok, parseError: true }));
                }
                return r.text().then(text => ({ ok: r.ok, text: text }));
              })
              .then(function (resp) {
                // If parsed JSON exists, handle normally
                if (resp && resp.json) {
                  const json = resp.json;
                  if (json && json.success) {
                    tr.parentNode && tr.parentNode.removeChild(tr);
                    const deletedPath = json.deleted_path || '';
                    const fileDeleted = json.file_deleted == 1 || json.file_deleted === true;
                    if (fileDeleted) {
                      showAttachmentMessage('Attachment deleted from disk: ' + (deletedPath || '(path unknown)'), true, 6000);
                    } else {
                      showAttachmentMessage('Attachment record removed but file not found on disk.', false, 7000);
                    }
                    if (attachmentsBody.children.length === 0) {
                      attachmentsTable.style.display = 'none';
                      existingAttachmentInput.value = '';
                    }
                    return;
                  }
                  showAttachmentMessage('Delete failed: ' + (json && json.error ? json.error : 'unknown'), false, 7000);
                  delBtn.disabled = false;
                  return;
                }

                // If server responded 2xx but parsing failed or returned plain text, treat 2xx as success
                if (resp && resp.ok) {
                  console.warn('delete_attachment: server returned non-JSON or parse failed; treating 2xx as success');
                  tr.parentNode && tr.parentNode.removeChild(tr);
                  showAttachmentMessage('Attachment deleted (server returned non-JSON response).', true, 6000);
                  if (attachmentsBody.children.length === 0) {
                    attachmentsTable.style.display = 'none';
                    existingAttachmentInput.value = '';
                  }
                  return;
                }

                // Non-2xx case
                const txt = (resp && resp.text) ? resp.text : '';
                showAttachmentMessage('Delete failed: ' + (txt || 'unknown response'), false, 7000);
                delBtn.disabled = false;
              })
              .catch(err => {
                console.error('Delete error', err);
                showAttachmentMessage('Delete failed: network or server error', false, 7000);
                delBtn.disabled = false;
              });
          });
          tdActions.appendChild(delBtn);
        }

        tr.appendChild(tdName);
        tr.appendChild(tdUploaded);
        tr.appendChild(tdActions);
        attachmentsBody.appendChild(tr);
      });
      attachmentsTable.style.display = 'table';
      // legacy hidden value: keep first attachment path
      existingAttachmentInput.value = attachments[0].file_path || '';
    } else {
      attachmentsTable.style.display = 'none';
      existingAttachmentInput.value = '';
    }
  }
}

// Wire the Choose files button to the hidden file input and show a simple filename preview
window.addEventListener('DOMContentLoaded', function () {
  const selectBtn = document.getElementById('selectAttachmentBtn');
  const fileInput = document.getElementById('attachment_file');
  const selectedFilesSpan = document.getElementById('selectedFiles');

  if (selectBtn && fileInput) {
    selectBtn.addEventListener('click', function (e) {
      e.preventDefault();
      // Only allow if input is enabled
      if (fileInput.disabled) return;
      fileInput.click();
    });

    fileInput.addEventListener('change', function () {
      if (!selectedFilesSpan) return;
      const files = Array.from(fileInput.files || []);
      if (files.length === 0) {
        selectedFilesSpan.textContent = '';
        return;
      }
      // show up to 3 filenames, then indicate count
      const names = files.slice(0, 3).map(f => f.name);
      let text = names.join(', ');
      if (files.length > 3) text += ' (+' + (files.length - 3) + ' more)';
      selectedFilesSpan.textContent = text;
    });
  }
});

// toggle inputs to readonly/disabled or enabled
function setReadOnlyMode(readOnly) {
  const form = document.getElementById("recordForm");
  if (!form) return;
  const inputs = form.querySelectorAll("input, textarea, select, button.save-btn");
  inputs.forEach(el => {
    // Keep Save button always enabled only when editing enabled
    if (el.classList && el.classList.contains('save-btn')) {
      el.disabled = readOnly;
      return;
    }
    // Exclude the enableEditBtn itself from being disabled
    if (el.id === 'enableEditBtn') return;
    // Input elements can be disabled
    if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT') {
      el.disabled = readOnly;
    }
  });
  isEditingEnabled = !readOnly;
}

// Called by the "Enable Edit" button inside modal
function enableEditing() {
  // Only allow if server indicated user can modify
  if (!window.canModify) {
    alert("You do not have permission to edit this record.");
    return;
  }
  setReadOnlyMode(false);
  const enableBtn = document.getElementById("enableEditBtn");
  if (enableBtn) enableBtn.style.display = "none";
}

// close modal
function closeModal() {
  const modal = document.getElementById("recordModal");
  if (modal) modal.style.display = "none";
}

// ====================== BOOKMARKS ======================
function toggleSave(recordId) {
  fetch('toggle_save.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'record_id=' + encodeURIComponent(recordId)
  })
    .then(response => response.json())
    .then(data => {
      const icon = document.querySelector(`#bookmark-${recordId} i`);
      if (data.status === 'saved') {
        if (icon) { icon.classList.remove('bx-bookmarks'); icon.classList.add('bxs-bookmarks'); }
      } else if (data.status === 'removed') {
        if (icon) { icon.classList.remove('bxs-bookmarks'); icon.classList.add('bx-bookmarks'); }
      }
    })
    .catch(err => console.error('Bookmark error:', err));
}

// ====================== DELETE ======================
function deleteRecord(code) {
  if (confirm("Are you sure you want to delete this record?")) {
    window.location.href = "delete_record.php?record_id=" + encodeURIComponent(code);
  }
}

// ✅ Auto-filter table if search query is present from homepage
window.addEventListener('DOMContentLoaded', () => {
  const initInput = document.getElementById('searchInput');
  const initField = document.getElementById('searchField');
  const initRows = document.getElementById('rowsPerPage');
  const initSort = document.getElementById('sortOrder');
  const initSort2 = document.getElementById('sortOrder2');

  if (initInput) currentSearchInput = initInput.value.trim();
  if (initField) currentSearchField = initField.value || 'all';
  if (initRows) rowsPerPage = initRows.value === 'all' ? 'all' : parseInt(initRows.value);
  if (initSort) currentSortOrder = initSort.value || currentSortOrder;
  if (initSort2) currentSortOrder2 = initSort2.value || '';

  if (currentSearchInput.length > 0) {
    fetchRecords(1);
  }

  if (initInput) initInput.addEventListener('input', filterTable);
  if (initField) initField.addEventListener('change', filterTable);
});

// ====================== INITIALIZE ======================
window.onload = () => {
  fetchRecords(1);
};
function enableEditing() {
  const inputs = document.querySelectorAll('#recordForm input, #recordForm textarea, #recordForm select');
  inputs.forEach(el => el.removeAttribute('readonly'));
  document.getElementById('action').value = 'edit';
  alert('Edit mode enabled.');
}

function openModal(viewOnly = false) {
  document.getElementById('recordModal').style.display = 'flex';
  const inputs = document.querySelectorAll('#recordForm input, #recordForm textarea, #recordForm select');
  inputs.forEach(el => el.readOnly = viewOnly);
  document.getElementById('enableEditBtn').style.display = viewOnly ? 'inline-block' : 'none';
}

function closeModal() {
  document.getElementById('recordModal').style.display = 'none';
}
function enableEditing() {
  setReadOnlyMode(false); // make all fields editable
  document.getElementById("enableEditBtn").style.display = "none"; // hide the button
}
function setReadOnlyMode(isReadOnly) {
  const formElements = document.querySelectorAll("#recordForm input, #recordForm textarea, #recordForm select");
  formElements.forEach(el => {
    el.disabled = isReadOnly;
    el.readOnly = isReadOnly;
  });
}
function enableEditing() {
  console.log("Enable Edit button clicked!");
  document.getElementById('saveModalBtn').style.display = 'block';

  // Select all form elements
  const elements = document.querySelectorAll('#recordForm input, #recordForm textarea, #recordForm select, #recordForm button.save-btn');

  elements.forEach(el => {
    console.log(el.id || el.className, "disabled:", el.disabled, "readonly:", el.readOnly);

    // Re-enable all inputs, selects, textareas, and Save button
    el.disabled = false;
    el.readOnly = false;
    el.removeAttribute('disabled');
    el.removeAttribute('readonly');
    el.classList.remove('disabled');
  });

  // Enable Save button explicitly (in case it was still disabled)
  const saveBtn = document.querySelector('#recordForm button.save-btn');
  if (saveBtn) {
    saveBtn.disabled = false;
  }

  // Set action to edit
  const actionField = document.getElementById('action');
  if (actionField) actionField.value = 'edit';

  // Hide Enable Edit button
  const enableBtn = document.getElementById('enableEditBtn');
  if (enableBtn) enableBtn.style.display = 'none';
  // mark editing enabled globally so other UI (delete buttons) can react
  isEditingEnabled = true;

  // enable any per-attachment delete buttons that were rendered disabled
  document.querySelectorAll('.attachment-delete-btn').forEach(b => {
    b.disabled = false;
    b.title = '';
  });

  // show remove-all label when editing enabled
  const removeLabel = document.getElementById('removeAttachmentLabel');
  if (removeLabel) removeLabel.style.display = 'inline-block';

  // show file input for attachments when editing enabled
  const fileInput = document.getElementById('attachment_file');
  if (fileInput) fileInput.style.display = 'inline-block';

  alert("Edit mode enabled!");
}
