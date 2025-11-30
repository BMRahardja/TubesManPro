<?php
session_start();
require_once 'koneksiDB.php';

$userId = $_SESSION['userId'] ?? null;
if (!$userId) {
    header("Location: ../Index/login.php");
    exit();
}
$baId = isset($_SESSION['baId'])? $_SESSION['baId']: null;

// Get channel ID from URL
$chnlId = $_GET['chnlId'] ?? null;
if (!$chnlId) die("Channel ID required");

// Fetch channel details
$sqlChannel = "SELECT nama, pfp FROM Channel WHERE chnlId = ?";
$params = array($chnlId);
$stmt = sqlsrv_query($conn, $sqlChannel, $params);
$channel = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

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
// Fetch channel videos with stats
$sqlVideos = "SELECT 
                v.userId,
                v.videoId,
                v.judul,
                v.thumbnail,
                v.tglUpld,
                (SELECT COUNT(*) FROM [View] WHERE videoId = v.videoId) AS views,
                (SELECT COUNT(*) FROM Reaksi WHERE videoId = v.videoId AND tipe = 'like') AS likes,
                (SELECT COUNT(*) FROM Reaksi WHERE videoId = v.videoId AND tipe = 'dislike') AS dislikes
              FROM Video v
              WHERE v.chnlId = ? AND v.status = 'up'";
$stmtVideos = sqlsrv_query($conn, $sqlVideos, array($chnlId));
$videos = [];
while ($row = sqlsrv_fetch_array($stmtVideos, SQLSRV_FETCH_ASSOC)) {
    $videos[] = $row;
}

//ambil role
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Channel Content - <?= $channel['nama'] ?></title>
    <link rel="stylesheet" href="../Styles/channelContent_styles.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.12.1/css/all.min.css" />
    <style>
        /* Add this style for profile pictures */
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
        <!-- Consistent Sidebar -->
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
                
                <li>
                    <a href="addChannel.php"><i class="fas fa-users"></i>Add Channel</a>
                </li>
            </ol>
        </div>

        <div class="right_side">
            <h2 class="channel_title"><?= $channel['nama'] ?> Content</h2>

            <div class="tab_menu">
                <button class="tab_btn active" onclick="location.href='channelContent.php?chnlId=<?= $chnlId ?>'">
                    Videos
                </button>
                <?php if($role !== 'Sub Editor'): ?>
                    <button class="tab_btn" onclick="location.href='channelContentUpload.php?chnlId=<?= $chnlId ?>'">
                        Upload
                    </button>
                <?php endif; ?>
                <?php if($role !== 'Sub Editor'): ?>
                    <button class="tab_btn" onclick="location.href='channelContentDelete.php?chnlId=<?= $chnlId ?>'">
                        Delete
                    </button>
                <?php endif; ?>
            </div>

            <div class="tab_content">
                <div class="video_table">
                    <div class="video_header">
                        <div class="video_col">Video</div>
                        <div class="video_col"></div>

                    </div>

                    <?php if (count($videos) > 0): ?>
                        <?php foreach ($videos as $video): ?>
                        <div class="video_row">
                            <div class="video_info">
                                <img src="<?= $video['thumbnail'] ?>" 
                                     alt="Thumbnail" 
                                     class="video_thumb"
                                     onerror="this.src='https://via.placeholder.com/160x90'">
                                <div class="video_meta">
                                    <p class="video_title"><?= $video['judul'] ?></p>
                                    <p class="video_date">
                                        Published: <?= date('M d, Y', strtotime($video['tglUpld']->format('Y-m-d'))) ?>
                                    </p>
                                </div>
                            </div>
                            <div class="video_col"></div>
                            <div class="video_col"></div>
                            <?php if ($role === 'Limited Editor'): ?>
                                <?php if ($video['userId'] === $userId): ?>
                                    <div class="video_col"><button class="tab_btn active" onclick="location.href='editVid.php?chnlId=<?= $chnlId ?>&videoId=<?= $video['videoId'] ?>'">Edit</button></div> 
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="video_col"><button class="tab_btn active" onclick="location.href='editVid.php?chnlId=<?= $chnlId ?>&videoId=<?= $video['videoId'] ?>'">Edit</button></div> 
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="video_row" style="grid-column: 1 / -1; text-align: center; padding: 20px;">
                            Tidak ada video yang di post
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>