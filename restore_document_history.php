<?php
session_start();
include 'connect.php';
include_once 'rolePermissionHandler.php';
include_once 'log_activity.php';
// Only admins may restore
require_admin();

// Helper: perform restore for a single history id. Returns array with success and message.
function restore_single($conn, $id, $postAction = null)
{
    // Fetch history row
    $stmt = $conn->prepare("SELECT * FROM document_history WHERE id = ? LIMIT 1");
    if (!$stmt)
        return ['success' => false, 'error' => 'Prepare failed'];
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row)
        return ['success' => false, 'error' => 'Entry not found', 'id' => $id];

    $data = [
        'record_id' => $row['record_id'] ?? null,
        'access_code' => $row['access_code'] ?? null,
        'library' => $row['library'] ?? null,
        'location_code' => $row['location_code'] ?? null,
        'author' => $row['author'] ?? null,
        'department' => $row['department'] ?? null,
        'title' => $row['title'] ?? null,
        'type' => $row['type'] ?? null,
        'record_status' => $row['record_status'] ?? null,
        'retention_period' => $row['retention_period'] ?? null,
        'description' => $row['description'] ?? null,
        'document_number' => $row['document_number'] ?? null,
        'supplier' => $row['supplier'] ?? null,
        'amount_due' => $row['amount_due'] ?? null,
        'notes_keywords' => $row['notes_keywords'] ?? null,
        'addressee' => $row['addressee'] ?? null,
        'accountable_officer' => $row['accountable_officer'] ?? null,
        'sender' => $row['sender'] ?? null,
        'source_funds' => $row['source_funds'] ?? null,
        'property_number' => $row['property_number'] ?? null,
        'total_cost' => $row['total_cost'] ?? null,
        'attachment' => $row['attachment'] ?? null,
        'status' => $row['status'] ?? null,
        'date_created' => $row['date_created'] ?? null,
        'date_modified' => date('Y-m-d H:i:s')
    ];
    // Check if a record exists. Prefer matching by record_id, otherwise fall back to access_code.
    $existing = null;
    $existingKey = null; // 'record_id' or 'access_code'
    if (!empty($data['record_id'])) {
        $rid = intval($data['record_id']);
        $chk = $conn->prepare("SELECT * FROM document_records WHERE record_id = ? LIMIT 1");
        if ($chk) {
            $chk->bind_param('i', $rid);
            $chk->execute();
            $existing = $chk->get_result()->fetch_assoc();
            if ($existing)
                $existingKey = 'record_id';
            $chk->close();
        }
    }

    if (!$existing && !empty($data['access_code'])) {
        $acc = $data['access_code'];
        $chk = $conn->prepare("SELECT * FROM document_records WHERE access_code = ? LIMIT 1");
        if ($chk) {
            $chk->bind_param('s', $acc);
            $chk->execute();
            $existing = $chk->get_result()->fetch_assoc();
            if ($existing)
                $existingKey = 'access_code';
            $chk->close();
        }
    }

    // Determine action: 'overwrite' to replace existing by access_code, 'insert' to insert new (or update by record_id)
    $action = $postAction ?? 'auto';

    // If there's an existing record and no explicit action, return conflict details so the client
    // can decide whether to overwrite. Include both the deleted (history) and existing records.
    if ($existing && $action === 'auto') {
        return ['success' => false, 'error' => 'conflict', 'conflict' => true, 'existing' => $existing, 'deleted' => $row, 'id' => $id];
    }

    $dbOk = false;

    // If overwrite requested, ensure an existing record is actually present and then update it
    if ($action === 'overwrite') {
        if (!$existing) {
            return ['success' => false, 'error' => 'No existing record found to overwrite.'];
        }
        // Update by the key that actually matched
        if ($existingKey === 'record_id') {
            $stmt = $conn->prepare("UPDATE document_records SET access_code=?, library=?, location_code=?, author=?, department=?, title=?, type=?, record_status=?, retention_period=?, description=?, document_number=?, supplier=?, amount_due=?, notes_keywords=?, addressee=?, accountable_officer=?, sender=?, source_funds=?, property_number=?, total_cost=?, attachment=?, status=?, date_created=?, date_modified=? WHERE record_id=?");
            $stmt->bind_param(
                "ssssssssssssssssssssssssi",
                $data['access_code'],
                $data['library'],
                $data['location_code'],
                $data['author'],
                $data['department'],
                $data['title'],
                $data['type'],
                $data['record_status'],
                $data['retention_period'],
                $data['description'],
                $data['document_number'],
                $data['supplier'],
                $data['amount_due'],
                $data['notes_keywords'],
                $data['addressee'],
                $data['accountable_officer'],
                $data['sender'],
                $data['source_funds'],
                $data['property_number'],
                $data['total_cost'],
                $data['attachment'],
                $data['status'],
                $data['date_created'],
                $data['date_modified'],
                $data['record_id']
            );
            $logAction = 'Restore - Overwrite by record_id';
        } else {
            // existingKey === 'access_code' or fallback
            $stmt = $conn->prepare("UPDATE document_records SET record_id=?, library=?, location_code=?, author=?, department=?, title=?, type=?, record_status=?, retention_period=?, description=?, document_number=?, supplier=?, amount_due=?, notes_keywords=?, addressee=?, accountable_officer=?, sender=?, source_funds=?, property_number=?, total_cost=?, attachment=?, status=?, date_created=?, date_modified=? WHERE access_code=?");
            $stmt->bind_param(
                "issssssssssssssssssssssss",
                $data['record_id'],
                $data['library'],
                $data['location_code'],
                $data['author'],
                $data['department'],
                $data['title'],
                $data['type'],
                $data['record_status'],
                $data['retention_period'],
                $data['description'],
                $data['document_number'],
                $data['supplier'],
                $data['amount_due'],
                $data['notes_keywords'],
                $data['addressee'],
                $data['accountable_officer'],
                $data['sender'],
                $data['source_funds'],
                $data['property_number'],
                $data['total_cost'],
                $data['attachment'],
                $data['status'],
                $data['date_created'],
                $data['date_modified'],
                $data['access_code']
            );
            $logAction = 'Restore - Overwrite by access_code';
        }
        $dbOk = $stmt->execute();
        $stmt->close();
    } else {
        // No overwrite: proceed with previous logic (update by id or insert)
        $existsById = false;
        if (!empty($data['record_id'])) {
            $chk2 = $conn->prepare("SELECT 1 FROM document_records WHERE record_id = ? LIMIT 1");
            $chk2->bind_param('i', $data['record_id']);
            $chk2->execute();
            $res2 = $chk2->get_result();
            $existsById = $res2 && $res2->num_rows > 0;
            $chk2->close();
        }

        if ($existsById) {
            $stmt = $conn->prepare("UPDATE document_records SET access_code=?, library=?, location_code=?, author=?, department=?, title=?, type=?, record_status=?, retention_period=?, description=?, document_number=?, supplier=?, amount_due=?, notes_keywords=?, addressee=?, accountable_officer=?, sender=?, source_funds=?, property_number=?, total_cost=?, attachment=?, status=?, date_modified=? WHERE record_id=?");
            $stmt->bind_param(
                "sssssssssssssssssssssssi",
                $data['access_code'],
                $data['library'],
                $data['location_code'],
                $data['author'],
                $data['department'],
                $data['title'],
                $data['type'],
                $data['record_status'],
                $data['retention_period'],
                $data['description'],
                $data['document_number'],
                $data['supplier'],
                $data['amount_due'],
                $data['notes_keywords'],
                $data['addressee'],
                $data['accountable_officer'],
                $data['sender'],
                $data['source_funds'],
                $data['property_number'],
                $data['total_cost'],
                $data['attachment'],
                $data['status'],
                $data['date_modified'],
                $data['record_id']
            );
            $dbOk = $stmt->execute();
            $stmt->close();
            $logAction = 'Restore - Update by id';
        } else {
            // Insert new record
            if (!empty($data['record_id'])) {
                $stmt = $conn->prepare("INSERT INTO document_records (record_id, access_code, library, location_code, author, department, title, type, record_status, retention_period, description, document_number, supplier, amount_due, notes_keywords, addressee, accountable_officer, sender, source_funds, property_number, total_cost, attachment, status, date_created, date_modified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param(
                    "issssssssssssssssssssssss",
                    $data['record_id'],
                    $data['access_code'],
                    $data['library'],
                    $data['location_code'],
                    $data['author'],
                    $data['department'],
                    $data['title'],
                    $data['type'],
                    $data['record_status'],
                    $data['retention_period'],
                    $data['description'],
                    $data['document_number'],
                    $data['supplier'],
                    $data['amount_due'],
                    $data['notes_keywords'],
                    $data['addressee'],
                    $data['accountable_officer'],
                    $data['sender'],
                    $data['source_funds'],
                    $data['property_number'],
                    $data['total_cost'],
                    $data['attachment'],
                    $data['status'],
                    $data['date_created'],
                    $data['date_modified']
                );
            } else {
                $stmt = $conn->prepare("INSERT INTO document_records (access_code, library, location_code, author, department, title, type, record_status, retention_period, description, document_number, supplier, amount_due, notes_keywords, addressee, accountable_officer, sender, source_funds, property_number, total_cost, attachment, status, date_created, date_modified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param(
                    "ssssssssssssssssssssssss",
                    $data['access_code'],
                    $data['library'],
                    $data['location_code'],
                    $data['author'],
                    $data['department'],
                    $data['title'],
                    $data['type'],
                    $data['record_status'],
                    $data['retention_period'],
                    $data['description'],
                    $data['document_number'],
                    $data['supplier'],
                    $data['amount_due'],
                    $data['notes_keywords'],
                    $data['addressee'],
                    $data['accountable_officer'],
                    $data['sender'],
                    $data['source_funds'],
                    $data['property_number'],
                    $data['total_cost'],
                    $data['attachment'],
                    $data['status'],
                    $data['date_created'],
                    $data['date_modified']
                );
            }
            $dbOk = $stmt->execute();
            $stmt->close();
            $logAction = 'Restore - Insert';
        }
    }

    // Log the restore action
    logActivity(
        $data['record_id'],
        $data['access_code'],
        $data['library'],
        $data['location_code'],
        $data['author'],
        $data['department'],
        $data['title'],
        $data['type'],
        $data['record_status'],
        $data['retention_period'],
        $data['description'],
        $data['document_number'],
        $data['supplier'],
        $data['amount_due'],
        $data['notes_keywords'],
        $data['addressee'],
        $data['accountable_officer'],
        $data['sender'],
        $data['source_funds'],
        $data['property_number'],
        $data['total_cost'],
        $data['attachment'],
        // attachments (if present in history row)
        $data['attachments'] ?? null,
        $data['status'],
        $data['date_created'],
        $logAction
    );

    // If the database operation succeeded, remove the record from document_history
    if ($dbOk) {
        $del = $conn->prepare("DELETE FROM document_history WHERE id = ?");
        if ($del) {
            $del->bind_param('i', $id);
            $del->execute();
            $del->close();
        }
    }

    return ['success' => (bool) $dbOk, 'id' => $id];
}

// Main entry: support JSON bulk or legacy single-id
$input = null;
if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
}

if ($input && isset($input['ids']) && is_array($input['ids'])) {
    $ids = array_values(array_map('intval', $input['ids']));
    // Map client bulk action names to the underlying single-action parameter
    $requestedAction = $input['action'] ?? null;
    $mappedAction = null;
    if ($requestedAction === 'bulk_restore')
        $mappedAction = 'auto';
    elseif ($requestedAction === 'bulk_overwrite')
        $mappedAction = 'overwrite';
    elseif ($requestedAction === 'bulk_insert')
        $mappedAction = 'insert';
    else
        $mappedAction = $requestedAction; // allow direct 'overwrite' etc.

    $results = ['restored' => [], 'failed' => []];
    foreach ($ids as $iid) {
        $res = restore_single($conn, $iid, $mappedAction);
        if ($res['success']) {
            $results['restored'][] = $iid;
        } else {
            // include any returned details (error/conflict/existing/deleted)
            $failedItem = ['id' => $iid, 'error' => $res['error'] ?? 'failed'];
            if (isset($res['conflict']) && $res['conflict']) {
                $failedItem['conflict'] = true;
                $failedItem['existing'] = $res['existing'] ?? null;
                $failedItem['deleted'] = $res['deleted'] ?? null;
            }
            $results['failed'][] = $failedItem;
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => count($results['failed']) === 0, 'results' => $results]);
    exit;
}

// Legacy single-id handling (POST/GET)
$id = null;
if (isset($_POST['id']) && is_numeric($_POST['id'])) {
    $id = intval($_POST['id']);
} elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);
}

if (is_null($id)) {
    header('Location: trash_bin.php');
    exit;
}

$action = $_POST['action'] ?? ($_GET['action'] ?? 'auto');
$res = restore_single($conn, $id, $action);
if (!$res['success']) {
    echo "<script>alert('" . addslashes($res['error'] ?? 'Restore failed') . "'); window.location='trash_bin.php';</script>";
    exit;
}

echo "<script>alert('Record restored successfully.'); window.location.href='trash_bin.php';</script>";
exit;

?>