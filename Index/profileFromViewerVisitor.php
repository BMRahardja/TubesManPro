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

// Fetch channel details with subscriber count and video count
$sqlChannel = "SELECT 
                c.nama, 
                c.pfp, 
                c.banner, 
                c.[desc],
                u.username,
                (SELECT COUNT(*) FROM Subscribe s WHERE s.chnlId = c.chnlId) AS subCount,
                (SELECT COUNT(*) FROM Video v WHERE v.chnlId = c.chnlId AND v.status = 'up') AS videoCount
              FROM Channel c
              JOIN [User] u ON c.userId = u.userId
              WHERE c.chnlId = ?";
$params = array($chnlId);
$stmt = sqlsrv_query($conn, $sqlChannel, $params);
$channel = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

// Fetch user's channels for sidebar
$sqlChannels = "SELECT chnlId, nama, pfp FROM Channel WHERE userId = ?";
$stmtChannels = sqlsrv_query($conn, $sqlChannels, array($userId));
$userChannels = [];
while ($row = sqlsrv_fetch_array($stmtChannels, SQLSRV_FETCH_ASSOC)) {
    $userChannels[] = $row;
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
                    <a href="homeVisitor.php"><i class="fas fa-home"></i>Home</a>
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
                    <h2><?= htmlspecialchars($channel['nama']) ?></h2>
                    <p class="username">@<?= htmlspecialchars($channel['username']) ?></p>
                    <p class="profile_stats">
                        <?= number_format($channel['subCount']) ?> Subscribers&nbsp;•&nbsp;
                        <?= number_format($channel['videoCount']) ?> Videos
                    </p>
                </div>
            </div>

            <!-- Deskripsi -->
            <div class="profile_description">
                <p>
                    <?= !empty($channel['desc']) ? nl2br(htmlspecialchars($channel['desc'])) : 'No description available' ?>
                </p>
            </div>

            <!-- Video Grid -->
            <div class="video_grid">
                <?php if (count($videos) > 0): ?>
                    <?php foreach ($videos as $video): ?>
                        <div class="video_card">
                            <a href="videoDetailVisitor.php?videoId=<?= $video['videoId'] ?>">
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