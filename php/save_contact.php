<?php
// Use the session_init.php approach for session handling
require_once 'session_init.php';
include 'database.php';

if (!isset($_SESSION['userid'])) {
  header("Location: ../index.php");
  exit();
}

$userid = $_SESSION['userid'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Use the existing $con connection from database.php instead of creating a new one
    if (!$con) {
        http_response_code(500);
        echo "Error: Database connection not available";
        exit();
    }

    // Use prepared statements to prevent SQL injection
    $name = $_POST['cntname'];
    $email = $_POST['cntemail'];
    $subject = $_POST['cntsubject'];
    $phone = $_POST['cntphone'];
    $message = $_POST['cntmessage'];

    // Prepare the SQL statement
    $sql = "INSERT INTO contactus (conname, conemail, consubject, conphone, conmessage, condate, constatus) 
            VALUES (?, ?, ?, ?, ?, NOW(), 'pending')";
    
    $stmt = $con->prepare($sql);
    
    if ($stmt) {
        // Bind parameters
        $stmt->bind_param("sssss", $name, $email, $subject, $phone, $message);
        
        // Execute the statement
        if ($stmt->execute()) {
            http_response_code(200);
            echo "Success";
        } else {
            http_response_code(500);
            echo "Error: " . $stmt->error;
        }
        
        $stmt->close();
    } else {
        http_response_code(500);
        echo "Error: " . $con->error;
    }
    
    // Don't close $con since it's shared from database.php
}
?>