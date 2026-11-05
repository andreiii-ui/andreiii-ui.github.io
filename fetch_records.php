<?php
$servername = "localhost";
$username = "root"; // default XAMPP user
$password = "";     // default empty password in XAMPP
$dbname = "library_records";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT * FROM document_records";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
  // output data of each row as table rows
  while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['access_code']) . "</td>";
    echo "<td>" . htmlspecialchars($row['library']) . "</td>";
    echo "<td>" . htmlspecialchars($row['location_code']) . "</td>";
    echo "<td>" . htmlspecialchars($row['author']) . "</td>";
    echo "<td>" . htmlspecialchars($row['department']) . "</td>";
    echo "<td>" . htmlspecialchars($row['title']) . "</td>";
    echo "<td>" . htmlspecialchars($row['type']) . "</td>";
    echo "<td>" . htmlspecialchars($row['record_status']) . "</td>";
    echo "<td>" . htmlspecialchars($row['retention_period']) . "</td>";
    echo "<td>" . htmlspecialchars($row['description']) . "</td>";
    echo "<td>" . htmlspecialchars($row['date_created']) . "</td>";
    echo "<td>" . htmlspecialchars($row['date_encoded']) . "</td>";
    echo "<td>" . htmlspecialchars($row['document_number']) . "</td>";
    echo "<td>" . htmlspecialchars($row['supplier']) . "</td>";
    echo "<td>" . htmlspecialchars(number_format($row['amount_due'], 2)) . "</td>";
    echo "<td>" . htmlspecialchars($row['notes_keywords']) . "</td>";
    echo "<td>" . htmlspecialchars($row['addressee']) . "</td>";
    echo "<td>" . htmlspecialchars($row['accountable_officer']) . "</td>";
    echo "<td>" . htmlspecialchars($row['sender']) . "</td>";
    echo "<td>" . htmlspecialchars($row['source_funds']) . "</td>";
    echo "<td>" . htmlspecialchars($row['property_number']) . "</td>";
    echo "<td>" . htmlspecialchars(number_format($row['total_cost'], 2)) . "</td>";
    // Display the original filename when available, otherwise fallback to basename of stored path
    $displayAttachment = $row['attachment_name'] ?? ($row['attachment'] ? basename($row['attachment']) : '');
    echo "<td>" . htmlspecialchars($displayAttachment) . "</td>";
    echo "<td>" . htmlspecialchars($row['status']) . "</td>";
    echo "</tr>";
  }
} else {
  echo "<tr><td colspan='24'>No records found</td></tr>";
}

$conn->close();
?>