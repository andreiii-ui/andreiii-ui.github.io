<?php
include 'connect.php';
include_once 'log_activity.php';
session_start();
include_once 'rolePermissionHandler.php';
// Correct include path: the helper is in the project root, not in "Implement/"
include_once 'document_history_helper.php';

// Admins and encoders can delete records
require_modifier();

$ids_str = $_GET['record_ids'] ?? '';
$id = $_GET['record_id'] ?? '';
$ids = [];

if ($ids_str)
    $ids = explode(',', $ids_str);
elseif ($id)
    $ids = [$id];

if (!empty($ids)) {
    $records_to_delete = [];

    foreach ($ids as $record_id) {
        $record_id = (int) $record_id;
        // Fetch record
        $fetch = $conn->prepare("SELECT * FROM document_records WHERE record_id = ?");
        if (!$fetch)
            die("Fetch prepare failed: " . $conn->error);
        $fetch->bind_param("i", $record_id);
        $fetch->execute();
        $result = $fetch->get_result();
        $record = $result->fetch_assoc();
        $fetch->close();

        if ($record) {
            $records_to_delete[] = $record;
            // Persist deleted record into document_history (best-effort)
            if (function_exists('saveDeletedRecord')) {
                @saveDeletedRecord($conn, $record);
            }

            // Log activity
            logActivity(
                $record_id,
                $record['access_code'],
                $record['library'],
                $record['location_code'],
                $record['author'],
                $record['department'],
                $record['title'],
                $record['type'],
                $record['record_status'],
                $record['retention_period'],
                $record['description'],
                $record['document_number'],
                $record['supplier'],
                $record['amount_due'],
                $record['notes_keywords'],
                $record['addressee'],
                $record['accountable_officer'],
                $record['sender'],
                $record['source_funds'],
                $record['property_number'],
                $record['total_cost'],
                $record['attachment'],
                // attachments column (if present)
                $record['attachments'] ?? null,
                $record['status'],
                $record['date_created'],
                'delete'
            );
        }
    }

    // Delete from main table
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("DELETE FROM document_records WHERE record_id IN ($placeholders)");
    if (!$stmt)
        die("Delete prepare failed: " . $conn->error);

    $types = str_repeat('i', count($ids));
    // bind params by reference for variable-length argument list
    $intIds = array_map('intval', $ids);
    $bind_params = [];
    foreach ($intIds as $k => $v) {
        // ensure we have variables to pass by reference
        $bind_params[$k] = $v;
    }
    array_unshift($bind_params, $types);
    $refs = [];
    foreach ($bind_params as $key => $value) {
        $refs[$key] = &$bind_params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0) {
        echo "<script>alert('$affected record(s) deleted and saved to document_history.'); window.location='Searchpage.php';</script>";
    } else {
        echo "<script>alert('No records found.'); window.location='Searchpage.php';</script>";
    }

} else {
    echo "<script>alert('Missing record ID(s)'); window.location='Searchpage.php';</script>";
}
?>