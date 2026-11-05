<?php

// === Utility Functions ===
include_once 'file_utils.php';
function logActivity(
    $record_id,
    $access_code,
    $library,
    $location_code,
    $author,
    $department,
    $title,
    $type,
    $record_status,
    $retention_period,
    $description,
    $document_number,
    $supplier,
    $amount_due,
    $notes_keywords,
    $addressee,
    $accountable_officer,
    $sender,
    $source_funds,
    $property_number,
    $total_cost,
    $attachment,
    $attachments,
    $status,
    $date_created,
    $action
) {
    include 'connect.php';

    // Ensure session is available so we can capture the current user's username as the encoder
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $encoder = isset($_SESSION['username']) ? $_SESSION['username'] : null;

    // Ensure `attachments` column exists (best-effort migration fallback).
    // This keeps older DBs compatible and avoids hard failures when logging attachments.
    @$conn->query("ALTER TABLE activity_history ADD COLUMN IF NOT EXISTS attachments LONGTEXT NULL");

    // Insert with encoder column (captures current session username)
    // Include `attachments` (JSON) column so attachment lists are stored alongside the full record snapshot.
    $stmt = $conn->prepare("INSERT INTO activity_history (
        record_id, access_code, library, location_code, author, department, title, type,
        record_status, retention_period, description, document_number, supplier, amount_due,
        notes_keywords, addressee, accountable_officer, sender, source_funds, property_number,
        total_cost, attachment, attachments, status, date_created, encoder, action, timestamp
    ) VALUES (
        ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, ?,
        NOW()
    )");
    // ✅ 27 parameters = 1 integer + 26 strings (encoder included)
    $stmt->bind_param(
        "issssssssssssssssssssssssss",
        $record_id,
        $access_code,
        $library,
        $location_code,
        $author,
        $department,
        $title,
        $type,
        $record_status,
        $retention_period,
        $description,
        $document_number,
        $supplier,
        $amount_due,
        $notes_keywords,
        $addressee,
        $accountable_officer,
        $sender,
        $source_funds,
        $property_number,
        $total_cost,
        $attachment,
        $attachments,
        $status,
        $date_created,
        $encoder,
        $action
    );

    $stmt->execute();
    $stmt->close();
}

function isDuplicateAccessCode($conn, $table, $access_code)
{
    $count = 0;
    $stmt = $conn->prepare("SELECT COUNT(*) FROM $table WHERE access_code = ?");
    $stmt->bind_param("s", $access_code);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count > 0;
}

function getRecordDetails($conn, $table, $record_id)
{
    $stmt = $conn->prepare("SELECT access_code, library, location_code, author, department, title, type, record_status, retention_period FROM $table WHERE record_id = ?");
    $stmt->bind_param("i", $record_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row ?: [
        'access_code' => null,
        'library' => null,
        'location_code' => null,
        'author' => null,
        'department' => null,
        'title' => null,
        'type' => null,
        'record_status' => null,
        'retention_period' => null
    ];
}

function insertRecord($conn, $table, $data)
{
    // Ensure author is formatted consistently
    if (is_array($data)) {
        $data['author'] = isset($data['author']) ? format_author($data['author']) : '';
    }
    $stmt = $conn->prepare("INSERT INTO $table (
        access_code, library, location_code, author, department, title, type, record_status,
        retention_period, description, document_number, supplier, amount_due, notes_keywords,
        addressee, accountable_officer, sender, source_funds, property_number, total_cost,
        attachment, attachment_name, status, date_created, date_modified
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

    // 25 string parameters for the 25 columns above
    $stmt->bind_param(
        "sssssssssssssssssssssssss",
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
        $data['attachment_name'],
        $data['status'],
        $data['date_created'],
        $data['date_modified']
    );

    $stmt->execute();
    $record_id = $conn->insert_id;
    $stmt->close();
    return $record_id;
}

function updateRecord($conn, $table, $data)
{
    // Ensure author is formatted consistently
    if (is_array($data)) {
        $data['author'] = isset($data['author']) ? format_author($data['author']) : '';
    }
    $stmt = $conn->prepare("UPDATE $table SET
        access_code=?, library=?, location_code=?, author=?, department=?, title=?, type=?,
        record_status=?, retention_period=?, description=?, document_number=?, supplier=?,
        amount_due=?, notes_keywords=?, addressee=?, accountable_officer=?, sender=?,
        source_funds=?, property_number=?, total_cost=?, attachment=?, attachment_name=?, status=?, date_modified=?
        WHERE record_id=?");

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
        $data['attachment_name'],
        $data['status'],
        $data['date_modified'],
        $data['record_id']
    );

    $stmt->execute();
    $stmt->close();
}

function processActivityLog($conn, $table, $record_id, $data, $action)
{
    if (is_array($data)) {
        // If $data is a numeric-indexed array (a list) it's a bulk operation and we skip logging.
        // If it's an associative array (single record) we should proceed and log it.
        $is_list = array_values($data) === $data;
        if ($is_list) {
            return; // bulk operation: don't log here
        }
        // otherwise it's an associative array representing a single record — continue
    }
    $record = getRecordDetails($conn, $table, $record_id);
    logActivity(
        $record_id,
        // Prefer values from $data (undo/redo payload) when present; otherwise fall back to DB lookup
        $data['access_code'] ?? $record['access_code'] ?? null,
        $data['library'] ?? $record['library'] ?? null,
        $data['location_code'] ?? $record['location_code'] ?? null,
        $data['author'] ?? $record['author'] ?? null,
        $data['department'] ?? $record['department'] ?? null,
        $data['title'] ?? $record['title'] ?? null,
        $data['type'] ?? $record['type'] ?? null,
        $data['record_status'] ?? $record['record_status'] ?? null,
        $data['retention_period'] ?? $record['retention_period'] ?? null,
        $data['description'] ?? null,
        $data['document_number'] ?? null,
        $data['supplier'] ?? null,
        $data['amount_due'] ?? null,
        $data['notes_keywords'] ?? null,
        $data['addressee'] ?? null,
        $data['accountable_officer'] ?? null,
        $data['sender'] ?? null,
        $data['source_funds'] ?? null,
        $data['property_number'] ?? null,
        $data['total_cost'] ?? null,
        $data['attachment_name'] ?? $data['attachment'] ?? null,
        // attachments JSON (list of record_attachments) when provided
        $data['attachments'] ?? null,
        $data['status'] ?? null,
        $data['date_created'] ?? null,
        $action
    );
}

// History logging (undo/redo) removed per client request.
function logDocumentHistory($conn, $record_id, $operation, $old_data, $new_data)
{
    // no-op: undo/redo history storage has been disabled
    return;
}