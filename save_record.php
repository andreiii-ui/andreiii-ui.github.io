<?php
include 'connect.php';
include 'log_activity.php'; // ✅ Activity logging file
include 'file_utils.php';

$action = $_POST['action'] ?? '';
$table = 'document_records';

if ($action === 'add') {
    // ✅ ADD RECORD (No activity log)
    $stmt = $conn->prepare(
        "INSERT INTO $table
        (access_code, library, location_code, author, department, title, type, record_status,
        retention_period, description, document_number, supplier, amount_due, notes_keywords,
        addressee, accountable_officer, sender, source_funds, property_number, total_cost,
        attachment, status, date_created, date_modified)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    );

    $date_modified = date('Y-m-d H:i:s');

    // Only normalize the author field per request. Other fields are used raw.
    $v_access_code = $_POST['access_code'] ?? '';
    $v_library = $_POST['library'] ?? '';
    $v_location_code = $_POST['location_code'] ?? '';
    $v_author = format_author($_POST['author'] ?? '');
    $v_department = $_POST['department'] ?? '';
    $v_title = $_POST['title'] ?? '';
    $v_type = $_POST['type'] ?? '';
    $v_record_status = $_POST['record_status'] ?? '';
    $v_retention_period = $_POST['retention_period'] ?? '';
    $v_description = $_POST['description'] ?? '';
    $v_document_number = $_POST['document_number'] ?? '';
    $v_supplier = $_POST['supplier'] ?? '';
    $v_amount_due = $_POST['amount_due'] ?? '';
    $v_notes_keywords = $_POST['notes_keywords'] ?? '';
    $v_addressee = $_POST['addressee'] ?? '';
    $v_accountable_officer = $_POST['accountable_officer'] ?? '';
    $v_sender = $_POST['sender'] ?? '';
    $v_source_funds = $_POST['source_funds'] ?? '';
    $v_property_number = $_POST['property_number'] ?? '';
    $v_total_cost = $_POST['total_cost'] ?? '';
    $v_attachment = $_POST['attachment'] ?? '';
    $v_status = $_POST['status'] ?? '';
    $v_date_created = $_POST['date_created'] ?? '';

    $stmt->bind_param(
        "ssssssssssssssssssssssss",
        $v_access_code,
        $v_library,
        $v_location_code,
        $v_author,
        $v_department,
        $v_title,
        $v_type,
        $v_record_status,
        $v_retention_period,
        $v_description,
        $v_document_number,
        $v_supplier,
        $v_amount_due,
        $v_notes_keywords,
        $v_addressee,
        $v_accountable_officer,
        $v_sender,
        $v_source_funds,
        $v_property_number,
        $v_total_cost,
        $v_attachment,
        $v_status,
        $v_date_created,
        $date_modified
    );

    $stmt->execute();
    $stmt->close();

    echo "<script>alert('Record added successfully'); window.location='Searchpage.php';</script>";
} elseif ($action === 'edit') {
    // ✅ EDIT RECORD + Log Action
    $stmt = $conn->prepare(
        "UPDATE $table SET
        access_code=?, library=?, location_code=?, author=?, department=?, title=?, type=?,
        record_status=?, retention_period=?, description=?, document_number=?, supplier=?,
        amount_due=?, notes_keywords=?, addressee=?, accountable_officer=?, sender=?,
        source_funds=?, property_number=?, total_cost=?, attachment=?, status=?, date_modified=?
        WHERE record_id=?"
    );

    $date_modified = date('Y-m-d H:i:s');
    // Only normalize author for updates as well. Other fields are used raw.
    $v_access_code = $_POST['access_code'] ?? '';
    $v_library = $_POST['library'] ?? '';
    $v_location_code = $_POST['location_code'] ?? '';
    $v_author = format_author($_POST['author'] ?? '');
    $v_department = $_POST['department'] ?? '';
    $v_title = $_POST['title'] ?? '';
    $v_type = $_POST['type'] ?? '';
    $v_record_status = $_POST['record_status'] ?? '';
    $v_retention_period = $_POST['retention_period'] ?? '';
    $v_description = $_POST['description'] ?? '';
    $v_document_number = $_POST['document_number'] ?? '';
    $v_supplier = $_POST['supplier'] ?? '';
    $v_amount_due = $_POST['amount_due'] ?? '';
    $v_notes_keywords = $_POST['notes_keywords'] ?? '';
    $v_addressee = $_POST['addressee'] ?? '';
    $v_accountable_officer = $_POST['accountable_officer'] ?? '';
    $v_sender = $_POST['sender'] ?? '';
    $v_source_funds = $_POST['source_funds'] ?? '';
    $v_property_number = $_POST['property_number'] ?? '';
    $v_total_cost = $_POST['total_cost'] ?? '';
    $v_attachment = $_POST['attachment'] ?? '';
    $v_status = $_POST['status'] ?? '';
    $v_record_id = intval($_POST['record_id'] ?? 0);

    $stmt->bind_param(
        "sssssssssssssssssssssssi",
        $v_access_code,
        $v_library,
        $v_location_code,
        $v_author,
        $v_department,
        $v_title,
        $v_type,
        $v_record_status,
        $v_retention_period,
        $v_description,
        $v_document_number,
        $v_supplier,
        $v_amount_due,
        $v_notes_keywords,
        $v_addressee,
        $v_accountable_officer,
        $v_sender,
        $v_source_funds,
        $v_property_number,
        $v_total_cost,
        $v_attachment,
        $v_status,
        $date_modified,
        $v_record_id
    );

    $stmt->execute();
    $stmt->close();

    // 🟢 Log Edited Activity (Full Data)
    $data_for_log = $_POST;
    $data_for_log['record_id'] = $_POST['record_id'] ?? null;
    processActivityLog($conn, $table, $data_for_log['record_id'], $data_for_log, 'edit');

    echo "<script>alert('Record updated successfully'); window.location='Searchpage.php';</script>";
} elseif ($action === 'delete') {
    // ✅ DELETE RECORD + Log Action
    $id = $_POST['record_id'];

    // Fetch record BEFORE deleting
    $query = $conn->prepare("SELECT * FROM $table WHERE record_id=?");
    $query->bind_param("i", $id);
    $query->execute();
    $result = $query->get_result();
    $row = $result->fetch_assoc();
    $query->close();

    // 🟢 Log Deleted Activity with full record data
    if ($row) {
        processActivityLog($conn, $table, $row['record_id'], $row, 'delete');
    }

    // Delete record
    $stmt = $conn->prepare("DELETE FROM $table WHERE record_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    echo "<script>alert('Record deleted successfully'); window.location='Searchpage.php';</script>";
}
?>