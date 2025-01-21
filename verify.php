<?php
// Set the timezone to Asia/Kolkata
date_default_timezone_set('Asia/Kolkata');

session_start();

// Database connection
$conn = new mysqli('localhost', 'root', '', 'contact_form');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['email']) && isset($_GET['otp'])) {
    $email = $_GET['email'];
    $otp = trim($_GET['otp']);  // Trim any spaces in the OTP input

    // Updated verification query with proper join
    $stmt = $conn->prepare("SELECT v.id, c.id as contact_id 
                           FROM verification v 
                           JOIN contacts c ON v.email = c.email 
                           WHERE v.email = ? 
                           AND v.otp = ? 
                           AND v.otp_expiry > NOW() 
                           AND v.is_verified = 0");  // Check if OTP is unverified
    
    $stmt->bind_param("ss", $email, $otp);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Mark as verified
        $updateStmt = $conn->prepare("UPDATE verification SET is_verified = 1 WHERE email = ? AND otp = ?");
        $updateStmt->bind_param("ss", $email, $otp);
        $updateStmt->execute();

        // Set session ID
        $_SESSION['id'] = $row['contact_id'];

        // Redirect to contact card
        header('Location: contact_card.php');
        exit;
    } else {
        echo "Invalid or expired OTP.";
    }
} else {
    echo "Invalid verification request.";
}

$conn->close();
?>
