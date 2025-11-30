<?php
session_start();
require_once 'koneksiDB.php';

// Ensure user is logged in
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
$sqlChannel = "SELECT c.*, u.username 
               FROM Channel c 
               JOIN [User] u ON c.userId = u.userId 
               WHERE c.chnlId = ?";
$params = array($chnlId);
$stmt = sqlsrv_query($conn, $sqlChannel, $params);
$channel = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$channel) {
    die("Channel not found");
}

// Get subscriber count
$sqlSubs = "SELECT COUNT(*) AS sub_count FROM Subscribe WHERE chnlId = ?";
$stmtSubs = sqlsrv_query($conn, $sqlSubs, array($chnlId));
$subData = sqlsrv_fetch_array($stmtSubs, SQLSRV_FETCH_ASSOC);
$subCount = $subData['sub_count'] ?: 0;

// Get video count
$sqlVideos = "SELECT COUNT(*) AS video_count FROM Video WHERE chnlId = ?";
$stmtVideos = sqlsrv_query($conn, $sqlVideos, array($chnlId));
$videoData = sqlsrv_fetch_array($stmtVideos, SQLSRV_FETCH_ASSOC);
$videoCount = $videoData['video_count'] ?: 0;

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
    <title><?= $channel['nama'] ?> - Profile</title>
    <link rel="stylesheet" href="../Styles/profile_styles.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.12.1/css/all.min.css" />
    <style>
        /* Add styles from home.php for sidebar consistency */
        .channel_pfp {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            margin-right: 15px;
            object-fit: cover;
        }
        
        /* Banner fallback */
        .profile_banner {
            background-color: #e63946;
            height: 200px;
            border-radius: 10px;
        }
        
        /* Profile stats */
        .profile_stats {
            display: flex;
            gap: 20px;
            margin-top: 10px;
            font-size: 16px;
            color: #555;
        }
        
        /* Action buttons */
        .profile_actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
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
        }
        
        .custom_btn:hover {
            background-color: #ac3333;
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
          
          <?php foreach ($userChannels as $ch): ?>
          <li>
            <a href="profile.php?chnlId=<?= $ch['chnlId'] ?>">
              <?php if ($ch['pfp']): ?>
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
          <?php if ($channel['banner']): ?>
            <img src="<?= $channel['banner'] ?>" alt="Banner Channel" style="width:100%; height:200px; object-fit:cover; border-radius:10px;" />
          <?php else: ?>
            <div style="background:linear-gradient(45deg, #e63946, #a8dadc); width:100%; height:200px; border-radius:10px;"></div>
          <?php endif; ?>
        </div>

        <!-- Info Profil -->
        <div class="profile_info">
          <div class="profile_image">
            <?php if ($channel['pfp']): ?>
              <img src="<?= $channel['pfp'] ?>" alt="Foto Profil" />
            <?php else: ?>
              <div style="width:120px; height:120px; border-radius:50%; background:#ddd; display:flex; align-items:center; justify-content:center;">
                <i class="fas fa-user" style="font-size:50px; color:#777;"></i>
              </div>
            <?php endif; ?>
          </div>
          <div class="profile_details">
            <h2><?= $channel['nama'] ?></h2>
            <p class="username">@<?= $channel['username'] ?></p>
            <p>
              <?= number_format($subCount) ?> Subscribers&nbsp;•&nbsp;
              <?= number_format($videoCount) ?> Videos
          </p>
          </div>
        </div>

        <!-- Deskripsi -->
        <div class="profile_description">
          <p>
            <?= $channel['desc'] ? nl2br($channel['desc']) : 'No description available' ?>
          </p>
        </div>

        <!-- Tombol Aksi -->
        <div class="profile_actions">
          <a href="editChannel.php?chnlId=<?= $chnlId ?>" class="custom_btn">Customize Channel</a>
          <a href="channelContent.php?chnlId=<?= $chnlId ?>" class="custom_btn">Customize Videos</a>
          <a href="dashboard.php?chnlId=<?= $chnlId ?>" class="custom_btn">Lihat Dashboard</a>
          <?php if ($baId !== null): ?>
            <a href="invite.php?chnlId=<?= $chnlId ?>" class="custom_btn">Invite User</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </body>
</html>