<?php
session_start();
require_once 'koneksiDB.php';

$userId = $_SESSION['userId'] ?? null;
if (!$userId) {
    header("Location: ../Index/login.php");
    exit();
}
$baId = isset($_SESSION['baId'])? $_SESSION['baId']: null;

// Get channel ID and video ID from URL
$chnlId = $_GET['chnlId'] ?? null;
$videoId = $_GET['videoId'] ?? null;

if (!$chnlId || !$videoId) die("Channel ID and Video ID required");

// Fetch channel details
$sqlChannel = "SELECT nama, pfp FROM Channel WHERE chnlId = ?";
$params = array($chnlId);
$stmt = sqlsrv_query($conn, $sqlChannel, $params);
$channel = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

// Get current video details
$sqlVideo = "SELECT * FROM Video WHERE videoId = ? AND chnlId = ?";
$paramsVideo = array($videoId, $chnlId);
$stmtVideo = sqlsrv_query($conn, $sqlVideo, $paramsVideo);
$video = sqlsrv_fetch_array($stmtVideo, SQLSRV_FETCH_ASSOC);

if (!$video) die("Video not found in this channel");

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

// Process video update
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $judul = $_POST['judul'] ?? $video['judul'];
    $deskripsi = $_POST['deskripsi'] ?? $video['desc'];
    
    // Initialize with existing values
    $thumbnailPath = $video['thumbnail'];
    $subPath = $video['subtitle'];
    $videoPath = $video['playback'];

    // Handle thumbnail upload if provided
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $thumbnailPath = "../img/" . uniqid() . '_' . $_FILES['image_file']['name'];
        if (!move_uploaded_file($_FILES['image_file']['tmp_name'], $thumbnailPath)) {
            $error = "Failed to upload thumbnail";
        }
    }

    // Handle subtitle upload if provided
    if (isset($_FILES['srt_file']) && $_FILES['srt_file']['error'] === UPLOAD_ERR_OK) {
        $subPath = "../sub/" . uniqid() . '_' . $_FILES['srt_file']['name'];
        if (!move_uploaded_file($_FILES['srt_file']['tmp_name'], $subPath)) {
            $error = "Failed to upload subtitle";
        }
    }

    // Handle video upload if provided
    if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
        $videoPath = "../Videos/" . uniqid() . '_' . $_FILES['video_file']['name'];
        if (!move_uploaded_file($_FILES['video_file']['tmp_name'], $videoPath)) {
            $error = "Failed to upload video";
        }
    }

    // Update database if no errors
    if (!$error) {
        $sql = "UPDATE Video SET 
                judul = ?,
                [desc] = ?,
                thumbnail = ?,
                subtitle = ?,
                playback = ?
                WHERE videoId = ? AND chnlId = ?";
        
        $params = array(
            $judul,
            $deskripsi,
            $thumbnailPath,
            $subPath,
            $videoPath,
            $videoId,
            $chnlId
        );
        
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt) {
            $success = "Video Teredit!";
            // Refresh video data
            $stmtVideo = sqlsrv_query($conn, $sqlVideo, $paramsVideo);
            $video = sqlsrv_fetch_array($stmtVideo, SQLSRV_FETCH_ASSOC);
        } else {
            $error = "Update failed: " . print_r(sqlsrv_errors(), true);
        }
    }
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
    <title>Edit Video - <?= $channel['nama'] ?></title>
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
        
        /* Message styling */
        .error {
            color: #e63946;
            background: #ffe6e6;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .success {
            color: #2a9d8f;
            background: #e6f7f5;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        /* Preview styling */
        .preview_section {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .preview_title {
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        
        .preview_container {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .preview_thumbnail {
            max-width: 200px;
            border-radius: 4px;
        }
        
        .preview_video {
            max-width: 300px;
            border-radius: 4px;
        }
        
        .preview_details {
            flex: 1;
            min-width: 300px;
        }
        
        .preview_details p {
            margin: 5px 0;
            color: #555;
        }
        
        /* Sidebar styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: sans-serif;
        }

        body {
            overflow: hidden;
        }

        #check {
            appearance: none;
            visibility: hidden;
            display: none;
        }

        .container {
            display: flex;
            height: 100vh;
            width: 100%;
        }

        .left_side {
            width: 250px;
            background: #e63946;
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            transform: translateX(-250px);
            transition: transform 0.5s ease;
            z-index: 10;
        }

        .container .head {
            color: #fff;
            font-size: 30px;
            font-weight: bold;
            padding: 30px;
            text-transform: uppercase;
            text-align: center;
            letter-spacing: 3px;
            background: linear-gradient(30deg, #ac3333, #e63946);
        }

        ol {
            width: 100%;
            list-style: none;
        }

        ol li {
            display: block;
            width: 100%;
        }

        ol li a {
            color: #fff;
            padding: 15px 10px;
            text-decoration: none;
            display: block;
            font-size: 20px;
            letter-spacing: 1px;
            position: relative;
            transition: 0.3s;
            overflow: hidden;
        }

        ol li a i {
            width: 70px;
            font-size: 25px;
            text-align: center;
            padding-left: 30px;
        }

        ol li:hover a {
            background: #030303;
            color: rgba(236, 236, 237, 0.667);
            letter-spacing: 0.5px;
        }

        span {
            position: absolute;
            right: -40px;
            top: 30px;
            font-size: 25px;
            border-radius: 3px;
            color: #fff;
            padding: 3px 8px;
            cursor: pointer;
            background: #000;
            z-index: 20;
        }

        #bars {
            background: #e63946;
        }

        #check:checked ~ .container .left_side {
            transform: translateX(0);
        }

        #check:checked ~ .container #bars {
            display: none;
        }

        #check:checked ~ .container .right_side {
            margin-left: 250px;
            transition: margin-left 0.5s ease;
        }

        .right_side {
            flex: 1;
            padding: 30px;
            background-color: #f8f8f8;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: margin-left 0.5s ease;
        }
    </style>
</head>
<body>
    <input type="checkbox" id="check" />

    <div class="container">
        <!-- Restored Sidebar -->
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

            <div class="upload_section">
                <h2>Edit Video</h2>
                
                <?php if ($error): ?>
                    <div class="error"><?= $error ?></div>
                <?php elseif ($success): ?>
                    <div class="success"><?= $success ?></div>
                <?php endif; ?>
                
                <!-- Current Video Preview -->
                <div class="preview_section">
                    <div class="preview_title">Video:</div>
                    <div class="preview_container">
                        <div>
                            <img src="<?= htmlspecialchars($video['thumbnail']) ?>" 
                                 alt="Thumbnail" 
                                 class="preview_thumbnail"
                                 onerror="this.onerror=null;this.src='default_thumbnail.jpg';">
                        </div>
                        <div class="preview_details">
                            <p><strong>Title:</strong> <?= htmlspecialchars($video['judul']) ?></p>
                            <p><strong>Description:</strong> <?= htmlspecialchars($video['desc']) ?></p>
                            <p><strong>Video:</strong> <?= basename($video['playback']) ?></p>
                            <p><strong>Subtitle:</strong> <?= $video['subtitle'] ? basename($video['subtitle']) : 'None' ?></p>
                        </div>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data" class="upload_form">
                    <?php if ($role !== 'Sub Editor'): ?>
                        <label for="judul">Ganti Title</label>
                        <input type="text" id="judul" name="judul" 
                            placeholder="Enter new title" 
                            value="<?= htmlspecialchars($video['judul']) ?>" />
                        
                        <label for="deskripsi">Ganti Deskripsi</label>
                        <textarea id="deskripsi" name="deskripsi" rows="4" 
                                placeholder="Enter new description"><?= htmlspecialchars($video['desc']) ?></textarea>

                        <label for="video_file">Ganti Video File</label>
                        <input type="file" id="video_file" name="video_file" accept="video/*" />

                        <label for="thumbnail_file">Ganti Thumbnail </label>
                        <input type="file" id="image_file" name="image_file" accept="image/*" />
                    <?php endif; ?>
                    <label for="subtitle_file">Ganti Subtitle</label>
                    <input type="file" id="srt_file" name="srt_file" accept=".srt" />

                    <button type="submit" name="update" class="upload_btn">Update Video</button>
                </form>
            </div>            
        </div>
    </div>
</body>
</html>