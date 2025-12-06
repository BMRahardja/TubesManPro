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

// Fetch channel details
$sqlChannel = "SELECT nama, pfp FROM Channel WHERE chnlId = ?";
$params = array($chnlId);
$stmt = sqlsrv_query($conn, $sqlChannel, $params);
$channel = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

// Get user channels
$userChannels = [];
$baId = isset($_SESSION['baId'])? $_SESSION['baId']: null;
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
    $sqlChannels = "SELECT chnlId, nama, pfp FROM Channel WHERE userId = ?";
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

$verifySql = null;

// Handle video deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $videoId = $_POST['videoId'] ?? null;
    
    if ($videoId) {
        // Verify that the video belongs to this channel
        $verifySql = "SELECT userId, videoId FROM Video WHERE videoId = ? AND chnlId = ?";
        $verifyParams = array($videoId, $chnlId);
        $verifyStmt = sqlsrv_query($conn, $verifySql, $verifyParams);
        
        if (sqlsrv_has_rows($verifyStmt)) {
            // Start transaction
            sqlsrv_begin_transaction($conn);
            
            try {
                // Delete from View table
                $deleteViewSql = "DELETE FROM [View] WHERE videoId = ?";
                sqlsrv_query($conn, $deleteViewSql, array($videoId));
                
                // Delete from Reaksi table
                $deleteReaksiSql = "DELETE FROM Reaksi WHERE videoId = ?";
                sqlsrv_query($conn, $deleteReaksiSql, array($videoId));
                
                // Delete from Komen table
                $deleteKomenSql = "DELETE FROM Komen WHERE videoId = ?";
                sqlsrv_query($conn, $deleteKomenSql, array($videoId));
                
                // Finally delete the video
                $deleteVideoSql = "DELETE FROM Video WHERE videoId = ?";
                $deleteStmt = sqlsrv_query($conn, $deleteVideoSql, array($videoId));
                
                if ($deleteStmt) {
                    sqlsrv_commit($conn);
                    header("Location: channelContentDelete.php?chnlId=$chnlId");
                    exit();
                } else {
                    throw new Exception("Failed to delete video: " . print_r(sqlsrv_errors(), true));
                }
            } catch (Exception $e) {
                sqlsrv_rollback($conn);
                $error = $e->getMessage();
            }
        } else {
            $error = "Video not found or doesn't belong to this channel";
        }
    } else {
        $error = "Invalid video ID";
    }
}

// Fetch all videos for this channel
$sqlVideos = "SELECT videoId, judul, tglUpld, thumbnail, userId  FROM Video WHERE chnlId = ? ORDER BY tglUpld DESC";
$videoParams = array($chnlId);
$stmtVideos = sqlsrv_query($conn, $sqlVideos, $videoParams);

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
    <title>Delete Videos - <?= $channel['nama'] ?></title>
    <link rel="stylesheet" href="../Styles/channelContentDelete_styles.css" />
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
        
        /* Error message */
        .error {
            color: #e63946;
            background: #ffe6e6;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        /* Thumbnail styling */
        .thumbnail {
            width: 150px;
            height: 90px;
            border-radius: 5px;
            object-fit: cover;
            background-color: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .thumbnail i {
            font-size: 30px;
            color: #555;
        }
        
        .no-videos {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .no-videos i {
            font-size: 60px;
            margin-bottom: 20px;
            color: #ddd;
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

            <!-- Tab Menu -->
            <div class="tab_menu">
                <button class="tab_btn" onclick="location.href='channelContent.php?chnlId=<?= $chnlId ?>'">
                    Videos
                </button>
                <button class="tab_btn" onclick="location.href='channelContentUpload.php?chnlId=<?= $chnlId ?>'">
                    Upload
                </button>
                <button class="tab_btn active">Delete</button>
            </div>

            <div class="delete_section">
                <h2>Delete Videos</h2>
                
                <?php if (isset($error)): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
                
                <?php if (empty($videos)): ?>
                    <div class="no-videos">
                        <i class="fas fa-film"></i>
                        <h3>Tidak ada video</h3>
                    </div>
                <?php else: ?>
                    <div class="video_list">
                        <?php foreach ($videos as $video): 
                            // Format the date
                            $uploadDate = date('d M Y', strtotime($video['tglUpld']->format('Y-m-d')));
                        ?>
                        <div class="video_item">
                            <div class="video_info">
                                <?php if ($video['thumbnail']): ?>
                                    <img src="<?= $video['thumbnail'] ?>" alt="Thumbnail" class="thumbnail">
                                <?php else: ?>
                                    <div class="thumbnail">
                                        <i class="fas fa-film"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="video_text">
                                    <h3><?= $video['judul'] ?></h3>
                                    <p>Dipublikasikan: <?= $uploadDate ?></p>
                                </div>
                            </div>
                            <?php if ($role === "Limited Editor"): ?>
                                <?php if ($video['userId'] === $userId): ?>
                                    <form method="POST" action="">
                                        <input type="hidden" name="videoId" value="<?= $video['videoId'] ?>">

                                            <button type="submit" name="delete" class="delete_btn">Delete</button>

                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <form method="POST" action="">
                                        <input type="hidden" name="videoId" value="<?= $video['videoId'] ?>">
                                        <button type="submit" name="delete" class="delete_btn">Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>  
        </div>
    </div>
    
    <script>
        // Confirmation before deleting
        document.querySelectorAll('.delete_btn').forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('Yakin mau delete?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>