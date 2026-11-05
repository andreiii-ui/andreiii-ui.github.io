<?php
session_start();
include("connect.php"); // ✅ Include database connection file

// =======================================
// 🔹 PURPOSE OF THIS SCRIPT
// =======================================
// This PHP script retrieves document records from the database for the
// logged-in user, supports pagination, searching, and sorting, and also
// indicates which records are bookmarked (saved) by that user.
// The output is returned in JSON format, suitable for AJAX-based data loading
// in the front-end (e.g., a table that loads data dynamically).

// ✅ Make sure the user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

$username = $_SESSION['username'];

// Get logged-in user's ID from the 'users' table using prepared statement
$stmt_user = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
if ($stmt_user === false) {
    echo json_encode(['error' => 'User lookup prepare failed']);
    exit;
}
$stmt_user->bind_param('s', $username);
$stmt_user->execute();
$res_user = $stmt_user->get_result();
if (!$res_user || $res_user->num_rows === 0) {
    echo json_encode(['error' => 'User not found']);
    $stmt_user->close();
    exit;
}
$user = $res_user->fetch_assoc();
$user_id = $user['id'];
$stmt_user->close();

// Determine whether current user may modify records (server-side authoritative)
// Use role from session if available; falls back to 'viewer'.
$role = strtolower($_SESSION['role'] ?? 'viewer');
// encoders and admins may modify records
$can_modify = ($role === 'admin' || $role === 'encoder') ? 1 : 0;

// =======================================
// 🔹 PAGINATION SETUP
// =======================================
// Handles "page" and "per_page" values passed from the frontend (via GET).
// Defaults to page 1 and 10 records per page if not specified.
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 10;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $per_page;

// =======================================
// 🔹 SEARCH AND SORT OPTIONS
// =======================================
// Accepts search parameters and sorting preferences via GET request.
$search_field = $_GET['search_field'] ?? '';
$search_input = trim($_GET['search_input'] ?? '');
$sort_order = $_GET['sort_order'] ?? 'desc'; // For date sorting
$sort_order2 = $_GET['sort_order2'] ?? '';   // For library name sorting

// =======================================
// 539 BUILD SEARCH CONDITION (WHERE CLAUSE) - using prepared statements
// =======================================
// Validate searchable columns (whitelist) — identifiers cannot be bound as params
$allowed_fields = [
    'access_code',
    'author',
    'title',
    'department',
    'library',
    'location_code',
    'type',
    'record_status',
    'retention_period',
    'description',
    'date_created',
    'date_encoded',
    'document_number',
    'supplier',
    'amount_due',
    'notes_keywords',
    'addressee',
    'accountable_officer',
    'sender',
    'source_funds',
    'property_number',
    'total_cost',
    'attachment',
    'status',
    'date_modified'
];
if ($search_field !== 'all' && !in_array($search_field, $allowed_fields, true)) {
    $search_field = 'all';
}

$where = '';
$where_params = []; // parameters for WHERE clause
if ($search_input !== '') {
    $like_value = '%' . $search_input . '%';
    if ($search_field === 'all') {
        $fields = $allowed_fields;
        $conditions = [];
        foreach ($fields as $field) {
            $conditions[] = "dr.$field LIKE ?";
            $where_params[] = $like_value;
        }
        $where = 'WHERE ' . implode(' OR ', $conditions);
    } else {
        // single validated column
        $where = "WHERE dr.$search_field LIKE ?";
        $where_params[] = $like_value;
    }
}

// 🔹 LIBRARY FILTER
$where_library = '';
if ($sort_order2 === 'lib1') {
    $where_library = "dr.library = 'E1'";
} elseif ($sort_order2 === 'lib2') {
    $where_library = "dr.library = 'E2'";
}

// Merge with search WHERE clause
if ($where && $where_library) {
    $where .= " AND $where_library";
} elseif (!$where && $where_library) {
    $where = "WHERE $where_library";
}

// 🔹 SORTING BY SECOND NUMBER IN document_number
$order_by = "ORDER BY CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(dr.document_number, '/', 2), '/', -1) AS UNSIGNED) ASC";


// =======================================
// 🔹 TOTAL RECORD COUNT
// =======================================
// Used for pagination (knowing total number of results)
$total = 0;
// Prepare and execute total count statement
$total_sql = "SELECT COUNT(*) as total FROM document_records dr " . ($where ? $where : '');
$stmt_total = $conn->prepare($total_sql);
if ($stmt_total === false) {
    die(json_encode(['error' => 'Count prepare failed: ' . $conn->error]));
}
if (!empty($where_params)) {
    // bind where params
    $types = str_repeat('s', count($where_params));
    $bind_names = array_merge([$types], $where_params);
    // helper to pass by reference
    $refs = [];
    foreach ($bind_names as $key => $val)
        $refs[$key] = &$bind_names[$key];
    call_user_func_array([$stmt_total, 'bind_param'], $refs);
}
$stmt_total->execute();
$res_total = $stmt_total->get_result();
if ($res_total) {
    $total_row = $res_total->fetch_assoc();
    $total = $total_row['total'] ?? 0;
}
$stmt_total->close();

// =======================================
// 🔹 MAIN DATA QUERY
// =======================================
// Retrieves document records with bookmark status (joined from saved_records)
// If a record is bookmarked by the logged-in user, `is_saved = 1`, otherwise 0.

// Build main query using prepared statement
// Note: ORDER BY cannot be bound as a parameter; it's built from validated inputs above

// Build main SQL; if per_page <= 0 then return all results (no LIMIT)
$main_sql = "SELECT dr.*, CASE WHEN sr.record_id IS NOT NULL THEN 1 ELSE 0 END AS is_saved 
    FROM document_records dr 
    LEFT JOIN saved_records sr ON sr.record_id = dr.record_id AND sr.user_id = ? 
    " . ($where ? $where : '') . " 
    " . $order_by;

$use_limit = ($per_page > 0);
if ($use_limit) {
    $main_sql .= " LIMIT ? OFFSET ?";
}

$stmt = $conn->prepare($main_sql);
if ($stmt === false) {
    die(json_encode(['error' => 'Query prepare failed: ' . $conn->error]));
}

// Build binding parameters: first user_id, then where params, then optionally limit & offset (ints)
$bind_params = [];
$bind_types = '';
$bind_params[] = $user_id;
$bind_types .= 'i';
foreach ($where_params as $p) {
    $bind_params[] = $p;
    $bind_types .= 's';
}
if ($use_limit) {
    $bind_params[] = $per_page;
    $bind_types .= 'i';
    $bind_params[] = $offset;
    $bind_types .= 'i';
}

$bind_names = array_merge([$bind_types], $bind_params);
$refs = [];
foreach ($bind_names as $key => $val)
    $refs[$key] = &$bind_names[$key];
call_user_func_array([$stmt, 'bind_param'], $refs);

$stmt->execute();
$result = $stmt->get_result();
if ($result === false) {
    die(json_encode(['error' => 'Query execute failed: ' . $conn->error]));
}

// ✅ Store all fetched records into an array
$records = [];
while ($row = $result->fetch_assoc()) {
    $records[] = $row;
}

// =======================================
// 🔹 OUTPUT JSON RESPONSE
// =======================================
// Returns data for use in front-end JavaScript (fetch or AJAX)
header('Content-Type: application/json');
echo json_encode([
    'total' => $total,   // total number of records
    'records' => $records, // actual data
    // server-side flag telling frontend whether the user can perform modify actions
    'can_modify' => $can_modify
]);

$conn->close(); // ✅ Close database connection
?>