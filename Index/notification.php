<?php
session_start();
require_once 'koneksiDB.php';

// Ensure consistent session variable naming
$userId = $_SESSION['userId'];
$baId = isset($_SESSION['baId'])? $_SESSION['baId']: null;

// Redirect if not logged in
if (!$userId) {
    header("Location: ../Index/login.php");
    exit();
}

// Store consistent session variable
$_SESSION['userId'] = $userId;

// Handle invitation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['kirimId']) && isset($_POST['chnlId']) && isset($_POST['role'])) {
        $action = $_POST['action'];
        $kirimId = $_POST['kirimId'];
        $chnlId = $_POST['chnlId'];
        $role = $_POST['role'];
        
        // Delete the invitation regardless of action
        $sqlDelete = "DELETE FROM Invite WHERE kirimId = ? AND terimaId = ?";
        $paramsDelete = array($kirimId, $userId);
        sqlsrv_query($conn, $sqlDelete, $paramsDelete);
        
        // If accepted, add to AdaRole
        if ($action === 'accept') {
            $sqlInsert = "INSERT INTO AdaRole (userId, chnlId, role) VALUES (?, ?, ?)";
            $paramsInsert = array($userId, $chnlId, $role);
            sqlsrv_query($conn, $sqlInsert, $paramsInsert);
        }
        
        // Redirect to refresh the page
        header("Location: notification.php");
        exit();
    }
}

// Get invitations for the current user
$sqlInvitations = "SELECT 
                    i.kirimId, 
                    i.role, 
                    c.chnlId, 
                    c.nama AS channel_name,
                    c.pfp AS channel_pfp
                FROM Invite i
                JOIN Channel c ON c.chnlId = (
                    SELECT TOP 1 chnlId 
                    FROM Channel 
                    WHERE baId = (
                        SELECT baId 
                        FROM BrandAcc 
                        WHERE userId = i.kirimId
                    )
                )
                WHERE i.terimaId = ?";
$paramsInvitations = array($userId);
$stmtInvitations = sqlsrv_query($conn, $sqlInvitations, $paramsInvitations);

$invitations = [];
if ($stmtInvitations !== false) {
    while ($row = sqlsrv_fetch_array($stmtInvitations, SQLSRV_FETCH_ASSOC)) {
        $invitations[] = $row;
    }
}

// Get user channels
$userChannels = [];
if($baId !== null){
    $sqlChannels = "SELECT chnlId, nama, pfp FROM Channel WHERE baId = ?";
    $paramsChannels = array($baId);
    $stmtChannels = sqlsrv_query($conn, $sqlChannels, $paramsChannels);

    if ($stmtChannels !== false) {
        while ($row = sqlsrv_fetch_array($stmtChannels, SQLSRV_FETCH_ASSOC)) {
            $userChannels[] = $row;
        }
    }
}else{
    $sqlChannels = "SELECT chnlId, nama, pfp FROM Channel WHERE userId = ? AND tipe = 'personal'";
    $paramsChannels = array($userId);
    $stmtChannels = sqlsrv_query($conn, $sqlChannels, $paramsChannels);

    if ($stmtChannels !== false) {
        while ($row = sqlsrv_fetch_array($stmtChannels, SQLSRV_FETCH_ASSOC)) {
            $userChannels[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>MeTube - Home</title>
    <link rel="stylesheet" href="../Styles/home_styles.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.12.1/css/all.min.css" />
    <style>
        /* Video grid styles */
.notification_content {
            padding: 30px;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .section_title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 25px;
            color: #333;
            border-bottom: 2px solid #e63946;
            padding-bottom: 10px;
        }
        
        .invitation_list {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }
        
        .invitation_card {
            display: flex;
            align-items: center;
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .invitation_card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);
        }
        
        .channel_info {
            display: flex;
            align-items: center;
            flex: 1;
        }
        
        .channel_pfp_img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 20px;
            border: 2px solid #e63946;
        }
        
        .channel_text {
            flex: 1;
        }
        
        .channel_name {
            font-weight: 600;
            font-size: 18px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .invitation_text {
            color: #666;
            font-size: 15px;
        }
        
        .role_badge {
            background: #e63946;
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            display: inline-block;
            margin-left: 8px;
        }
        
        .invitation_actions {
            display: flex;
            gap: 12px;
        }
        
        .action_btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
        }
        
        .accept_btn {
            background: #4CAF50;
            color: white;
        }
        
        .accept_btn:hover {
            background: #388E3C;
        }
        
        .reject_btn {
            background: #f44336;
            color: white;
        }
        
        .reject_btn:hover {
            background: #d32f2f;
        }
        
        .no_invitations {
            text-align: center;
            padding: 40px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
        }
        
        .no_invitations_icon {
            font-size: 60px;
            color: #e0e0e0;
            margin-bottom: 20px;
        }
        
        .no_invitations_text {
            font-size: 18px;
            color: #666;
            margin-bottom: 30px;
        }

        .video_grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        
        .video_card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        
        .video_card:hover {
            transform: scale(1.03);
        }
        
        .thumbnail_container {
            position: relative;
            width: 100%;
            padding-top: 56.25%; /* 16:9 Aspect Ratio */
            overflow: hidden;
        }
        
        .thumbnail_img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .video_info {
            padding: 12px;
        }
        
        .video_title {
            font-weight: 500;
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            font-size: 16px;
            line-height: 1.4;
        }
        
        .channel_name {
            color: #606060;
            font-size: 14px;
            margin-bottom: 4px;
        }
        
        .video_meta {
            display: flex;
            color: #606060;
            font-size: 13px;
        }
        
        .video_views {
            margin-right: 12px;
        }
        
        .upload_date {
            position: relative;
        }
        
        .upload_date::before {
            content: "•";
            margin-right: 8px;
        }
        
        /* Empty state */
        .empty_state {
            text-align: center;
            padding: 50px 20px;
            grid-column: 1 / -1;
        }
        
        .empty_icon {
            font-size: 60px;
            color: #e0e0e0;
            margin-bottom: 20px;
        }
        
        .empty_text {
            font-size: 18px;
            color: #606060;
            margin-bottom: 30px;
        }
        
        .upload_btn {
            display: inline-block;
            background: #ff0000;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.2s;
        }
        
        .upload_btn:hover {
            background: #cc0000;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .video_grid {
                padding: 10px;
            }
        }

        /* Fixed dropdown positioning */
        .profile_hover_container {
            /* take it out of the normal flow and pin to the right */
            position: absolute;
            right: 30px;            /* adjust this to match your .right_side padding */
            top: 50%;               /* vertically center in the 60px‑high top_bar */
            transform: translateY(-50%);
            /* no more display:inline-block needed */
        }
                        
        .logout_dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 10%;              /* sits directly below the icon */
            margin-top: 8px;
            background: #fff;
            min-width: 160px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 4px;
            z-index: 100;
            padding: 10px;
        }

        .account_icon {
            position: relative;     /* now relative to the container, not the page */
            font-size: 30px;
            color: #e63946;
            cursor: pointer;
            transition: color 0.3s ease;
        }
                    
        .profile_hover_container:hover .logout_dropdown {
            display: block;
        }
        
        .logout_dropdown a {
            display: block;
            padding: 8px 12px;
            color: #333;
            text-decoration: none;
            transition: background 0.2s;
        }
        
        .logout_dropdown a:hover {
            background: #f0f0f0;
        }
        
        .switch_btn {
            border-bottom: 1px solid #eee;
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
                <?php if($baId === null):?>
                    <li>
                        <a href="subscription.php"><i class="fas fa-star"></i>Subscription</a>
                    </li>
                    <li>
                        <a href="notification.php"><i class="fas fa-bell"></i>Notification</a>
                    </li>
                    <li>
                        <a href="collaboration.php"><i class="fas fa-handshake"></i>Collaboration</a>
                    </li>
                <?php endif; ?>
                
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
            <!-- Search dan Profil -->
            <div class="top_bar">
                <div class="profile_hover_container">
                    <i class="fas fa-user-circle account_icon"></i>
                    <!-- Kotak logout yang muncul saat hover -->
                    <div class="logout_dropdown">
                        <?php if ($baId === null): ?>
                            <a href="loginBrand.php" class="switch_btn">Ke Brand</a>
                            <a href="../Index/login.php" class="logout_btn">Logout</a>
                        <?php else: ?>
                            <a href="login.php" class="switch_btn">Ke Personal</a>
                            <a href="../Index/loginBrand.php" class="logout_btn">Logout</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>


            <!-- Konten Notifikasi -->
            <div class="notification_content">
                <h2 class="section_title">Undangan Role</h2>
                
                <?php if (count($invitations) > 0): ?>
                    <div class="invitation_list">
                        <?php foreach ($invitations as $invitation): ?>
                            <div class="invitation_card">
                                <div class="channel_info">
                                    <?php if (!empty($invitation['channel_pfp'])): ?>
                                        <img src="<?= htmlspecialchars($invitation['channel_pfp']) ?>" 
                                            alt="Channel Profile" 
                                            class="channel_pfp_img"
                                            onerror="this.src='default_pfp.jpg'">
                                    <?php else: ?>
                                        <div class="channel_pfp_img" style="background: #e0e0e0; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-user-circle" style="font-size: 40px; color: #888;"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="channel_text">
                                        <div class="channel_name"><?= htmlspecialchars($invitation['channel_name']) ?></div>
                                        <div class="invitation_text"> Mengundang Anda sebagai<em class="role_badge"><?= htmlspecialchars($invitation['role']) ?></em></div>
                                        
                                    </div>
                                </div>
                                
                                <div class="invitation_actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="accept">
                                        <input type="hidden" name="kirimId" value="<?= $invitation['kirimId'] ?>">
                                        <input type="hidden" name="chnlId" value="<?= $invitation['chnlId'] ?>">
                                        <input type="hidden" name="role" value="<?= htmlspecialchars($invitation['role']) ?>">
                                        <button type="submit" class="action_btn accept_btn">Terima</button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="kirimId" value="<?= $invitation['kirimId'] ?>">
                                        <input type="hidden" name="chnlId" value="<?= $invitation['chnlId'] ?>">
                                        <input type="hidden" name="role" value="<?= htmlspecialchars($invitation['role']) ?>">
                                        <button type="submit" class="action_btn reject_btn">Tolak</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no_invitations">
                        <div class="no_invitations_icon">
                            <i class="fas fa-envelope-open"></i>
                        </div>
                        <h3 class="no_invitations_text">Tidak ada undangan saat ini</h3>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </body>
    </html>
