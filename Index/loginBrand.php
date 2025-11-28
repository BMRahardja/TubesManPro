<?php
session_start();
require_once 'koneksiDB.php'; // Pastikan menggunakan file koneksi yang benar

// Proses login jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Query untuk mencari user berdasarkan username
    $sql = "SELECT baId, username, userId, pass 
            FROM BrandAcc
            WHERE username = ?";
    $params = [$username];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    // Jika user ditemukan
    if ($stmt && sqlsrv_has_rows($stmt)) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        
        // Verifikasi password
        if ($password === $row['pass']) {
            $_SESSION['userId'] = $row['userId'];
            $_SESSION['baId'] = $row['baId'];
            $_SESSION['uname'] = $row['username'];
            header("Location: home.php");
            exit;
        }
    }

    // Jika login gagal
    echo "<script>
            alert('Login gagal! username atau password salah');
            window.location.href='loginBrand.php';
          </script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login</title>
    <link rel="stylesheet" href="../Styles/login_styles.css" />
  </head>
  <body>
    <div class="container">
      <!-- Sisi Kiri: Form Login -->
      <div class="left_side">
        <h1>Welcome</h1>
        <form method="POST" action="" class="login_form">
          <div class="input_form">
            <input type="username" name="username" placeholder="Enter Username" required />
            <input type="password" name="password" placeholder="Enter Password" required />
          </div>

          <div class="button_row">
            <button type="button" class="signup" onclick="location.href='registrationBrand.php'">Sign Up</button>
            <button type="submit" class="submit" name="login">Login</button>
          </div>
        </form>
      </div>

      <!-- Sisi Kanan: Gambar -->
      <div class="right_side">
        <img src="../img/login_img.jpg" alt="" />
      </div>
    </div>
  </body>
</html>