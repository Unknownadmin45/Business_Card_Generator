<?php

    // Set the timezone to Asia/Kolkata
    date_default_timezone_set('Asia/Kolkata');

    session_start();
    
    // Create Database and Table if it doesn't exist
    $servername = "localhost";  
    $username = "root";             
    $password = "";             
    $dbname = "contact_form";   
    
    // Create connection
    $conn = new mysqli($servername, $username, $password);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Create database if it doesn't exist
    $sql = "CREATE DATABASE IF NOT EXISTS $dbname";
    if ($conn->query($sql) === TRUE) {
        // Create table if it doesn't exist
        $conn->select_db($dbname);
        $tableSQL = "CREATE TABLE IF NOT EXISTS contacts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            photo VARCHAR(100),
            name VARCHAR(100) NOT NULL,
            role VARCHAR(20),
            team VARCHAR(20),
            phone VARCHAR(12) UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            address VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $conn->query($tableSQL);

        // Create verification table
        $verificationSQL = "CREATE TABLE IF NOT EXISTS verification (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            otp VARCHAR(6) NOT NULL,
            otp_expiry DATETIME NOT NULL,
            verification_link TEXT NOT NULL,
            is_verified TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            
        )";
        $conn->query($verificationSQL);

    }

        // PHPMailer imports
        use PHPMailer\PHPMailer\PHPMailer;
        use PHPMailer\PHPMailer\Exception;
        require 'vendor/autoload.php';

        // Function to generate OTP
        function generateOTP($length = 6) {
            return str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
        }

        // Function to send verification email
        function sendVerificationEmail($email, $otp, $verificationLink) {
            $mail = new PHPMailer(true);
            
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'ADMIN@gmail.com';// YOUR GMAIL
                $mail->Password = '';//YOUR GMAIL PASSWORD
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                // Recipients
                $mail->setFrom('ADMIN@gmail.com', 'ADMIN');
                $mail->addAddress($email);

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Verify Your Email - Contact Fetcher';
                $mail->Body = "Your OTP is: $otp <br> Click <a href='$verificationLink'>here</a> to verify your email.";

                $mail->send();
                return true;
            } catch (Exception $e) {
                return false;
            }
        }

        // Handle AJAX requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');
            $response = array();

            // New handler for finding email by phone number
        if (isset($_POST['action']) && $_POST['action'] === 'findEmail') {
            $phone = trim($_POST['phone']);
            
            $conn->select_db($dbname);
            
            // Check if phone number exists
            $stmt = $conn->prepare("SELECT email FROM contacts WHERE phone = ?");
            $stmt->bind_param("s", $phone);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $response = ['status' => 'success', 'email' => $row['email']];
            } else {
                $response = ['status' => 'error', 'message' => 'No email found for this phone number'];
            }
            
            echo json_encode($response);
            exit;
        }
        
            // Handle OTP generation and email sending
            if (isset($_POST['action']) && $_POST['action'] === 'getOTP') {
                $email = trim($_POST['email']);
                
                // Server-side email validation
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $response = ['status' => 'error', 'message' => 'invalid_email_format'];
                    echo json_encode($response);
                    exit;
                }
    
                $conn->select_db($dbname);
                
                // Check if email exists in contacts table
                $checkEmailStmt = $conn->prepare("SELECT id FROM contacts WHERE email = ?");
                $checkEmailStmt->bind_param("s", $email);
                $checkEmailStmt->execute();
                $emailResult = $checkEmailStmt->get_result();

                if ($emailResult->num_rows === 0) {
                    $response = ['status' => 'error', 'message' => 'Email not Found!  Would you like to Find correct Email using mobile number.'];
                    echo json_encode($response);
                    exit;
                }

                $otp = generateOTP();
                $verificationLink = "http://localhost/05_Ajax_Form_Submission/verify.php?email=" . urlencode($email) . "&otp=" . $otp;
                $otpExpiry = date("Y-m-d H:i:s", strtotime('+15 minutes'));

                // Delete any existing OTP for this email before inserting new one
            $deleteStmt = $conn->prepare("DELETE FROM verification WHERE email = ?");
            $deleteStmt->bind_param("s", $email);
            $deleteStmt->execute();

            // Insert new OTP
            $stmt = $conn->prepare("INSERT INTO verification (email, otp, otp_expiry, verification_link, is_verified) 
                                VALUES (?, ?, ?, ?, 0)");
            
            $stmt->bind_param("ssss", $email, $otp, $otpExpiry, $verificationLink);
            
            if ($stmt->execute() && sendVerificationEmail($email, $otp, $verificationLink)) {
                $response = ['status' => 'success', 'message' => 'OTP sent successfully'];
            } else {
                $response = ['status' => 'error', 'message' => 'Failed to send OTP'];
            }
            
            echo json_encode($response);
            exit;
        }

             // Handle OTP verification
        if (isset($_POST['action']) && $_POST['action'] === 'verifyOTP') {
            $email = trim($_POST['email']);
            $otp = trim($_POST['otp']);

            $conn->select_db($dbname);
            
            // Updated verification query with proper conditions
            $stmt = $conn->prepare("SELECT v.id, c.id as contact_id 
                                FROM verification v 
                                JOIN contacts c ON v.email = c.email 
                                WHERE v.email = ? 
                                AND v.otp = ? 
                                AND v.otp_expiry > NOW() 
                                AND v.is_verified = 0");
            
            $stmt->bind_param("ss", $email, $otp);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                
                // Update verification status
                $updateStmt = $conn->prepare("UPDATE verification SET is_verified = 1 WHERE email = ? AND otp = ?");
                $updateStmt->bind_param("ss", $email, $otp);
                $updateStmt->execute();

                // Store contact ID in session
                $_SESSION['id'] = $row['contact_id'];

                $response = ['status' => 'success', 'message' => 'OTP verified successfully'];
            } else {
                $response = ['status' => 'error', 'message' => 'Invalid or expired OTP'];
            }
            
            echo json_encode($response);
            exit;
        }
    }

            // Handle POST request for form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');
    $response = array();

    // Get form data
    $name = trim($_POST['name']);
    $role = trim($_POST['role']);
    $team = trim($_POST['team']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);

    // Photo upload processing
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $photo = $_FILES['photo'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/heic'];
        $maxSize = 2 * 1024 * 1024; // 2MB limit

        // Validate file type and size
        if (!in_array($photo['type'], $allowedTypes) || $photo['size'] > $maxSize) {
            $response['status'] = 'error';
            $response['message'] = 'Invalid file type or size!';
            echo json_encode($response);
            exit;
        }

        // Generate unique file name
        $photoExtension = pathinfo($photo['name'], PATHINFO_EXTENSION);
        $uniqueFileName = hash('sha256', uniqid()) . '.' . $photoExtension;

        // Define upload directory
        $uploadDir = 'uploads/';

        // Check if the uploads directory exists, if not, create it
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true); // Creates the directory with proper permissions
        }

        // Define the full photo path
        $photoPath = $uploadDir . $uniqueFileName;

        // Move the uploaded file to the target directory
        if (!move_uploaded_file($photo['tmp_name'], $photoPath)) {
            $response['status'] = 'error';
            $response['message'] = 'Failed to upload photo!';
            echo json_encode($response);
            exit;
        }
    } else {
        $photoPath = null; // Handle case if photo is not uploaded
    }

    // Validate form data
    if (empty($name) || empty($email) || empty($phone) || empty($role) || empty($team) || empty($address)) {
        $response['status'] = 'error';
        $response['message'] = 'All fields are required!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['status'] = 'error';
        $response['message'] = 'Invalid email format!';
    } else {
        // Connect to the database
        $conn = new mysqli($servername, $username, $password, $dbname);

        if ($conn->connect_error) {
            $response['status'] = 'error';
            $response['message'] = 'Database connection failed: ' . $conn->connect_error;
        } else {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT * FROM contacts WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $response['status'] = 'error';
                $response['message'] = 'This email is already used!';
            } else {
                // Insert data into the database
                $stmt = $conn->prepare("INSERT INTO contacts (name, role, team, phone, email, address, photo) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssss", $name, $role, $team, $phone, $email, $address, $photoPath);

                if ($stmt->execute()) {
                    $response['status'] = 'success';
                    $response['message'] = 'Contact added successfully!';
                } else {
                    $response['status'] = 'error';
                    $response['message'] = 'Failed to add contact: ' . $stmt->error;
                }
            }
                    $stmt->close();
                }
                $conn->close();
            }
            // Return JSON response
            echo json_encode($response);
            exit;
            }
    ?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Ajax Contact Form</title>

        <!-- SweetAlert2 CSS -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

        <!-- jQuery -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

        <!-- SweetAlert2 JS -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        <style>
            body {
                font-family: 'Arial', serif;
                background-color: #121212;
                color: #ffffff;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
                flex-direction: row;
                gap: 40px;
            }
            .form-container, .fetch-container {
                background-color: #1e1e1e;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
                width: 350px;
                margin: 10px;
            }
            .form-container h2, .fetch-container h2 {
                text-align: center;
                margin-bottom: 20px;
                font-weight: bold;
            }
            .form-container input, .form-container textarea, .fetch-container input {
                width: 100%;
                padding: 10px;
                margin-bottom: 15px;
                border: 1px solid #444;
                border-radius: 4px;
                background-color: #2c2c2c;
                color: #ffffff;
            }
            .form-container button, .fetch-container button {
                width: 100%;
                padding: 10px;
                background-color: #28a745;
                color: #fff;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-weight: bold;
                transition: background-color 0.3s;
            }
            .form-container button:hover, .fetch-container button:hover {
                background-color: #218838;
                transform: translate(0px, -3px);
            }
            .rounded-image {
                border-radius: 50%; 
                width: 120px; 
                height: 120px; 
                object-fit: cover;
            }
            .phone-lookup-container {
                display: none;
                margin-top: 15px;
                padding: 15px;
                border-radius: 4px;
            }
            
            .phone-lookup-container input {
                margin-bottom: 10px;
            }
            
            .phone-lookup-container button {
                background-color: #28a745;
            }
            
            .phone-lookup-container button:hover {
                background-color: #218838;
            }
        </style>
    </head>
    <body>
        <div class="form-container">
        <h2>Contact Form</h2>
            <form id="contactForm" enctype="multipart/form-data">
                <input type="file" id="photoInput" name="photo" accept="image/*">
                <input type="text" id="name" name="name" placeholder="Your Name" required>
                <input type="text" id="role" name="role" placeholder="Role">
                <input type="text" id="team" name="team" placeholder="Team">
                <input type="email" id="email" name="email" placeholder="Email" required>
                <input type="tel" id="phone" name="phone" placeholder="Phone Number" required>
                <textarea id="address" name="address" placeholder="Address" required></textarea>
                <button type="submit">Add Contact</button>
            </form>
        </div> <br>
        
                <!-- Fetch Contact Container -->
            <div class="fetch-container">
                <div id="fetchContactSection">
                    <form id="fetchContactForm">
                    <h2>Fetch Contact</h2>
                        <input type="email" id="fetchEmail" name="email" placeholder="Enter your email" required>
                        <button type="button" id="getOTPBtn">Get OTP</button>
                        <button type="button" id="resendOTPBtn" style="display: none;">Resend OTP</button>
                    </form>
                    
                    <!-- New phone lookup form -->
                    <div id="phoneLookupForm" class="phone-lookup-container">
                        <h2>Find User Email</h2>
                        <input type="tel" id="lookupPhone" name="phone" placeholder="Enter phone number" required>
                        <button type="button" id="getUserEmailBtn">Get User Email</button>
                    </div>
                    
                    <div id="otpSection" style="display: none;">
                        <input type="text" id="otpInput" name="otp" placeholder="Enter OTP" required>
                        <button type="button" id="fetchContactBtn">Fetch Contact</button>
                    </div>
                </div>
            </div>

        <script>
            $(document).ready(function() {
                // Contact form submission
                $('#contactForm').on('submit', function(e) {
                    e.preventDefault(); // Prevent form submission

                    var formData = new FormData(this); // Use FormData to include file upload

                    // Send form data via AJAX
                    $.ajax({
                        type: 'POST',
                        url: '', 
                        data: formData,
                        contentType: false,
                        processData: false,
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success!',
                                    text: response.message
                                }).then(() => {
                                    $('#contactForm')[0].reset(); // Reset form after success
                                    $('#photo').css('border', 'none'); // Clear green border after reset
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: response.message
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Failed to add Contact.'
                            });
                        }
                    });
                });

                // Photo upload handling
                $('#photoInput').on('change', function() {
                    // Get the selected file name
                    var fileName = $(this).val().split('\\').pop(); // Get the file name

                    // Use the photo upload container instead of the input itself for styling
                    var $photoUpload = $('#photoInput'); // Reference to the photo upload container

                    if (fileName) {
                        $photoUpload.css({
                            'border-color': 'green', // Add green border to the container
                            'border-width': '2px', // Optional: Change the border width
                        });
                        $('#photo').text(fileName); // Display the file name in the container
                    } else {
                        $photoUpload.css({
                            'border-color': '#ccc', // Reset the border color if no file is selected
                        });
                        $('#photo').text('No file selected'); // Clear the container text
                    }
                });    
            });
        </script>

      <script>
            $(document).ready(function() {
                let otpTimer;
                let timerDuration = 30; // 1 minute in seconds

                function startOTPTimer() {
                    $('#getOTPBtn').hide();
                    $('#resendOTPBtn').hide();
                    
                    let timeLeft = timerDuration;
                    otpTimer = setInterval(function() {
                        timeLeft--;
                        if (timeLeft <= 0) {
                            clearInterval(otpTimer);
                            $('#resendOTPBtn').show();
                        }
                    }, 1000);
                }

                function resetOTPSection() {
                    clearInterval(otpTimer);
                    $('#otpSection').hide();
                    $('#getOTPBtn').show();
                    $('#resendOTPBtn').hide();
                    $('#otpInput').val('');
                }

           // Get OTP button click handler
           $('#getOTPBtn').click(function() {
                    const email = $('#fetchEmail').val();
                    const emailPattern = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

                    if (!email) {
                        Swal.fire('Error', 'Please enter your email', 'error');
                        return;
                    }

                    if (!emailPattern.test(email)) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid Email',
                            text: 'Please enter a valid email address.'
                        });
                        return;
                    }

                    $.ajax({
                        type: 'POST',
                        url: '',
                        data: {
                            action: 'getOTP',
                            email: email
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire('Success', 'OTP sent to your email', 'success');
                                $('#fetchContactForm').fadeIn();
                                $('#otpSection').fadeIn();
                                $('#phoneLookupForm').hide();
                                startOTPTimer();
                            } else {
                                // Handle different error cases
                                if (response.message === 'invalid_email_format') {
                                    
                                    // This won't trigger due to client-side validation
                                    return;
                                } else if (response.message === 'Email not Found!  Would you like to Find correct Email using mobile number.') {
                                  
                                    // Show error message and option to find email by phone
                                $('#fetchContactForm').fadeOut(); // Hide the fetch contact section
                                $('#otpSection').hide(); // Hide OTP section immediately

                                Swal.fire({
                                    title: 'Error',
                                    text: 'Email not Found!  Would you like to Find correct Email using mobile number.',
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        // Show only the phone lookup form
                                        $('#phoneLookupForm').fadeIn();
                                    }
                                });
                             } else {
                                    Swal.fire('Error', response.message, 'error');
                                }
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'Failed to send OTP', 'error');
                        }
                    });
                });

                // Get User Email button click handler
                $('#getUserEmailBtn').click(function() {
                    const phone = $('#lookupPhone').val();
                    if (!phone) {
                        Swal.fire('Error', 'Please enter phone number', 'error');
                        return;
                    }

                    $.ajax({
                        type: 'POST',
                        url: '',
                        data: {
                            action: 'findEmail',
                            phone: phone
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire({
                                    title: 'Email Found!',
                                    text: 'Use this Email: ' + response.email,
                                    icon: 'success',
                                    confirmButtonText: 'OK'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        $('#fetchEmail').val(response.email);
                                        $('#phoneLookupForm').hide();
                                        $('#fetchContactForm').fadeIn();
                                        $('#otpSection').hide(); // Ensure OTP section is hidden
                                        $('#getOTPBtn').fadeIn(); // Show the Get OTP button
                                    }
                                });
                            } else {
                                Swal.fire('Error', response.message, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'Failed to find email', 'error');
                        }
                    });
                });

            // Resend OTP button click handler
            $('#resendOTPBtn').click(function() {
                const email = $('#fetchEmail').val();
                if (!email) {
                    Swal.fire('Error', 'Please enter your email', 'error');
                    return;
                }

                $.ajax({
                    type: 'POST',
                    url: '',
                    data: {
                        action: 'getOTP',
                        email: email
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire('Success', 'OTP resent to your email', 'success');
                            startOTPTimer();
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Failed to resend OTP', 'error');
                    }
                });
            });

            // Fetch Contact button click handler
            $('#fetchContactBtn').click(function() {
                const email = $('#fetchEmail').val();
                const otp = $('#otpInput').val();

                if (!email || !otp) {
                    Swal.fire('Error', 'Please enter both email and OTP', 'error');
                    return;
                }

                $.ajax({
                    type: 'POST',
                    url: '',
                    data: {
                        action: 'verifyOTP',
                        email: email,
                        otp: otp
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: 'OTP verified successfully',
                                showConfirmButton: false,
                                timer: 1500
                            }).then(() => {
                                window.location.href = 'contact_card.php';
                            });
                            resetOTPSection();
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Failed to verify OTP', 'error');
                    }
                });
            });
        });
    </script>
</body>
</html>