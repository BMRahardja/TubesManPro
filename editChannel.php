<?php
session_start();
require_once 'koneksiDB.php';

$userId = $_SESSION['userId'];
$chnlId = isset($_GET['chnlId']) ? $_GET['chnlId'] : null;
$baId = isset($_SESSION['baId'])? $_SESSION['baId']: null;

// Fetch current channel details
$sqlCurrent = "SELECT * FROM Channel WHERE chnlId = ?";
$paramsCurrent = array($chnlId, $userId);
$stmtCurrent = sqlsrv_query($conn, $sqlCurrent, $paramsCurrent);
$currentChannel = sqlsrv_fetch_array($stmtCurrent, SQLSRV_FETCH_ASSOC);


// Proses form jika disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
    // Use current values as defaults
    $channelName = $currentChannel['nama'];
    $channelDesc = $currentChannel['desc'];
    $bannerPath = $currentChannel['banner'];
    $pfpPath = $currentChannel['pfp'];

    // Update only if new values are provided
    if (!empty($_POST['channelName'])) {
        $channelName = $_POST['channelName'];
    }
    
    if (!empty($_POST['channelDesc'])) {
        $channelDesc = $_POST['channelDesc'];
    }

    // Handle banner upload
    if (isset($_FILES['bannerUpload']) && $_FILES['bannerUpload']['error'] == UPLOAD_ERR_OK) {
        $bannerTmp = $_FILES['bannerUpload']['tmp_name'];
        $bannerName = uniqid() . '_' . basename($_FILES['bannerUpload']['name']);
        $bannerTarget = "uploads/banners/" . $bannerName;
        
        if (!is_dir('uploads/banners')) {
            mkdir('uploads/banners', 0777, true);
        }
        
        if (move_uploaded_file($bannerTmp, $bannerTarget)) {
            $bannerPath = $bannerTarget;
        }
    }

    // Handle profile picture upload
    if (isset($_FILES['profileUpload']) && $_FILES['profileUpload']['error'] == UPLOAD_ERR_OK) {
        $pfpTmp = $_FILES['profileUpload']['tmp_name'];
        $pfpName = uniqid() . '_' . basename($_FILES['profileUpload']['name']);
        $pfpTarget = "uploads/profiles/" . $pfpName;
        
        if (!is_dir('uploads/profiles')) {
            mkdir('uploads/profiles', 0777, true);
        }
        
        if (move_uploaded_file($pfpTmp, $pfpTarget)) {
            $pfpPath = $pfpTarget;
        }
    }

    // Update channel
    $sql = "UPDATE Channel 
            SET banner = ?, nama = ?, [desc] = ?, pfp = ? 
            WHERE chnlId = ? AND userId = ?";
    $params = array($bannerPath, $channelName, $channelDesc, $pfpPath, $chnlId, $userId);
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt) {
        header("Location: profile.php?chnlId=$chnlId");
        exit();
    } else {
        $error = "Gagal mengupdate channel: " . print_r(sqlsrv_errors(), true);
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
    <title>Add Channel</title>
    <link rel="stylesheet" href="../Styles/addChannel_styles.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.12.1/css/all.min.css" />
    <style>
        /* Reset dan dasar */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: sans-serif;
        }

        body {
            overflow: hidden;
        }

        /* Checkbox disembunyikan secara spesifik */
        #check {
            appearance: none;
            visibility: hidden;
            display: none;
        }

        /* Container utama */
        .container {
            display: flex;
            height: 100vh;
            width: 100%;
        }

        /* Sidebar kiri */
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

        /* Tombol toggle sidebar */
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

        /* Checkbox aktif: sidebar muncul dan konten geser */
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

        /* Konten kanan */
        .right_side {
            flex: 1;
            padding: 30px;
            background-color: #f8f8f8;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .page_title {
            margin-bottom: 40px;
            font-size: 28px;
            font-weight: 600;
            text-align: center;
            color: #333;
        }

        .channel_form_box {
            background-color: #fff;
            width: 100%;
            max-width: 500px;
            padding: 30px 40px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .upload_label,
        .channel_name_label,
        .channel_desc_label {
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
            display: block;
        }

        input[type="file"] {
            border: 2px dashed #ccc;
            padding: 25px;
            border-radius: 8px;
            cursor: pointer;
            background-color: #fafafa;
        }

        input[type="file"]:hover {
            border-color: #e63946;
            background-color: #fff0f0;
        }

        input[type="text"] {
            padding: 12px 15px;
            font-size: 16px;
            border-radius: 8px;
            border: 1.5px solid #ccc;
            outline: none;
            transition: border-color 0.3s ease;
        }

        input[type="text"]:focus {
            border-color: #e63946;
        }

        .create_btn {
            margin-top: 40px;
            padding: 15px 40px;
            background-color: #e63946;
            color: white;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            align-self: center;
            transition: background-color 0.3s ease;
        }

        .create_btn:hover {
            background-color: #ac3333;
        }
        
        /* Tambahan styling untuk channel di sidebar */
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
                
                <li>
                    <a href="addChannel.php"><i class="fas fa-users"></i>Add Channel</a>
                </li>
            </ol>
        </div>

        <!-- Konten Kanan -->
        <div class="right_side">
            <h2 class="page_title">Edit Channel Kamu</h2>

            <?php if (!empty($error)): ?>
                <div class="error"><?= $error ?></div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" class="channel_form_box">
                <label class="upload_label" for="bannerUpload">Upload Banner</label>
                <input type="file" name="bannerUpload" id="bannerUpload" accept="image/*">
                
                <label class="upload_label" for="profileUpload">Upload Profile Photo</label>
                <input type="file" name="profileUpload" id="profileUpload" accept="image/*" >
                
                <label class="channel_name_label" for="channelName">Nama Channel Baru</label>
                <input type="text" name="channelName" id="channelName" 
                       placeholder="Masukan nama channel" >

                <label class="channel_desc_label" for="channelDesc">Deskripsi Channel Baru</label>
                <input type="text" name="channelDesc" id="channelDesc" 
                       placeholder="Masukan deskripsi channel" >
                
                <button type="submit" name="create" class="create_btn">Ubah</button>
            </form>
        </div>
    </div>
</body>
</html>