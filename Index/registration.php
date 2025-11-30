<?php
session_start();
require_once 'koneksiDB.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    // Validate inputs
    if (empty($username) || empty($email) || empty($password)) {
        $error = "Isi semua feild!";
    } elseif (strlen($username) > 15) {
        $error = "Username harus dibawah 15 huruf";
    } else {
        // Check if email exists
        $sqlCheck = "SELECT email FROM [User] WHERE email = ?";
        $paramsCheck = [$email];
        $stmtCheck = sqlsrv_query($conn, $sqlCheck, $paramsCheck);
        
        if ($stmtCheck === false) {
            die(print_r(sqlsrv_errors(), true));
        }
        
        if (sqlsrv_has_rows($stmtCheck)) {
            $error = "Email sudah ada";
        } else {
            // Insert new user
            $sqlInsert = "INSERT INTO [User] (username, email, pass) VALUES (?, ?, ?)";
            $paramsInsert = [$username, $email, $password];
            $stmtInsert = sqlsrv_query($conn, $sqlInsert, $paramsInsert);

            if ($stmtInsert) {
                header('Location: login.php');
                exit;
            } else {
                $error = "Registration failed: " . print_r(sqlsrv_errors(), true);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Registration</title>
    <link rel="stylesheet" href="../Styles/registration_styles.css" />
    <style>
      .error {
        color: red;
        margin-bottom: 15px;
        text-align: center;
      }
    </style>
  </head>
  <body>
    <div class="container">
      <!-- Left Side: Registration Form -->
      <div class="left_side">
        <h1>Registration Form</h1>
        
        <?php if (isset($error)): ?>
          <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
          <div class="input_form">
            <input type="text" name="username" placeholder="Enter Username" 
                   value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>" 
                   maxlength="15" required>
            <input type="email" name="email" placeholder="Enter Email"
                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
            <input type="password" name="password" placeholder="Enter Password" required>
          </div>

          <div class="create_account_button">
            <button type="submit" name="register">Create Account</button>
          </div>

          <div class="login_link">
            Already have an account? <a href="login.php">Login</a>
          </div>

          <div class="visitor_link">
            <a href="homeVisitor.php">Masuk sebagai visitor </a>
          </div>
        </form>
      </div>

      <!-- Right Side: Image -->
      <div class="right_side">
        <img src="../img/login_img.jpg" alt="" />
      </div>
    </div>
  </body>
</html>