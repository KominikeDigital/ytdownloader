<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>YTDownloader — YouTube Kanal Video İndirici</title>
<meta name="description" content="YouTube kanal linkini yapıştır, tüm videoları sunucuya indir.">
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%23ff0000'/><path d='M12 10l12 6-12 6V10z' fill='white'/></svg>">
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
  <a href="/" class="navbar-brand">
    <span class="yt-icon">▶</span>
    YT<span>Downloader</span>
  </a>
  <div style="display:flex;align-items:center;gap:16px;">
    <span class="nav-badge">cPanel Ready</span>
    <a href="setup.php" class="btn btn-ghost btn-sm">⚙️ Kurulum</a>
  </div>
</nav>

<!-- Hero -->
<div class="hero">
  <div class="container">
    <h1>YouTube Kanal<br>Video İndirici</h1>
    <p>Kanal linkini yapıştır, videoları seç (veya tümünü al), sunucuya indir.</p>

    <!-- Setup Warning -->
    <div id="setup-warning" style="max-width:720px;margin:0 auto 16px;"></div>

    <!-- Search Bar -->
    <div class="search-wrapper">
      <div class="search-bar">
        <div class="search-icon">▶</div>
        <input type="text" id="channel-url" class="search-input"
               placeholder="https://www.youtube.com/@kanal veya /channel/UC..."
               autocomplete="off" spellcheck="false">
        <button id="search-btn" class="search-btn">
          <span id="search-btn-text">🔍 Listele</span>
        </button>
      </div>
      <div class="search-hint">
        Desteklenen formatlar: &nbsp;
        <code>@kullanici</code> &nbsp;
        <code>/channel/UCxxxx</code> &nbsp;
        <code>/c/kanal</code> &nbsp;
        <code>/user/kanal</code> &nbsp;
        <code>?list=PLxxxx</code> (playlist)
      </div>
    </div>

    <!-- Quality Selector -->
    <div class="quality-bar">
      <span class="quality-label">Kalite:</span>
      <button class="quality-btn active" data-quality="best">🏆 En İyi</button>
      <button class="quality-btn" data-quality="1080p">1080p</button>
      <button class="quality-btn" data-quality="720p">720p</button>
      <button class="quality-btn" data-quality="480p">480p</button>
      <button class="quality-btn" data-quality="360p">360p</button>
      <button class="quality-btn" data-quality="audio">🎵 MP3</button>
    </div>
  </div>
</div>

<div class="container">

  <!-- Error -->
  <div id="error-msg" style="display:none;"></div>

  <!-- Videos Section -->
  <div id="videos-section" style="display:none;">

    <!-- Stats -->
    <div class="stats-bar">
      <div class="stat-item">
        <div class="stat-num" id="video-count-info">0</div>
        <div class="stat-label">TOPLAM VİDEO</div>
      </div>
      <div class="stat-item">
        <div class="stat-num" id="selected-count">0</div>
        <div class="stat-label">SEÇİLİ</div>
      </div>
    </div>

    <!-- Select Bar -->
    <div class="select-bar">
      <div class="select-bar-left">
        <button id="select-all-btn" class="btn btn-ghost btn-sm">☑ Tümünü Seç</button>
        <button id="deselect-all-btn" class="btn btn-ghost btn-sm">☐ Seçimi Kaldır</button>
        <span class="select-info"><strong id="selected-count-2">0</strong> video seçildi</span>
      </div>
      <div style="display:flex;gap:8px;">
        <button id="download-selected-btn" class="btn btn-primary">⬇ Seçilenleri İndir</button>
        <button id="download-all-btn" class="btn btn-success">⬇ Tümünü İndir</button>
      </div>
    </div>

    <!-- Video Grid -->
    <div id="video-grid" class="video-grid"></div>
  </div>

  <!-- Download Queue -->
  <div id="queue-section" class="queue-section" style="display:none;">
    <h2>⬇️ İndirme Kuyruğu</h2>
    <div id="queue-list"></div>
  </div>

</div>

<!-- Footer -->
<footer>
  <p>YTDownloader · PHP + yt-dlp · cPanel uyumlu · Yalnızca yasal amaçlarla kullanın</p>
</footer>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loading-overlay">
  <div class="spinner" style="width:48px;height:48px;border-width:5px;"></div>
  <div class="loading-text">Yükleniyor...</div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toast-container"></div>

<script src="assets/js/app.js"></script>
<script>
// Sync selected-count display
setInterval(() => {
  const c2 = document.getElementById('selected-count-2');
  const c1 = document.getElementById('selected-count');
  if (c2 && c1) c2.textContent = c1.textContent;
}, 300);
</script>

</body>
</html>
