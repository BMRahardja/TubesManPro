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
// Fetch channel videos with stats including comments
$sqlVideos = "SELECT 
                v.videoId,
                v.judul,
                v.thumbnail,
                v.tglUpld,
                (SELECT COUNT(*) FROM [View] WHERE videoId = v.videoId) AS views,
                (SELECT COUNT(*) FROM Reaksi WHERE videoId = v.videoId AND tipe = 'like') AS likes,
                (SELECT COUNT(*) FROM Reaksi WHERE videoId = v.videoId AND tipe = 'dislike') AS dislikes,
                (SELECT COUNT(*) FROM Komen WHERE videoId = v.videoId) AS comments
              FROM Video v
              WHERE v.chnlId = ? AND v.status = 'up'";
$stmtVideos = sqlsrv_query($conn, $sqlVideos, array($chnlId));
$videos = [];
while ($row = sqlsrv_fetch_array($stmtVideos, SQLSRV_FETCH_ASSOC)) {
    $videos[] = $row;
}

// Fetch analytics data
// Video dengan view terbanyak
$sqlMostViewed = "SELECT TOP 1 v.videoId, v.judul, v.thumbnail,
                    (SELECT COUNT(*) FROM [View] WHERE videoId = v.videoId) AS views
                  FROM Video v
                  WHERE v.chnlId = ?
                  ORDER BY views DESC";
$stmtMostViewed = sqlsrv_query($conn, $sqlMostViewed, array($chnlId));
$mostViewed = sqlsrv_fetch_array($stmtMostViewed, SQLSRV_FETCH_ASSOC);

// Video dengan like terbanyak
$sqlMostLiked = "SELECT TOP 1 v.videoId, v.judul, v.thumbnail,
                    (SELECT COUNT(*) FROM Reaksi WHERE videoId = v.videoId AND tipe = 'like') AS likes
                 FROM Video v
                 WHERE v.chnlId = ?
                 ORDER BY likes DESC";
$stmtMostLiked = sqlsrv_query($conn, $sqlMostLiked, array($chnlId));
$mostLiked = sqlsrv_fetch_array($stmtMostLiked, SQLSRV_FETCH_ASSOC);

// Video dengan komentar terbanyak
$sqlMostCommented = "SELECT TOP 1 v.videoId, v.judul, v.thumbnail,
                        (SELECT COUNT(*) FROM Komen WHERE videoId = v.videoId) AS comments
                     FROM Video v
                     WHERE v.chnlId = ?
                     ORDER BY comments DESC";
$stmtMostCommented = sqlsrv_query($conn, $sqlMostCommented, array($chnlId));
$mostCommented = sqlsrv_fetch_array($stmtMostCommented, SQLSRV_FETCH_ASSOC);

// PERBAIKAN QUERY: Subscriber dengan view terbanyak (jumlah video yang ditonton)
$sqlMostViewingSub = "SELECT TOP 1 u.username, COUNT(vi.videoId) AS total_views
                      FROM [View] vi
                      JOIN Video v ON vi.videoId = v.videoId
                      JOIN Subscribe s ON vi.userId = s.userId AND s.chnlId = ?
                      JOIN [User] u ON vi.userId = u.userId
                      WHERE v.chnlId = ?
                      GROUP BY u.username
                      ORDER BY total_views DESC";
$stmtMostViewingSub = sqlsrv_query($conn, $sqlMostViewingSub, array($chnlId, $chnlId));
$mostViewingSub = sqlsrv_fetch_array($stmtMostViewingSub, SQLSRV_FETCH_ASSOC);

// Subscriber paling lama
$sqlOldestSubscriber = "SELECT TOP 1 u.username, s.tglSub 
                        FROM Subscribe s
                        JOIN [User] u ON s.userId = u.userId
                        WHERE s.chnlId = ?
                        ORDER BY s.tglSub ASC";
$stmtOldestSubscriber = sqlsrv_query($conn, $sqlOldestSubscriber, array($chnlId));
$oldestSubscriber = sqlsrv_fetch_array($stmtOldestSubscriber, SQLSRV_FETCH_ASSOC);

// Fetch subscribers list
$sqlSubscribers = "SELECT u.username, s.tglSub 
                   FROM Subscribe s
                   JOIN [User] u ON s.userId = u.userId
                   WHERE s.chnlId = ?
                   ORDER BY s.tglSub DESC";
$stmtSubscribers = sqlsrv_query($conn, $sqlSubscribers, array($chnlId));
$subscribers = [];
while ($row = sqlsrv_fetch_array($stmtSubscribers, SQLSRV_FETCH_ASSOC)) {
    $subscribers[] = $row;
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
        .channel_pfp {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            margin-right: 15px;
            object-fit: cover;
        }

        /* PERBAIKAN TATA LETAK UTAMA */
        .tab_content {
            display: grid;
            grid-template-columns: 70% 30%;
            gap: 20px;
        }

        /* Video Grid */
        .video_table {
            grid-column: 1;
        }

        /* Subscriber List */
        .subscribers_list {
            grid-column: 2;
            margin-top: 0; /* Hapus margin atas sebelumnya */
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            height: fit-content;
        }
        
        .subscribers_header {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #e63946;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        
        .subscriber_item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .subscriber_name {
            font-weight: 500;
        }
        
        .subscriber_date {
            color: #666;
        }
        
        .no_data {
            text-align: center;
            padding: 20px;
            color: #888;
            font-style: italic;
        }

        /* Analytics Grid */
        .analytics_grid {
            grid-column: 1 / span 2;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .analytics_card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .analytics_title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #e63946;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        
        .analytics_content {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .analytics_thumb {
            width: 120px;
            height: 68px;
            border-radius: 6px;
            object-fit: cover;
        }
        
        .analytics_text {
            flex: 1;
        }
        
        .analytics_video_title {
            font-weight: 500;
            margin-bottom: 5px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .analytics_stat {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }

        /* RESPONSIVE */
        @media (max-width: 1200px) {
            .tab_content {
                grid-template-columns: 1fr;
            }
            .subscribers_list {
                grid-column: 1;
            }
        }

        .video_header,
        .video_row {
        display: grid;
        /* misal: judul thumbnail lebih lebar */
        grid-template-columns: 3fr 1fr 1fr 1fr 1fr;
        /*                    ↑ sekarang 5 kolom */
        align-items: center;
        padding: 10px 15px;
        border-bottom: 1px solid #ccc;
        background-color: #f9f9f9;
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
        <h2 class="channel_title">Analitik Channel</h2>
        <div class="tab_content">
            <div class="video_table">
                <div class="video_header">
                    <div class="video_col">Video</div>
                    <div class="video_col">Views</div>
                    <div class="video_col">Likes</div>
                    <div class="video_col">Dislikes</div>
                    <div class="video_col">Komen</div>
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
                        <div class="video_col"><?= number_format($video['views']) ?></div>
                        <div class="video_col"><?= number_format($video['likes']) ?></div>
                        <div class="video_col"><?= number_format($video['dislikes']) ?></div>
                        <div class="video_col"><?= number_format($video['comments']) ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="video_row" style="grid-column: 1 / -1; text-align: center; padding: 20px;">
                        Tidak ada video yang di post
                    </div>
                <?php endif; ?>
            </div>

            <!-- PERBAIKAN LETAK: Subscriber List sekarang di samping video -->
            <div class="subscribers_list">
                <div class="subscribers_header">Daftar Subscriber</div>
                <?php if (count($subscribers) > 0): ?>
                    <?php foreach ($subscribers as $sub): ?>
                    <div class="subscriber_item">
                        <div class="subscriber_name"><?= $sub['username'] ?></div>
                        <div class="subscriber_date">
                            <?= date('M d, Y', strtotime($sub['tglSub']->format('Y-m-d'))) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no_data">Belum ada subscriber</div>
                <?php endif; ?>
            </div>

            <!-- Bagian Analitik -->
            <div class="analytics_grid">
                <!-- Video paling banyak viewers -->
                <div class="analytics_card">
                    <div class="analytics_title">Video Paling Banyak Dilihat</div>
                    <?php if ($mostViewed): ?>
                    <div class="analytics_content">
                        <img src="<?= $mostViewed['thumbnail'] ?>" 
                             alt="Thumbnail" 
                             class="analytics_thumb"
                             onerror="this.src='https://via.placeholder.com/120x68'">
                        <div class="analytics_text">
                            <div class="analytics_video_title"><?= $mostViewed['judul'] ?></div>
                            <div class="analytics_stat"><?= number_format($mostViewed['views']) ?> views</div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="no_data">Tidak ada data</div>
                    <?php endif; ?>
                </div>

                <!-- Video paling banyak like -->
                <div class="analytics_card">
                    <div class="analytics_title">Video Paling Banyak Disukai</div>
                    <?php if ($mostLiked): ?>
                    <div class="analytics_content">
                        <img src="<?= $mostLiked['thumbnail'] ?>" 
                             alt="Thumbnail" 
                             class="analytics_thumb"
                             onerror="this.src='https://via.placeholder.com/120x68'">
                        <div class="analytics_text">
                            <div class="analytics_video_title"><?= $mostLiked['judul'] ?></div>
                            <div class="analytics_stat"><?= number_format($mostLiked['likes']) ?> likes</div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="no_data">Tidak ada data</div>
                    <?php endif; ?>
                </div>

                <!-- Video paling banyak komen -->
                <div class="analytics_card">
                    <div class="analytics_title">Video Paling Banyak Komentar</div>
                    <?php if ($mostCommented): ?>
                    <div class="analytics_content">
                        <img src="<?= $mostCommented['thumbnail'] ?>" 
                             alt="Thumbnail" 
                             class="analytics_thumb"
                             onerror="this.src='https://via.placeholder.com/120x68'">
                        <div class="analytics_text">
                            <div class="analytics_video_title"><?= $mostCommented['judul'] ?></div>
                            <div class="analytics_stat"><?= number_format($mostCommented['comments']) ?> komentar</div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="no_data">Tidak ada data</div>
                    <?php endif; ?>
                </div>

                <!-- Subscriber paling banyak view video -->
                <div class="analytics_card">
                    <div class="analytics_title">Subscriber Paling Aktif Menonton</div>
                    <?php if ($mostViewingSub): ?>
                    <div class="analytics_content">
                        <div class="analytics_text">
                            <div class="analytics_video_title"><?= $mostViewingSub['username'] ?></div>
                            <!-- PERBAIKAN: Menampilkan jumlah video yang ditonton -->
                            <div class="analytics_stat"><?= number_format($mostViewingSub['total_views']) ?> video</div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="no_data">Tidak ada data</div>
                    <?php endif; ?>
                </div>

                <!-- Subscriber paling lama subscribe -->
                <div class="analytics_card">
                    <div class="analytics_title">Subscriber Paling Setia</div>
                    <?php if ($oldestSubscriber): ?>
                    <div class="analytics_content">
                        <div class="analytics_text">
                            <div class="analytics_video_title"><?= $oldestSubscriber['username'] ?></div>
                            <div class="analytics_stat">
                                <?= date('M d, Y', strtotime($oldestSubscriber['tglSub']->format('Y-m-d'))) ?>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="no_data">Tidak ada data</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
