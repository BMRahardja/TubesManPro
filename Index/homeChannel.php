<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Home</title>
    <link rel="stylesheet" href="../Styles/homeChannel_styles.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.12.1/css/all.min.css" />
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
            <a href="#"><i class="fas fa-home"></i>Home</a>
          </li>
          <li>
            <a href="../Index/channelContent.html"><i class="fas fa-qrcode"></i>Channel 1</a>
          </li>
          <li>
            <a href="../Index/addChannel.html"><i class="fas fa-users"></i>Add Channel</a>
          </li>
        </ol>
      </div>

      <!-- Konten Kanan -->
      <div class="right_side">
        <!-- Search dan Profil -->
        <div class="top_bar">
          <input type="text" placeholder="Search..." class="search_input" />
          <a href="../Index/profile.html">
            <div class="profile_hover_container">
              <i class="fas fa-user-circle account_icon"></i>

              <!-- Kotak logout yang muncul saat hover -->
              <div class="logout_dropdown">
                <a href="../Index/login.html" class="logout_btn">Logout</a>
              </div>
            </div>
          </a>
        </div>

        <div class="video_grid">
          <!-- Contoh video rekomendasi -->
          <a href="videoDetail.html" class="video_link">
            <div class="video_card">
              <video controls width="100%">
                <source src="../Videos/video_1.mp4" type="video/mp4" />
              </video>
              <h3>Judul Video 1</h3>
            </div>
          </a>
          <div class="video_card">
            <video controls width="100%">
              <source src="../Videos/video_2.mp4" type="video/mp4" />
            </video>
            <h3>Judul Video 2</h3>
          </div>
          <div class="video_card">
            <video controls width="100%">
              <source src="../Videos/video_3.mp4" type="video/mp4" />
            </video>
            <h3>Judul Video 3</h3>
          </div>
          <div class="video_card">
            <video controls width="100%">
              <source src="../Videos/video_4.mp4" type="video/mp4" />
            </video>
            <h3>Judul Video 4</h3>
          </div>
          <!-- Tambah lebih banyak jika perlu -->
        </div>
      </div>
    </div>
  </body>
</html>
