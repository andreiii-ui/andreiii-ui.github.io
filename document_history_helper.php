<?php
/**
 * document_history_helper.php
 *
 * Provides helper to save deleted records into `document_history` table.
 */
include_once 'connect.php';

/**
 * Save a deleted record into document_history.
 * @param mysqli $conn
 * @param array $record Associative array of document_records row (column => value)
 * @param string $deleted_by Optional username who deleted the record (falls back to session username)
 * @return bool True on success, false on failure
 */
function saveDeletedRecord($conn, $record, $deleted_by = null)
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!$deleted_by) {
        $deleted_by = $_SESSION['username'] ?? null;
    }

    // Get columns available in document_history
    $table = 'document_history';
    $cols_sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
    $stmt = $conn->prepare($cols_sql);
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $res = $stmt->get_result();
    $available = [];
    while ($r = $res->fetch_assoc()) {
        $available[] = $r['COLUMN_NAME'];
    }
    $stmt->close();

    if (empty($available)) {
        return false; // table doesn't exist or no columns
    }

    // Build insert columns and values from intersection of $record and $available
    $insertCols = [];
    $placeholders = [];
    $types = '';
    $values = [];

    foreach ($available as $col) {
        // Skip auto timestamp columns named 'timestamp' if present; we'll let DB set it if default NOW()
        if ($col === 'timestamp' && !array_key_exists('timestamp', $record)) {
            continue;
        }
        if ($col === 'deleted_by') {
            $insertCols[] = $col;
            $placeholders[] = '?';
            $types .= 's';
            $values[] = $deleted_by;
            continue;
        }
        if (array_key_exists($col, $record)) {
            $insertCols[] = $col;
            $placeholders[] = '?';
            // determine type loosely: integers -> i, else s
            $val = $record[$col];
            if (is_int($val) || ctype_digit((string) $val)) {
                $types .= 'i';
            } else {
                $types .= 's';
            }
            $values[] = $val;
        }
    }

    if (empty($insertCols)) {
        return false;
    }

    $cols_sql = implode(',', $insertCols);
    $phs_sql = implode(',', $placeholders);

    $insert_sql = "INSERT INTO $table ($cols_sql) VALUES ($phs_sql)";
    $ins = $conn->prepare($insert_sql);
    if ($ins === false) {
        // Log prepare error for easier debugging
        error_log("[saveDeletedRecord] prepare failed: " . ($conn->error ?? 'unknown'));
        return false;
    }

    // bind params dynamically
    $bind_names[] = $types;
    for ($i = 0; $i < count($values); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $values[$i];
        $bind_names[] = &$$bind_name;
    }

    call_user_func_array([$ins, 'bind_param'], $bind_names);
    $ok = $ins->execute();
    if (!$ok) {
        // Log execution error for easier debugging when records are not saved
        error_log("[saveDeletedRecord] execute failed: " . ($ins->error ?? $conn->error ?? 'unknown'));
    }
    $ins->close();
    return $ok;
}

?>