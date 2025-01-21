<?php
  session_start();

  // Check if the session ID exists
  if (isset($_SESSION['id'])) {
      $id = $_SESSION['id'];

      // Database connection
      $conn = new mysqli('localhost', 'root', '', 'contact_form');

      // Check connection
      if ($conn->connect_error) {
          die("Connection failed: " . $conn->connect_error);
      }

      // Fetch contact details from the database
      $sql = "SELECT name, role, team, phone, email, address, photo FROM contacts WHERE id = ?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("i", $id);
      $stmt->execute();
      $stmt->bind_result($name, $role, $team, $phone, $email, $address, $photo);
      $stmt->fetch();
      $stmt->close();
      $conn->close();
  } else {
      echo "No contact ID found in the session.";
      exit;
  }
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Contact Card</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.5.0/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/0.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/1.3.2/jspdf.min.js"></script>

    <style>
            body {
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            overflow: hidden;
        }

        iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            filter: blur(8px);
            pointer-events: none; /* Prevent interaction with the iframe */
        }

        .modal-dialog-centered {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .modal-dialog {
            max-width: 380px;
            width: 100%;
            height: 730px;
            margin: auto;
            border-radius: 15px;
            position: relative;
        }

        .modal-content {
            border-radius: 5px;
            overflow: hidden;
        }

        .business-card {
            width: 380px;
            height: 640px;
            background: #f9f9f9;
            border-radius: 5px; 
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); 
            padding: 0;
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #2e414f, #4a5762);
            padding: 15px; 
            text-align: center;
            border-radius: 15px 15px 0 0; 
        }

        .profile-img {
            width: 140px;
            height: 140px; 
            background-color: #fff; 
            border-radius: 50%;
            margin: 0 auto;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .profile-img img {
            width: 130px; 
            height: 130px; 
            border-radius: 50%;
        }

        .name {
            font-size: 1.6rem;
            font-weight: bold;
            margin-top: 15px;
            color: #F4F6FF;
        }

        .title {
            font-size: 1rem;
            color: #F4F6FF;
        }

        .title a {
            color: #F4F6FF;
            text-decoration: none; /* Added text-decoration */
        }

        .tabs-section {
            background-color: #F5F5F5;
            padding: 20px;
            border-top: 3px solid #eae0f8;
            border-radius: 0 0 15px 15px; /* Added border-radius */
        }

        .contact-info p {
            font-size: 0.9rem;
            color: #333;
            display: flex;
            align-items: center;
        }

        .contact-info i {
            margin-right: 10px;
            color: #333;
        }

        .action-buttons {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            background-color: #4a5762;
            border-top: 1px solid #ddd;
        }

        .action-buttons button, .action-buttons a {
            text-decoration: none;
            color: #fff;
            font-weight: bold;
            font-size: 18px;
            border: none;
            background: none;
        }

        .modal-header {
            border-bottom: none;
            position: absolute;
            top: 10px;
            right: 15px;
            background: none;
        }

        .modal-header .close {
            font-size: 1.5rem;
            background: none;
            border: none;
            color: #fff;
        }

        .download-box {
            position: absolute;
            left: 50%;
            transform: translate(-50%, -50%);
            background: darkgray;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0px 0px 25px rgba(0, 0, 0, 0.8);
            z-index: 1060;
        }

        .download-box h5 {
            margin: 0 0 10px;
            color: #333;
            text-align: center;
        }

        .download-box .close-box {
            position: absolute;
            top: 5px;
            right: 10px;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #333;
        }

        .download-box button {
            background-color: #4a4a4a;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn-primary:hover {
            background-color: #3a3a3a;
        }

        .close-box :hover {
            color: crimson;
        }

    </style>
  </head>
  <body>
    <!-- Iframe for background -->
    <iframe src="index.php" frameborder="0"></iframe>

    <!-- Modal Triggered on Page Load -->
    <div class="modal fade" id="contactModal" tabindex="-1" aria-labelledby="contactModalLabel" aria-modal="true" role="dialog" data-backdrop="static" data-keyboard="false">
      <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
                <div class="business-card" id="businessCard">
                    <div class="card-header">
                        <div class="profile-img">
                            <img src="<?= !empty($photo) && file_exists($photo) ? $photo : 'resources/default_img.png' ?>" alt="Profile Image">
                        </div>
                        <h2 class="name"><?= $name ?></h2>
                        <p class="title"><a href="mailto:<?= $email ?>" target="_blank"><?= $email ?></a></p>
                        <div class="title">
                            <div style="display: inline-block; text-align: center; width: 100px;">
                                <span><strong>Role</strong></span><br>
                                <span><?= $role ?></span>
                            </div>
                            &nbsp;&nbsp;&nbsp;&nbsp; | &nbsp;&nbsp;&nbsp;&nbsp;
                            <div style="display: inline-block; text-align: center; width: 100px;">
                                <span><strong>Team</strong></span><br>
                                <span><?= $team ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="tabs-section">
                        <div class="contact-info">
                            <p>
                                <a href="mailto:<?= $email ?>" style="text-decoration: none; font-size: 18px; color: black;">
                                    <i class="fas fa-envelope fa-2x" style="color: #2e414f"></i> <?= $email ?>
                                </a>
                            </p>
                            <p>
                                <a href="tel:<?= $phone ?>" style="text-decoration: none; font-size: 18px; color: black;">
                                    <i class="fas fa-phone fa-2x" style="color: #2e414f; transform: scaleX(-1);"></i> <?= $phone ?>
                                </a>
                            </p>
                            <p>
                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $phone) ?>" style="text-decoration: none; font-size: 18px; color: black;">
                                    <i class="fab fa-whatsapp fa-2x" style="color: #2e414f;"></i> <?= $phone ?>
                                </a>
                            </p>
                            <p>
                                <a href="https://www.google.com/maps?q=<?= urlencode($address) ?>" target="_blank" style="text-decoration: none; font-size: 18px; color: black;">
                                    <i class="fas fa-map-marker-alt fa-2x" style="color: #2e414f;"></i> <?= $address ?>
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
                <!-- Action Buttons -->
                <div class="action-buttons">
                    <button type="button" class="btn btn-outline-light" id="returnBtn">
                        <i class="fas fa-arrow-left"></i> Return
                    </button>
                    <button type="button" class="btn btn-outline-light" id="saveBtn">
                        <i class="fas fa-save"></i> Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Small Box for Download Options -->
    <div id="downloadBox" class="download-box" style="display: none;">
        <button class="close-box" aria-label="Close" style="font-size: 1rem">
            <i class="fas fa-times"></i> <!-- Font Awesome close icon -->
        </button>
        <h5>Choose Format</h5> <br>
        <button id="downloadImage" class="btn btn-primary">Image</button>
          &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        <button id="downloadPDF" class="btn btn-primary">PDF</button>
    </div>

    <!-- Bootstrap JS and Dependencies -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>

    <script>
      $(document).ready(function () {
          // Show the modal on page load
          $('#contactModal').modal('show');

          // Show download options box on save button click
          $('#saveBtn').on('click', function (event) {
              event.stopPropagation(); // Prevents the click event from propagating to the modal
              console.log("Save button clicked"); // Debugging log
              $('#downloadBox').fadeIn();
          });

          // Close download options box
          $('.close-box').on('click', function () {
              $('#downloadBox').fadeOut();
          });

          // Return button redirect logic
          $('#returnBtn').on('click', function () {
              window.location.href = 'index.php';
          });

          // Return to previous page when clicking outside the modal
          $('#contactModal').on('click', function (e) {
              if ($(e.target).hasClass('modal')) {
                  window.location.href = 'index.php'; // Redirect to previous page
              }
          });

          // Show download options box
          $('#showDownloadBox').on('click', function () {
              $('#downloadBox').fadeIn();
          });

          // Close download box function
          function closeDownloadBox() {
              $('#downloadBox').fadeOut();
          }

          // Download image function
          $('#downloadImage').on('click', function () {
              html2canvas(document.querySelector("#businessCard"), { scale: 2 }).then(canvas => {
                  let link = document.createElement('a');
                  link.href = canvas.toDataURL('image/png', 1.0); // Full quality
                  link.download = 'contact_card.png';
                  link.click();
              });
              closeDownloadBox(); // Close download box after downloading
          });

          // Download PDF function
          $('#downloadPDF').on('click', function () {
              html2canvas(document.querySelector("#businessCard"), { scale: 2 }).then(canvas => {
                  let imgData = canvas.toDataURL('image/png');
                  let pdf = new jsPDF();
                  
                  // A4 dimensions
                  let imgWidth = 210; // A4 width in mm
                  let imgHeight = (canvas.height * imgWidth) / canvas.width; // Adjusting image height
                  let pageHeight = pdf.internal.pageSize.height;

                  let heightLeft = imgHeight;
                  let position = 0;

                  // Add the image to the PDF, handling multiple pages if necessary
                  pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                  heightLeft -= pageHeight;

                  while (heightLeft >= 0) {
                      position = heightLeft; // Set position for the next page
                      pdf.addPage();
                      pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                      heightLeft -= pageHeight;
                  }

                  pdf.save('contact_card.pdf');
              });
              closeDownloadBox(); // Close download box after downloading
          });
      });
    </script>
  </body>
</html>