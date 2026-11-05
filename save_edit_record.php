<?php
// previously "save_record.php", change it to "save_edit_record.php"
// in Searchpage.php <form id="recordForm" action="save_edit_record.php" method="POST">

include 'connect.php';
include 'log_activity.php';
session_start();
include_once 'rolePermissionHandler.php';
include_once 'file_utils.php';
// Admins or encoders can add/edit via this endpoint
require_modifier();

// === Main Logic ===

$action = $_POST['action'] ?? '';
$table = 'document_records';
$data = $_POST;
$data['date_modified'] = date('Y-m-d H:i:s');

// Ensure attachment_name column exists (migration helper). If it doesn't exist, try to add it.
$colCheck = $conn->query("SHOW COLUMNS FROM $table LIKE 'attachment_name'");
if ($colCheck && $colCheck->num_rows === 0) {
    // Try to add column; ignore failure (may lack privileges)
    $conn->query("ALTER TABLE $table ADD COLUMN attachment_name VARCHAR(255) NULL");
}

// Helper: convert the $_FILES['attachment_files'] structure into a list of files
function restructure_files_array($file_post)
{
    $files = [];
    if (!is_array($file_post) || !isset($file_post['name']))
        return $files;
    $count = is_array($file_post['name']) ? count($file_post['name']) : 0;
    $keys = array_keys($file_post);
    for ($i = 0; $i < $count; $i++) {
        $file = [];
        foreach ($keys as $k) {
            $file[$k] = $file_post[$k][$i];
        }
        $files[] = $file;
    }
    return $files;
}

// try_unlink_upload provided by shared file_utils.php

if ($action === 'add') {
    if (isDuplicateAccessCode($conn, $table, $data['access_code'])) {
        echo "<script>alert('Error: A record with this Access Code already exists. Please use a unique Access Code.'); window.history.back();</script>";
        exit;
    }

    // Insert record first (attachments will be stored in record_attachments table)
    $data['attachment'] = $data['attachment'] ?? '';
    $data['attachment_name'] = $data['attachment_name'] ?? '';
    $record_id = insertRecord($conn, $table, $data);

    // Ensure attachments table exists (best-effort migration fallback)
    $conn->query("CREATE TABLE IF NOT EXISTS record_attachments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        record_id INT NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NULL,
        uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (record_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Handle multiple file uploads (input name: attachment_files[])
    if (isset($_FILES['attachment_files'])) {
        $uploadDir = __DIR__ . '/uploads';
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0755, true);

        $files = restructure_files_array($_FILES['attachment_files']);
        $firstPath = null;
        $firstName = null;
        // Flag to know whether we actually added any attachments in this request
        $attachments_added = false;
        $insertStmt = $conn->prepare("INSERT INTO record_attachments (record_id, file_path, original_name, uploaded_at) VALUES (?, ?, ?, NOW())");
        foreach ($files as $f) {
            if ($f['error'] !== UPLOAD_ERR_OK)
                continue;
            $origName = basename($f['name']);
            $ext = pathinfo($origName, PATHINFO_EXTENSION);
            try {
                $storedName = time() . '_' . bin2hex(random_bytes(6)) . ($ext ? '.' . $ext : '');
            } catch (Exception $e) {
                $storedName = time() . '_' . bin2hex(openssl_random_pseudo_bytes(6)) . ($ext ? '.' . $ext : '');
            }
            $targetPath = $uploadDir . '/' . $storedName;
            if (move_uploaded_file($f['tmp_name'], $targetPath)) {
                $filePath = 'uploads/' . $storedName;
                $insertStmt->bind_param('iss', $record_id, $filePath, $origName);
                $insertStmt->execute();
                $attachments_added = true;
                if ($firstPath === null) {
                    $firstPath = $filePath;
                    $firstName = $origName;
                }
            }
        }
        if (isset($insertStmt))
            $insertStmt->close();

        // If we uploaded at least one file, update document_records legacy attachment fields for compatibility
        if ($firstPath !== null) {
            $u = $conn->prepare("UPDATE $table SET attachment = ?, attachment_name = ? WHERE record_id = ?");
            $u->bind_param('ssi', $firstPath, $firstName, $record_id);
            $u->execute();
            $u->close();
        }

        // If attachments were added, insert a minimal activity_history snapshot for attachments
        if (!empty($attachments_added)) {
            // Fetch current record access_code for context
            $recStmt = $conn->prepare("SELECT record_id, access_code, attachment FROM document_records WHERE record_id = ? LIMIT 1");
            $recRow = null;
            if ($recStmt) {
                $recStmt->bind_param('i', $record_id);
                $recStmt->execute();
                $resR = $recStmt->get_result();
                if ($resR && $resR->num_rows > 0) {
                    $recRow = $resR->fetch_assoc();
                }
                $recStmt->close();
            }

            // gather attachments metadata
            $atts = [];
            $raQ = $conn->prepare("SELECT id, file_path, original_name, uploaded_at FROM record_attachments WHERE record_id = ? ORDER BY uploaded_at DESC");
            if ($raQ) {
                $raQ->bind_param('i', $record_id);
                $raQ->execute();
                $raRes = $raQ->get_result();
                while ($ar = $raRes->fetch_assoc()) {
                    $atts[] = [
                        'id' => (int) $ar['id'],
                        'file_path' => $ar['file_path'],
                        'original_name' => $ar['original_name'],
                        'uploaded_at' => $ar['uploaded_at'] ?? null
                    ];
                }
                $raQ->close();
            }

            if ($recRow) {
                // Build attachments JSON and call the central activity logger so the full record snapshot
                // (all fields) is recorded alongside the attachments list.
                $attachments_json = count($atts) ? json_encode($atts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
                $rec_id = (int) $recRow['record_id'];

                $data_for_log = $data;
                $data_for_log['attachments'] = $attachments_json;

                // Log a snapshot with action 'add-attachment'
                processActivityLog($conn, $table, $rec_id, $data_for_log, 'add-attachment');
            }
        }
    }

    processActivityLog($conn, $table, $record_id, $data, $action);
    echo "<script>alert('Record added successfully'); window.location='Searchpage.php';</script>";

} elseif ($action === 'edit') {
    $data['record_id'] = $_POST['record_id'];

    $stmt = $conn->prepare("SELECT * FROM $table WHERE record_id = ?");
    $stmt->bind_param("i", $data['record_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $old_data = $result->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("SELECT access_code FROM $table WHERE record_id = ?");
    $stmt->bind_param("i", $data['record_id']);
    $stmt->execute();
    $stmt->bind_result($original_access_code);
    $stmt->fetch();
    $stmt->close();

    if ($original_access_code !== $data['access_code'] && isDuplicateAccessCode($conn, $table, $data['access_code'])) {
        echo "<script>alert('Error: A record with this Access Code already exists. Please use a unique Access Code.'); window.history.back();</script>";
        exit;
    }

    // === Handle file upload / removal for edit ===
    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir))
        mkdir($uploadDir, 0755, true);

    // Ensure attachments table exists (fallback)
    $conn->query("CREATE TABLE IF NOT EXISTS record_attachments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        record_id INT NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NULL,
        uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (record_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // If user requested removal of all attachments
    if (!empty($_POST['remove_attachment'])) {
        // delete files referenced in record_attachments
        $q = $conn->prepare("SELECT file_path FROM record_attachments WHERE record_id = ?");
        $q->bind_param('i', $data['record_id']);
        $q->execute();
        $res = $q->get_result();
        while ($r = $res->fetch_assoc()) {
            if (!empty($r['file_path'])) {
                // attempt robust deletion
                try_unlink_upload($r['file_path']);
            }
        }
        $q->close();
        // remove DB rows
        $d = $conn->prepare("DELETE FROM record_attachments WHERE record_id = ?");
        $d->bind_param('i', $data['record_id']);
        $d->execute();
        $d->close();
        // clear legacy fields
        $data['attachment'] = '';
        $data['attachment_name'] = '';
    }

    // Track whether this edit operation added attachments
    $attachments_added = false;

    // Handle new uploaded files (attachment_files[]). We append to existing attachments unless remove_attachment was set above.
    if (isset($_FILES['attachment_files'])) {
        $files = restructure_files_array($_FILES['attachment_files']);
        $insertStmt = $conn->prepare("INSERT INTO record_attachments (record_id, file_path, original_name, uploaded_at) VALUES (?, ?, ?, NOW())");
        foreach ($files as $f) {
            if ($f['error'] !== UPLOAD_ERR_OK)
                continue;
            $origName = basename($f['name']);
            $ext = pathinfo($origName, PATHINFO_EXTENSION);
            try {
                $storedName = time() . '_' . bin2hex(random_bytes(6)) . ($ext ? '.' . $ext : '');
            } catch (Exception $e) {
                $storedName = time() . '_' . bin2hex(openssl_random_pseudo_bytes(6)) . ($ext ? '.' . $ext : '');
            }
            $targetPath = $uploadDir . '/' . $storedName;
            if (move_uploaded_file($f['tmp_name'], $targetPath)) {
                $filePath = 'uploads/' . $storedName;
                $insertStmt->bind_param('iss', $data['record_id'], $filePath, $origName);
                $insertStmt->execute();
                $attachments_added = true;
            }
        }
        if (isset($insertStmt))
            $insertStmt->close();
    }

    // After possible removal or addition, ensure legacy attachment fields reflect the first attachment (if any)
    $firstQ = $conn->prepare("SELECT file_path, original_name FROM record_attachments WHERE record_id = ? ORDER BY id ASC LIMIT 1");
    $firstQ->bind_param('i', $data['record_id']);
    $firstQ->execute();
    $firstRes = $firstQ->get_result();
    if ($firstRes && $firstRes->num_rows > 0) {
        $frow = $firstRes->fetch_assoc();
        $data['attachment'] = $frow['file_path'];
        $data['attachment_name'] = $frow['original_name'];
    } else {
        $data['attachment'] = '';
        $data['attachment_name'] = '';
    }
    $firstQ->close();

    // If attachments were added or removed, record a minimal activity_history snapshot
    if (!empty($attachments_added) || !empty($_POST['remove_attachment'])) {
        $rec_id = $data['record_id'];

        // Fetch current record access_code and legacy attachment
        $recStmt = $conn->prepare("SELECT record_id, access_code, attachment FROM document_records WHERE record_id = ? LIMIT 1");
        $recRow = null;
        if ($recStmt) {
            $recStmt->bind_param('i', $rec_id);
            $recStmt->execute();
            $resR = $recStmt->get_result();
            if ($resR && $resR->num_rows > 0) {
                $recRow = $resR->fetch_assoc();
            }
            $recStmt->close();
        }

        // gather attachments metadata
        $atts = [];
        $raQ = $conn->prepare("SELECT id, file_path, original_name, uploaded_at FROM record_attachments WHERE record_id = ? ORDER BY uploaded_at DESC");
        if ($raQ) {
            $raQ->bind_param('i', $rec_id);
            $raQ->execute();
            $raRes = $raQ->get_result();
            while ($ar = $raRes->fetch_assoc()) {
                $atts[] = [
                    'id' => (int) $ar['id'],
                    'file_path' => $ar['file_path'],
                    'original_name' => $ar['original_name'],
                    'uploaded_at' => $ar['uploaded_at'] ?? null
                ];
            }
            $raQ->close();
        }

        if ($recRow) {
            // Build attachments JSON and call central logger so the full record snapshot is recorded
            $attachments_json = count($atts) ? json_encode($atts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

            $data_for_log = $data;
            $data_for_log['attachments'] = $attachments_json;

            $action_name = !empty($attachments_added) ? 'add-attachment' : (!empty($_POST['remove_attachment']) ? 'delete-attachment' : 'edit');

            processActivityLog($conn, $table, $rec_id, $data_for_log, $action_name);
        }
    }

    updateRecord($conn, $table, $data);
    processActivityLog($conn, $table, $data['record_id'], $data, $action);
    echo "<script>alert('Record updated successfully'); window.location='Searchpage.php';</script>";

} else {
    echo "<script>alert('Error: Invalid action.'); window.history.back();</script>";
    exit;
}

/* elseif ($action === 'delete') {
// ✅ DELETE RECORD
$id = $_POST['record_id'];

$stmt = $conn->prepare("DELETE FROM $table WHERE record_id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

echo "<script>alert('Record deleted successfully'); window.location='Searchpage.php';</script>";
} */
?>