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
// Cari video subscription
$sql = "SELECT 
            v.videoId, 
            v.judul, 
            v.thumbnail, 
            v.tglUpld,
            c.nama AS channel_name,
            c.chnlId AS channel_id,
            COUNT(vw.videoId) AS view_count
        FROM Video v
        JOIN Channel c ON v.chnlId = c.chnlId
        LEFT JOIN [View] vw ON v.videoId = vw.videoId
        WHERE v.status = 'up' AND v.chnlId IN (SELECT chnlId FROM Subscribe)
        GROUP BY v.videoId, v.judul, v.thumbnail, v.tglUpld, c.nama, c.chnlId
        ORDER BY v.tglUpld DESC";
$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

$videos = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $videos[] = $row;
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
                <input type="text" placeholder="Search..." class="search_input" />
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

            <div class="video_grid">
                <?php if (count($videos) > 0): ?>
                    <?php foreach ($videos as $video): ?>
                        <div class="video_card">
                            <a href="videoDetail.php?videoId=<?= $video['videoId'] ?>">
                                <div class="thumbnail_container">
                                    <img 
                                        src="<?= htmlspecialchars($video['thumbnail']) ?>" 
                                        alt="Video Thumbnail" 
                                        class="thumbnail_img"
                                        onerror="this.src='https://via.placeholder.com/300x169?text=Thumbnail+Missing'"
                                    >
                                </div>
                            </a>
                            <div class="video_info">
                                <h3 class="video_title"><?= htmlspecialchars($video['judul']) ?></h3>
                                <!-- CHANGED: Channel name is now a clickable link -->
                                <div class="channel_name">
                                    <a href="profileFromViewer.php?chnlId=<?= $video['channel_id'] ?>">
                                        <?= htmlspecialchars($video['channel_name']) ?>
                                    </a>
                                </div>
                                <div class="video_meta">
                                    <div class="video_views"><?= number_format($video['view_count']) ?> views</div>
                                    <div class="upload_date">
                                        <?= date('M d, Y', strtotime($video['tglUpld']->format('Y-m-d'))) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty_state">
                        <div class="empty_icon">
                            <i class="fas fa-film"></i>
                        </div>
                        <h2 class="empty_text">Anda belum subscribe</h2>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>