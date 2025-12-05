<?php
session_start();
require_once 'koneksiDB.php';

// Periksa apakah user login sebagai brand account
$baId = $_SESSION['baId'] ?? null;
$chnlId = $_GET['chnlId'];

if($baId === null){
    $a = "SELECT baId FROM Channel WHERE chnlId = ?";
    $b = sqlsrv_query($conn, $a, array($chnlId));
    $c = sqlsrv_fetch_array($b, SQLSRV_FETCH_ASSOC);
    $baId = $c['baId'];
}


// Dapatkan userId pemilik brand account
$sqlUser = "SELECT userId FROM BrandAcc WHERE baId = ?";
$stmtUser = sqlsrv_query($conn, $sqlUser, array($baId));
$brandUser = sqlsrv_fetch_array($stmtUser, SQLSRV_FETCH_ASSOC);
$kirimId = $brandUser['userId'];

// Proses form jika disubmit
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user'])) {
    $userIdentifier = trim($_POST['user']);
    $role = $_POST['role'];
    
    // Validasi role
    $allowedRoles = ['Manager', 'Editor', 'Limited Editor', 'Sub Editor', 'Viewer'];
    if (!in_array($role, $allowedRoles)) {
        $error = "Role tidak valid";
    } else {
        // Cari user berdasarkan email
        $sqlFindUser = "SELECT userId FROM [User] WHERE email = ? OR username = ?";
        $paramsFindUser = array($userIdentifier, $userIdentifier);
        $stmtFindUser = sqlsrv_query($conn, $sqlFindUser, $paramsFindUser);
        
        if ($stmtFindUser && sqlsrv_has_rows($stmtFindUser)) {
            $row = sqlsrv_fetch_array($stmtFindUser, SQLSRV_FETCH_ASSOC);
            $terimaId = $row['userId'];
            
            // Cek apakah undangan sudah ada
            $sqlCheck = "SELECT * FROM Invite WHERE kirimId = ? AND terimaId = ?";
            $paramsCheck = array($kirimId, $terimaId);
            $stmtCheck = sqlsrv_query($conn, $sqlCheck, $paramsCheck);
            
            if ($stmtCheck && sqlsrv_has_rows($stmtCheck)) {
                $error = "Anda sudah mengirim undangan ke user ini";
            } else {
                // Masukkan undangan baru
                $sqlInsert = "INSERT INTO Invite (kirimId, terimaId, role) VALUES (?, ?, ?)";
                $paramsInsert = array($kirimId, $terimaId, $role);
                $stmtInsert = sqlsrv_query($conn, $sqlInsert, $paramsInsert);
                
                if ($stmtInsert) {
                    $success = "Undangan berhasil dikirim";
                } else {
                    $error = "Gagal mengirim undangan: " . print_r(sqlsrv_errors(), true);
                }
            }
        } else {
            $error = "User tidak ditemukan";
        }
    }
}

// Get user channels (untuk sidebar)
$userChannels = [];
$sqlChannels = "SELECT chnlId, nama, pfp FROM Channel WHERE baId = ?";
$paramsChannels = array($baId);
$stmtChannels = sqlsrv_query($conn, $sqlChannels, $paramsChannels);

if ($stmtChannels !== false) {
    while ($row = sqlsrv_fetch_array($stmtChannels, SQLSRV_FETCH_ASSOC)) {
        $userChannels[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Invite User</title>
    <link rel="stylesheet" href="../Styles/invite_user_styles.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.12.1/css/all.min.css" />
    <style>
        .error {
            color: #e63946;
            background: #ffe6e6;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .success {
            color: #4CAF50;
            background: #e8f5e9;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .channel_pfp {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            margin-right: 15px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <!-- Checkbox untuk toggle menu -->
    <input type="checkbox" id="check" />

    <div class="container">
        <!-- Sidebar Kiri -->
        <div class="left_side">
            <div class="menu-header">
                <label for="check">
                    <span class="fas fa-times" id="times"></span>
                    <span class="fas fa-bars" id="bars"></span>
                </label>
                <div class="head">MeTube</div>
            </div>

            <ol>
                <li>
                    <a href="home.php"><i class="fas fa-home"></i>Home</a>
                </li>
                <!-- TAMBAHAN 3 BUTTON -->
                <li>
                    <a href="subscription.php"><i class="fas fa-star"></i>Subscription</a>
                </li>
                <li>
                    <a href="notification.php"><i class="fas fa-bell"></i>Notification</a>
                </li>
                <li>
                    <a href="collaboration.php"><i class="fas fa-handshake"></i>Collaboration</a>
                </li>
                
                <!-- CHANNEL USER - ONLY SHOW IF CHANNELS EXIST -->
                <?php foreach ($userChannels as $ch): ?>
                <li>
                    <a href="profile.php?chnlId=<?= $ch['chnlId'] ?>">
                        <?php if (!empty($ch['pfp'])): ?>
                            <img src="<?= htmlspecialchars($ch['pfp']) ?>" 
                                alt="Profile" 
                                class="channel_pfp"
                                onerror="this.src='default_pfp.jpg'">
                        <?php else: ?>
                            <i class="fas fa-user-circle"></i>
                        <?php endif; ?>
                        <?= htmlspecialchars($ch['nama']) ?>
                    </a>
                </li>
                <?php endforeach; ?>
                
                <!-- ADD CHANNEL BUTTON -->
                <li>
                    <a href="addChannel.php"><i class="fas fa-users"></i>Add Channel</a>
                </li>
            </ol>
        </div>

        <!-- Konten Kanan -->
        <div class="right_side">
            <h2 class="page_title">Undang User</h2>

            <?php if (!empty($error)): ?>
                <div class="error"><?= $error ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="success"><?= $success ?></div>
            <?php endif; ?>
            
            <form method="POST" id="inviteForm">
                <div class="form-group">
                    <label for="userEmail">Email atau Username User</label>
                    <input type="text" id="userEmail" name="user" 
                           placeholder="e.g. johndoe@example.com atau johndoe" required>
                    
                    <label for="userRole">Pilih Role</label>
                    <select id="userRole" name="role" required>
                        <option value="" disabled selected>Pilih role</option>
                        <option value="Manager">Manager</option>
                        <option value="Editor">Editor</option>
                        <option value="Limited Editor">Limited Editor</option>
                        <option value="Sub Editor">Sub Editor</option>
                        <option value="Viewer">Viewer</option>
                    </select>
                </div>
                <button class="submit_btn" type="submit">Undang</button>
            </form>
        </div>
    </div>
</body>
</html>