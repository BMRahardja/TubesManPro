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

// Process video upload
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload'])) {
    $judul = $_POST['judul'] ?? '';
    $deskripsi = $_POST['deskripsi'];
    $tglUpld = date('Y-m-d');
    $status = 'up';
    
    // Handle video file upload
    $videoPath = '';
    $thumbnailPath = "../img/" . $_FILES['image_file']['name'];
    $sub = "../sub/". $_FILES['srt_file']['name'];
    
    if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
        $videoTarget = "../Videos/" . $_FILES['video_file']['name'];
            $sql = "INSERT INTO Video (tglUpld, judul, [desc], status, chnlId, userId, thumbnail, subtitle, playback)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = array(
                $tglUpld,
                $judul,
                $deskripsi,
                $status,
                $chnlId,
                $userId,
                $thumbnailPath,
                $sub,
                $videoTarget
            );
            
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt) {
                header("Location: channelContent.php?chnlId=$chnlId");
                exit();
            }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Upload Videos - <?= $channel['nama'] ?></title>
    <link rel="stylesheet" href="../Styles/channelContentUpload_styles.css" />
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
                <button class="tab_btn active">Upload</button>
                <button class="tab_btn" onclick="location.href='channelContentDelete.php?chnlId=<?= $chnlId ?>'">
                    Delete
                </button>
            </div>

            <div class="upload_section">
                <h2>Upload Videos</h2>
                
                <?php if ($error): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="upload_form">
                    <label for="judul">Judul Video</label>
                    <input type="text" id="judul" name="judul" placeholder="Masukkan judul video" required />

                    <label for="deskripsi">Deskripsi</label>
                    <textarea id="deskripsi" name="deskripsi" rows="4" placeholder="Tulis deskripsi video..."></textarea>

                    <label for="video_file">Pilih File Video</label>
                    <input type="file" id="video_file" name="video_file" accept="video/*" required />

                    <label for="thumbnail_file">Pilih File Thumbnail</label>
                    <input type="file" id="image_file" name="image_file" accept="image/*" required />

                    <label for="subtitle_file">Pilih File Subtitle</label>
                    <input type="file" id="srt_file" name="srt_file" accept="srt/*" />

                    <button type="submit" name="upload" class="upload_btn">Upload</button>
                </form>
            </div>            
        </div>
    </div>
</body>
</html>