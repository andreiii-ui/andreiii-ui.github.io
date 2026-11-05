let selectionMode = false;

// === Toggle selection mode ===
function toggleSelectionMode() {
    selectionMode = document.getElementById('selectionModeCheckbox').checked;
    document.querySelectorAll('.select-checkbox').forEach(cb => {
        cb.style.display = selectionMode ? 'inline-block' : 'none';
        cb.checked = false;
    });
    document.getElementById('selectAllCheckbox').style.display = selectionMode ? 'inline-block' : 'none';
    document.getElementById('deleteSelectedBtn').style.display = selectionMode ? 'inline-block' : 'none';
}

// === Select all checkboxes ===
function selectAll() {
    const checked = document.getElementById('selectAllCheckbox').checked;
    document.querySelectorAll('.select-checkbox').forEach(cb => cb.checked = checked);
}

// === Delete selected records ===
document.getElementById('deleteSelectedBtn').addEventListener('click', function () {
    const selected = Array.from(document.querySelectorAll('.select-checkbox:checked'))
        .map(cb => cb.dataset.id);

    if (selected.length === 0) {
        alert('No records selected.');
        return;
    }

    if (!confirm(`Delete ${selected.length} selected saved entries?`)) return;

    fetch('delete_saved.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ids=' + encodeURIComponent(selected.join(',')),
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            selected.forEach(id => {
                const tr = document.querySelector(`tr[data-record-id="${id}"]`);
                if (tr) tr.remove();
            });
            alert(data.message);
        } else {
            alert(data.error || 'Failed to delete selected records.');
        }
    })
    .catch(err => {
        console.error('Delete error:', err);
        alert('Network error or server error. Check console.');
    });
});

// === Save / unsave selected ===
function savedSelected() {
    const selectedIds = Array.from(document.querySelectorAll('.select-checkbox:checked'))
        .map(cb => cb.dataset.id);

    if (selectedIds.length === 0) {
        alert('No records selected.');
        return;
    }

    fetch('toggle_save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'record_ids=' + encodeURIComponent(selectedIds.join(',')),
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            selectedIds.forEach(id => {
                const icon = document.querySelector(`#bookmark-${id} i`);
                if (!icon) return;
                if (data.saved.includes(id)) {
                    icon.classList.remove('bx-bookmarks');
                    icon.classList.add('bxs-bookmarks');
                } else if (data.removed.includes(id)) {
                    icon.classList.remove('bxs-bookmarks');
                    icon.classList.add('bx-bookmarks');
                }
            });
            alert(data.message || 'Selected records updated.');
        } else {
            alert(data.error || 'An error occurred.');
        }
    })
    .catch(err => console.error('Saved Selected error:', err));
}

// === Toggle unsave single record ===
function toggleUnsave(recordId) {
    if (!confirm('Remove this record from saved files?')) return;

    fetch('toggle_save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'record_id=' + encodeURIComponent(recordId),
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'removed') {
            const tr = document.querySelector(`tr[data-record-id="${recordId}"]`);
            if (tr) tr.remove();
        } else alert('Error removing record.');
    })
    .catch(e => { console.error(e); alert('Network error.'); });
}

// === Field Visibility ===
const dropdown = document.getElementById('fieldDropdown');
dropdown.querySelector('button').addEventListener('click', () => dropdown.classList.toggle('show'));
window.addEventListener('click', function (e) { if (!dropdown.contains(e.target)) dropdown.classList.remove('show'); });

document.querySelectorAll('.display-field').forEach(cb => {
    cb.addEventListener('change', () => {
        const field = cb.dataset.field;
        const show = cb.checked;
        document.querySelectorAll(`th[data-field="${field}"], td[data-field="${field}"]`).forEach(el => {
            el.style.display = show ? '' : 'none';
        });
    });
});

// === Export Function ===
function exportData() {
    const format = document.getElementById('exportFormat').value;
    if (!format) return;

    const table = document.getElementById('savedTable');
    const headers = Array.from(table.querySelectorAll('thead th'))
        .map((th, index) => th.style.display !== 'none' ? index : -1)
        .filter(i => i !== -1);

    const data = Array.from(table.querySelectorAll('tr')).map(row => {
        const cells = Array.from(row.querySelectorAll('th, td'));
        return headers.map(i => cells[i]?.innerText.trim() ?? '');
    }).filter(r => r.length > 0);

    if (data.length === 0) { alert("No data available to export!"); return; }

    if (format === 'csv') {
        const csv = data.map(r => r.map(c => `"${c.replace(/"/g, '""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'saved_files.csv';
        a.click();
    } else if (format === 'excel') {
        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.aoa_to_sheet(data);
        XLSX.utils.book_append_sheet(wb, ws, 'SavedFiles');
        XLSX.writeFile(wb, 'saved_files.xlsx');
    } else if (format === 'pdf') {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('landscape');
        doc.autoTable({
            head: [data[0]],
            body: data.slice(1),
            startY: 10,
            styles: { fontSize: 9, cellPadding: 2 },
            headStyles: { fillColor: [230, 81, 0] },
            alternateRowStyles: { fillColor: [255, 245, 235] }
        });
        doc.save('saved_files.pdf');
    }

    document.getElementById('exportFormat').value = '';
}
