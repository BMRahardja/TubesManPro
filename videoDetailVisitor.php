<?php
session_start();
require_once 'koneksiDB.php';

// Ensure consistent session variable naming
$userId = $_SESSION['userId'];

// Redirect if not logged in
if (!$userId) {
    header("Location: ../Index/login.php");
    exit();
}

// Store consistent session variable
$_SESSION['userId'] = $userId;

// Get video ID from URL
if (!isset($_GET['videoId'])) {
    header("Location: home.php");
    exit();
}
$videoId = $_GET['videoId'];

// Get user channels (for sidebar)
$sqlChannels = "SELECT chnlId, nama, pfp FROM Channel WHERE userId = ?";
$paramsChannels = array($userId);
$stmtChannels = sqlsrv_query($conn, $sqlChannels, $paramsChannels);

$userChannels = [];
if ($stmtChannels !== false) {
    while ($row = sqlsrv_fetch_array($stmtChannels, SQLSRV_FETCH_ASSOC)) {
        $userChannels[] = $row;
    }
}

// Record view if not already viewed
$sqlCheckView = "SELECT 1 FROM [View] WHERE userId = ? AND videoId = ?";
$paramsCheckView = array($userId, $videoId);
$stmtCheckView = sqlsrv_query($conn, $sqlCheckView, $paramsCheckView);

if (sqlsrv_has_rows($stmtCheckView) === false) {
    $sqlInsertView = "INSERT INTO [View] (userId, videoId, durasi, tglView, waktuView)
                      VALUES (?, ?, 0, CONVERT(date, GETDATE()), CONVERT(time, GETDATE()))";
    $paramsInsertView = array($userId, $videoId);
    sqlsrv_query($conn, $sqlInsertView, $paramsInsertView);
}

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    $comment = trim($_POST['comment']);
    if (!empty($comment)) {
        // Get current date and time
        $currentDateTime = date('Y-m-d H:i:s');
        
        // Insert comment with composite key
        $sqlInsertComment = "INSERT INTO Komen (userId, videoId, konten, tanggalKomen) 
                             VALUES (?, ?, ?, ?)";
        $paramsInsertComment = array($userId, $videoId, $comment, $currentDateTime);
        
        $stmtInsertComment = sqlsrv_query($conn, $sqlInsertComment, $paramsInsertComment);
        
        if ($stmtInsertComment === false) {
            die(print_r(sqlsrv_errors(), true));
        }
        
        // Redirect to refresh page and show new comment
        header("Location: videoDetail.php?videoId=$videoId");
        exit();
    }
}

// Handle reaction (like/dislike) actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reaction'])) {
    $reaction = $_POST['reaction'];
    
    // First remove any existing reaction
    $sqlDelete = "DELETE FROM Reaksi WHERE userId = ? AND videoId = ?";
    $paramsDelete = array($userId, $videoId);
    sqlsrv_query($conn, $sqlDelete, $paramsDelete);
    
    // If the user is adding a reaction (not removing)
    if ($reaction !== 'remove') {
        $sqlInsert = "INSERT INTO Reaksi (userId, videoId, tipe) VALUES (?, ?, ?)";
        $paramsInsert = array($userId, $videoId, $reaction);
        sqlsrv_query($conn, $sqlInsert, $paramsInsert);
    }
    
    // Redirect to refresh page
    header("Location: videoDetail.php?videoId=$videoId");
    exit();
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
    header("Location: videoDetail.php?videoId=$videoId");
    exit();
}

$userReaction = null;
$sqlUserReaction = "SELECT tipe FROM Reaksi WHERE userId = ? AND videoId = ?";
$paramsUserReaction = array($userId, $videoId);
$stmtUserReaction = sqlsrv_query($conn, $sqlUserReaction, $paramsUserReaction);

if ($stmtUserReaction && $row = sqlsrv_fetch_array($stmtUserReaction, SQLSRV_FETCH_ASSOC)) {
    $userReaction = $row['tipe'];
}

// Get video details
$sqlVideo = "SELECT v.*, c.nama AS channel_name, c.pfp AS channel_pfp, c.chnlId
             FROM Video v
             JOIN Channel c ON v.chnlId = c.chnlId
             WHERE v.videoId = ?";
$paramsVideo = array($videoId);
$stmtVideo = sqlsrv_query($conn, $sqlVideo, $paramsVideo);

if ($stmtVideo === false || !sqlsrv_has_rows($stmtVideo)) {
    header("Location: home.php");
    exit();
}
$video = sqlsrv_fetch_array($stmtVideo, SQLSRV_FETCH_ASSOC);

// Format upload date
$uploadDate = $video['tglUpld']->format('M d, Y');

// Get view count
$sqlViews = "SELECT COUNT(*) AS view_count FROM [View] WHERE videoId = ?";
$paramsViews = array($videoId);
$stmtViews = sqlsrv_query($conn, $sqlViews, $paramsViews);
$viewCount = sqlsrv_fetch_array($stmtViews, SQLSRV_FETCH_ASSOC)['view_count'];

// Get like/dislike counts
$sqlLikes = "SELECT 
                SUM(CASE WHEN tipe = 'like' THEN 1 ELSE 0 END) AS like_count,
                SUM(CASE WHEN tipe = 'dislike' THEN 1 ELSE 0 END) AS dislike_count
             FROM Reaksi
             WHERE videoId = ?";
$paramsLikes = array($videoId);
$stmtLikes = sqlsrv_query($conn, $sqlLikes, $paramsLikes);
$reactions = sqlsrv_fetch_array($stmtLikes, SQLSRV_FETCH_ASSOC);
$likeCount = $reactions['like_count'] ?: 0;
$dislikeCount = $reactions['dislike_count'] ?: 0;

// Get comments with usernames
$sqlComments = "SELECT k.*, u.username 
                FROM Komen k
                JOIN [User] u ON k.userId = u.userId
                WHERE k.videoId = ?
                ORDER BY k.tanggalKomen DESC";
$paramsComments = array($videoId);
$stmtComments = sqlsrv_query($conn, $sqlComments, $paramsComments);

$comments = [];
if ($stmtComments !== false) {
    while ($row = sqlsrv_fetch_array($stmtComments, SQLSRV_FETCH_ASSOC)) {
        $comments[] = $row;
    }
}

// Check if user is subscribed to this channel
$isSubscribed = false;
$channelId = $video['chnlId'];
$sqlCheckSub = "SELECT 1 FROM Subscribe WHERE userId = ? AND chnlId = ?";
$paramsCheckSub = array($userId, $channelId);
$stmtCheckSub = sqlsrv_query($conn, $sqlCheckSub, $paramsCheckSub);

if ($stmtCheckSub && sqlsrv_has_rows($stmtCheckSub)) {
    $isSubscribed = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($video['judul']) ?> - MeTube</title>
    <link rel="stylesheet" href="../Styles/videoDetail_styles.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.12.1/css/all.min.css" />
    <style>
        /* Add styles from home.php */
        .video_grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 20px;
            padding: 20px;
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

        /* Video detail specific styles */
        .video_detail_container {
            max-width: 900px;
            margin: 30px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        .video_player video {
            width: 100%;
            border-radius: 10px;
        }

        .video_info {
            margin-top: 20px;
        }

        .video_title {
            font-size: 24px;
            margin-bottom: 10px;
            color: #222;
        }

        .video_stats {
            display: flex;
            align-items: center;
            font-size: 14px;
            color: #555;
            margin-bottom: 15px;
            gap: 15px;
        }

        .video_actions {
            display: flex;
            gap: 10px;
        }

        .video_actions button {
            background: none;
            border: none;
            font-size: 16px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 4px;
        }

        .video_meta {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .video_description {
            font-size: 16px;
            color: #333;
            line-height: 1.6;
            white-space: pre-line;
            margin-bottom: 20px;
        }

        /* Channel info section */
        .channel_info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .channel_pfp_large {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }

        .channel_details {
            flex: 1;
        }

        .channel_name {
            font-weight: bold;
            font-size: 18px;
        }

        .subscribe_btn {
            padding: 8px 16px;
            border-radius: 4px;
            border: none;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
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

        /* Comments section */
        .comments_section {
            margin-top: 20px;
        }

        .comments_section h3 {
            margin-bottom: 10px;
            color: #333;
        }

        .comment_input {
            width: 100%;
            height: 80px;
            padding: 10px;
            font-size: 14px;
            border-radius: 8px;
            border: 1px solid #ccc;
            resize: vertical;
            margin-bottom: 10px;
        }

        .video_actions button {
            /* ... existing button styles ... */
            transition: all 0.3s ease;
        }

        .video_actions .active {
            background-color: #e63946;
            color: white;
            border-radius: 4px;
        }

        .comment_submit {
            padding: 10px 20px;
            background-color: #e63946;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }

        .comment_list {
            margin-top: 20px;
        }

        /* Comment item */
        .comment_item {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }

        /* Comment header */
        .comment_header {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }

        .comment_username {
            font-weight: bold;
            margin-right: 10px;
        }

        .comment_date {
            color: #777;
            font-size: 0.9em;
        }

        /* Comment content */
        .comment_content {
            display: block;
            margin-top: 5px;
            padding-left: 5px;
        }
    </style>
</head>
<body>
    <input type="checkbox" id="check" />

    <div class="container">
        <!-- Sidebar (same as home.php) -->
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

        <div class="right_side">
            <div class="top_bar">
                <input type="text" placeholder="Search..." class="search_input" />
                <div class="profile_hover_container">
                    <i class="fas fa-user-circle account_icon"></i>
                    <div class="logout_dropdown">
                        <a href="../Index/registration.php" class="logout_btn">Register</a>
                    </div>
                </div>
            </div>

            <div class="video_detail_container">
                <!-- Video Player -->
                <div class="video_player">
                    <video controls poster="<?= htmlspecialchars($video['thumbnail']) ?>">
                        <source src="<?= htmlspecialchars($video['playback']) ?>" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                </div>

                <!-- Video Info -->
                <div class="video_info">
                    <h2 class="video_title"><?= htmlspecialchars($video['judul']) ?></h2>
                    <div class="video_stats">
                    </div>
                    <p class="video_description"><?= htmlspecialchars($video['desc']) ?></p>
                    
                    <!-- Channel Info -->
                    <div class="channel_info">
                        <?php if (!empty($video['channel_pfp'])): ?>
                            <img src="<?= htmlspecialchars($video['channel_pfp']) ?>" 
                                 alt="Channel Profile" 
                                 class="channel_pfp_large"
                                 onerror="this.src='default_pfp.jpg'">
                        <?php else: ?>
                            <div class="channel_pfp_large">
                                <i class="fas fa-user-circle" style="font-size: 50px; color: #ccc;"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="channel_details">
                            <div class="channel_name"><?= htmlspecialchars($video['channel_name']) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Comment Section -->
                <div class="comments_section">
                    <h3>Comments (<?= count($comments) ?>)</h3>

                    <div class="comment_list">
                        <?php foreach ($comments as $comment): ?>
                            <div class="comment_item">
                                <div class="comment_header">
                                    <span class="comment_username"><?= htmlspecialchars($comment['username']) ?></span>
                                    <span class="comment_date">
                                        <?= $comment['tanggalKomen']->format('M d, Y') ?>
                                    </span>
                                </div>
                                <p class="comment_content"><?= htmlspecialchars($comment['konten']) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>  
            </div>
        </div>
    </div>
</body>
</html>