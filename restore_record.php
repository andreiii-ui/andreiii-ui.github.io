<?php
include 'connect.php';
include_once 'log_activity.php';
session_start();
include_once 'rolePermissionHandler.php';
require_modifier(); // Admins/encoders can restore

if (isset($_POST['record_id'])) {
    $record_id = $_POST['record_id'];

    // 1️⃣ Fetch from document_history
    $fetch = $conn->prepare("SELECT * FROM document_history WHERE record_id = ? LIMIT 1");
    $fetch->bind_param("i", $record_id);
    $fetch->execute();
    $result = $fetch->get_result();
    $record = $result->fetch_assoc();
    $fetch->close();

    if ($record) {
        // ✅ Check if the record already exists in document_records
        $access_code = $record['access_code'];
        $check = $conn->prepare("SELECT 1 FROM document_records WHERE access_code = ? LIMIT 1");
        $check->bind_param("s", $access_code);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            // Already exists — do not restore
            $check->close();
            echo "<script>alert('⚠️ Cannot restore. Record with the same Access Code already exists.'); window.location='Trash_bin.php';</script>";
            exit;
        }
        $check->close();

        // 2️⃣ Insert back into document_records (only matching columns)
        $insert_sql = "INSERT INTO document_records (
            record_id, access_code, library, location_code, author, department, title, type,
            record_status, retention_period, description, document_number, supplier, amount_due,
            notes_keywords, addressee, accountable_officer, sender, source_funds, property_number,
            total_cost, attachment, status, date_created, date_encoded, date_modified
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $insert = $conn->prepare($insert_sql);
        $insert->bind_param(
            "isssssssssssssssssssssssss",
            $record['record_id'],
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
            $record['status'],
            $record['date_created'],
            $record['date_encoded'],
            $record['date_modified']
        );

        if ($insert->execute()) {
            $insert->close();

            // 3️⃣ Remove from document_history after restore
            $delete = $conn->prepare("DELETE FROM document_history WHERE record_id = ?");
            $delete->bind_param("i", $record_id);
            $delete->execute();
            $delete->close();

            // 4️⃣ Log activity
            logActivity(
                $record_id,
                $record['access_code'] ?? '',
                $record['library'] ?? '',
                $record['location_code'] ?? '',
                $record['author'] ?? '',
                $record['department'] ?? '',
                $record['title'] ?? '',
                $record['type'] ?? '',
                $record['record_status'] ?? '',
                $record['retention_period'] ?? '',
                $record['description'] ?? '',
                $record['document_number'] ?? '',
                $record['supplier'] ?? '',
                $record['amount_due'] ?? '',
                $record['notes_keywords'] ?? '',
                $record['addressee'] ?? '',
                $record['accountable_officer'] ?? '',
                $record['sender'] ?? '',
                $record['source_funds'] ?? '',
                $record['property_number'] ?? '',
                $record['total_cost'] ?? '',
                $record['attachment'] ?? '',
                // attachments column (if present in history row)
                $record['attachments'] ?? null,
                $record['status'] ?? '',
                $record['date_created'] ?? '',
                'Restore - Insert'
            );

            echo "<script>alert('✅ Record restored successfully.'); window.location='Trash_bin.php';</script>";
        } else {
            echo "Error restoring record: " . $insert->error;
        }
    } else {
        echo "Record not found in document_history.";
    }
} else {
    echo "No record ID provided.";
}
?>