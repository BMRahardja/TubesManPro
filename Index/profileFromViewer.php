<?php
session_start();
require_once 'koneksiDB.php';

$userId = $_SESSION['userId'] ?? null;
if (!$userId) {
    header("Location: ../Index/login.php");
    exit();
}

// Get channel ID from URL
$chnlId = $_GET['chnlId'] ?? null;
if (!$chnlId) die("Channel ID required");

$baId = isset($_SESSION['baId'])? $_SESSION['baId']: null;

// Set channelId for subscription
$channelId = $chnlId;

// Fetch channel details with subscriber count and video count
$sqlChannel = "
    SELECT 
      c.nama, 
      c.pfp, 
      c.banner, 
      c.[desc],
      c.tipe,
      u_personal.username AS personal_username,
      u_brand.username    AS brand_username,
      (SELECT COUNT(*) FROM Subscribe s WHERE s.chnlId = c.chnlId) AS subCount,
      (SELECT COUNT(*) FROM Video v WHERE v.chnlId = c.chnlId AND v.status = 'up') AS videoCount
    FROM Channel c
    LEFT JOIN [User] u_personal 
      ON c.userId = u_personal.userId
    LEFT JOIN BrandAcc b 
      ON c.baId = b.baId
    LEFT JOIN [User] u_brand 
      ON b.userId = u_brand.userId
    WHERE c.chnlId = ?
";
$params = [ $channelId ];
$stmt = sqlsrv_query($conn, $sqlChannel, $params);
if( $stmt === false ) {
    die( print_r(sqlsrv_errors(), true) );
}
$channel = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

// pick the right username based on type
if ($channel['tipe'] === 'brand') {
     $channel['username'] = $channel['brand_username'];
} else {
    $channel['username'] = $channel['personal_username'];
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

$role = null;
$a = "SELECT [role] FROM AdaRole WHERE chnlId = ? AND userId = ?";
$params = [ $chnlId, $userId ];
$stmt = sqlsrv_query($conn, $a, $params);

if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

if (sqlsrv_has_rows($stmt)) {
    $row  = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $role = $row['role'];  
}

// Handle subscription actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subscribe_action'])) {
    $channelId = $_POST['channel_id'];
    $action = $_POST['subscribe_action'];
    
    if ($action === 'subscribe') {
        $sqlSubscribe = "INSERT INTO Subscribe (userId, chnlId) VALUES (?, ?)";
        sqlsrv_query($conn, $sqlSubscribe, array($userId, $channelId));
    } elseif ($action === 'unsubscribe') {
        $sqlUnsubscribe = "DELETE FROM Subscribe WHERE userId = ? AND chnlId = ?";
        sqlsrv_query($conn, $sqlUnsubscribe, array($userId, $channelId));
    }
    
    // Redirect to refresh page
    header("Location: profileFromViewer.php?chnlId=$chnlId");
    exit();
}

// Check if user is subscribed to this channel
$isSubscribed = false;
$sqlCheckSub = "SELECT 1 FROM Subscribe WHERE userId = ? AND chnlId = ?";
$paramsCheckSub = array($userId, $channelId);
$stmtCheckSub = sqlsrv_query($conn, $sqlCheckSub, $paramsCheckSub);

if ($stmtCheckSub && sqlsrv_has_rows($stmtCheckSub)) {
    $isSubscribed = true;
}

// Fetch channel videos with view counts
$sqlVideos = "SELECT 
                v.videoId, 
                v.judul, 
                v.thumbnail, 
                v.tglUpld,
                COUNT(vw.videoId) AS view_count
              FROM Video v
              LEFT JOIN [View] vw ON v.videoId = vw.videoId
              WHERE v.chnlId = ? AND v.status = 'up'
              GROUP BY v.videoId, v.judul, v.thumbnail, v.tglUpld
              ORDER BY v.tglUpld DESC";
$stmtVideos = sqlsrv_query($conn, $sqlVideos, array($chnlId));
$videos = [];
while ($row = sqlsrv_fetch_array($stmtVideos, SQLSRV_FETCH_ASSOC)) {
    $videos[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= $channel['nama'] ?> - MeTube</title>
    <link rel="stylesheet" href="../Styles/home_styles.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.12.1/css/all.min.css" />
    <style>
        /* Channel header styles */
        .channel_header {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .channel_banner {
            width: 100%;
            height: 180px;
            background-color: #f1f1f1;
            background-size: cover;
            background-position: center;
        }
        
        .channel_info_container {
            display: flex;
            padding: 20px;
            align-items: center;
            gap: 20px;
        }
        
        .channel_pfp_large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            background-color: #f1f1f1;
        }
        
        .channel_text_info {
            flex: 1;
        }
        
        .channel_title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .channel_stats {
            color: #606060;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .subscribe_btn {
            padding: 8px 16px;
            border-radius: 4px;
            border: none;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
            margin-top: 10px;
        }

        .subscribe_btn.subscribed {
            background-color: white;
            color: #e63946;
            border: 1px solid #e63946;
        }

        .subscribe_btn.unsubscribed {
            background-color: #e63946;
            color: white;
        }
        
        .channel_description {
            color: #333;
            line-height: 1.5;
        }
        
        /* Video grid styles (same as home.php) */
        .video_grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 20px;
            padding: 0;
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
        
        /* Profile info styles */
        .profile_info {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
        }
        
        .profile_image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            background-color: #f1f1f1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .profile_image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile_details {
            flex: 1;
        }
        
        .profile_details h2 {
            margin-bottom: 5px;
        }
        
        .username {
            color: #606060;
            margin-bottom: 10px;
        }
        
        .profile_stats {
            color: #606060;
            font-size: 14px;
        }
        
        .profile_description {
            padding: 0 20px 20px;
            color: #333;
            line-height: 1.5;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .video_grid {
                padding: 10px;
            }
            
            .channel_info_container,
            .profile_info {
                flex-direction: column;
                text-align: center;
            }
            
            .channel_pfp_large,
            .profile_image {
                width: 100px;
                height: 100px;
            }
        }

        .custom_btn {
            padding: 10px 20px;
            background-color: #e63946;
            border: none;
            color: white;
            border-radius: 25px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            margin-bottom: 40px;
        }
        
        .custom_btn:hover {
            background-color: #ac3333;
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
    <input type="checkbox" id="check" />

    <div class="container">
        <!-- Sidebar -->
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
                
                <?php foreach ($userChannels as $ch): ?>
                <li>
                    <a href="profile.php?chnlId=<?= $ch['chnlId'] ?>">
                        <?php if (!empty($ch['pfp'])): ?>
                            <img src="<?= $ch['pfp'] ?>" alt="Profile" class="channel_pfp">
                        <?php else: ?>
                            <i class="fas fa-user-circle"></i>
                        <?php endif; ?>
                        <?= $ch['nama'] ?>
                    </a>
                </li>
                <?php endforeach; ?>
                
                <li>
                    <a href="addChannel.php"><i class="fas fa-users"></i>Add Channel</a>
                </li>
            </ol>
        </div>

        <!-- Konten Kanan -->
        <div class="right_side">
            <!-- Banner -->
            <div class="profile_banner">
                <?php if (!empty($channel['banner'])): ?>
                    <img src="<?= htmlspecialchars($channel['banner']) ?>" 
                         alt="Banner Channel" 
                         style="width:100%; height:200px; object-fit:cover; border-radius:10px;" />
                <?php else: ?>
                    <div style="background:linear-gradient(45deg, #e63946, #a8dadc); width:100%; height:200px; border-radius:10px;"></div>
                <?php endif; ?>
            </div>

            <!-- Info Profil -->
            <div class="profile_info">
                <div class="profile_image">
                    <?php if (!empty($channel['pfp'])): ?>
                        <img src="<?= htmlspecialchars($channel['pfp']) ?>" alt="Foto Profil" />
                    <?php else: ?>
                        <i class="fas fa-user" style="font-size:50px; color:#777;"></i>
                    <?php endif; ?>
                </div>
                <div class="profile_details">
                    <h2><?= htmlspecialchars($channel['nama']) ?> </h2>
                    <p class="username">@<?= htmlspecialchars($channel['username']) ?></p>
                    <p class="profile_stats">
                        <?= number_format($channel['subCount']) ?> Subscribers&nbsp;•&nbsp;
                        <?= number_format($channel['videoCount']) ?> Videos
                    </p>
                    
                    <!-- SUBSCRIBE BUTTON - FIXED POSITION -->
                    <form method="POST" action="profileFromViewer.php?chnlId=<?= $chnlId ?>">
                        <input type="hidden" name="channel_id" value="<?= $channelId ?>">
                        <?php if ($isSubscribed): ?>
                            <input type="hidden" name="subscribe_action" value="unsubscribe">
                            <button type="submit" class="subscribe_btn subscribed">Unsubscribe</button>
                        <?php else: ?>
                            <input type="hidden" name="subscribe_action" value="subscribe">
                            <button type="submit" class="subscribe_btn unsubscribed">Subscribe</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Deskripsi -->
            <div class="profile_description">
                <p>
                    <?= !empty($channel['desc']) ? nl2br(htmlspecialchars($channel['desc'])) : 'No description available' ?>
                </p>
            </div>

            <!-- Tombol edit role -->
            <div class="profile_actions">
                <?php if ($role !== null): ?>
                    <?php if ($role === 'Sub Editor' or $role === 'Limited Editor'): ?>
                        <?php if ($role === 'Sub Editor'): ?>
                            <a href="channelContent.php?chnlId=<?= $chnlId ?>" class="custom_btn">Edit Subtitle</a>
                            <a href="dashboard.php?chnlId=<?= $chnlId ?>" class="custom_btn">Lihat Dashboard</a>
                        <?php else: ?>
                            <a href="dashboard.php?chnlId=<?= $chnlId ?>" class="custom_btn">Lihat Dashboard</a>
                            <a href="channelContent.php?chnlId=<?= $chnlId ?>" class="custom_btn">Customize Videos</a>
                        <?php endif; ?>
                    <?php elseif ($role === 'Viewer'): ?>
                        <a href="dashboard.php?chnlId=<?= $chnlId ?>" class="custom_btn">Lihat Dashboard</a>
                    <?php elseif ($role === 'Manager'): ?>
                        <a href="dashboard.php?chnlId=<?= $chnlId ?>" class="custom_btn">Lihat Dashboard</a>
                        <a href="channelContent.php?chnlId=<?= $chnlId ?>" class="custom_btn">Customize Videos</a>
                        <a href="editChannel.php?chnlId=<?= $chnlId ?>" class="custom_btn">Customize Channel</a>
                        <a href="invite.php?chnlId=<?= $chnlId ?>" class="custom_btn">Invite User</a>
                    <?php else: ?>
                        <a href="dashboard.php?chnlId=<?= $chnlId ?>" class="custom_btn">Lihat Dashboard</a>
                        <a href="channelContent.php?chnlId=<?= $chnlId ?>" class="custom_btn">Customize Videos</a>
                        <a href="editChannel.php?chnlId=<?= $chnlId ?>" class="custom_btn">Customize Channel</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Video Grid -->
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
                        <h2 class="empty_text">No videos available in this channel</h2>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>