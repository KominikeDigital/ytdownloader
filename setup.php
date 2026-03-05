<?php
require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/api/YtDlp.php';

$ytdlp = new YtDlp();
$msg   = '';
$type  = '';

$action = $_POST['action'] ?? '';
if ($action === 'install') {
    $r    = $ytdlp->install();
    $msg  = $r['success'] ? 'yt-dlp başarıyla kuruldu!' : $r['error'];
    $type = $r['success'] ? 'success' : 'error';
} elseif ($action === 'update') {
    $out  = $ytdlp->update();
    $msg  = $out ?: 'Güncelleme tamamlandı';
    $type = 'info';
} elseif ($action === 'clean') {
    $count = 0;
    $dirs  = glob(DOWNLOAD_DIR . '*/');
    if ($dirs) {
        foreach ($dirs as $dir) {
            $files = glob($dir . '*');
            if ($files) array_map('unlink', $files);
            @rmdir($dir);
            $count++;
        }
    }
    $logs = glob(TMP_DIR . '*.log');
    if ($logs) array_map('unlink', $logs);
    $pids = glob(TMP_DIR . '*.pid');
    if ($pids) array_map('unlink', $pids);
    $msg  = "$count indirme klasörü temizlendi.";
    $type = 'success';
}

// Disk kullanımı
function dirSize($dir) {
    $size = 0;
    if (!is_dir($dir)) return 0;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        $size += $file->getSize();
    }
    return $size;
}
function fmtB($b) {
    if ($b >= 1073741824) return round($b/1073741824, 2) . ' GB';
    if ($b >= 1048576)    return round($b/1048576, 2) . ' MB';
    if ($b >= 1024)       return round($b/1024, 2) . ' KB';
    return $b . ' B';
}

$downloadSize = fmtB(dirSize(DOWNLOAD_DIR));
$dlFiles      = glob(DOWNLOAD_DIR . '*/*');
$fileCount    = $dlFiles ? count($dlFiles) : 0;

$checks = [
    ['exec() Fonksiyonu',       YtDlp::execAvailable(),
        'exec() aktif ✓',
        'HATA: exec() devre dışı → cPanel > Select PHP Version > Disable Functions > exec\'i kaldırın'],
    ['shell_exec() Fonksiyonu', YtDlp::shellExecAvailable(),
        'shell_exec() aktif ✓',
        'UYARI: shell_exec() devre dışı — proc_open() varsa indirme yine çalışır'],
    ['proc_open() Fonksiyonu',  YtDlp::procOpenAvailable(),
        'proc_open() aktif ✓ (indirme için kullanılıyor)',
        'UYARI: proc_open() devre dışı → shell_exec veya proc_open\'dan birini açın'],
    ['yt-dlp Binary',           $ytdlp->isInstalled(),
        'yt-dlp kurulu ✓ (v' . $ytdlp->version() . ')',
        'yt-dlp bulunamadı → Aşağıdan kurun'],
    ['downloads/ Yazılabilir',  is_dir(DOWNLOAD_DIR) && is_writable(DOWNLOAD_DIR),
        'downloads/ yazılabilir ✓',
        'ERİŞİM HATASI: chmod 755 downloads/ yapın (cPanel > File Manager)'],
    ['tmp/ Yazılabilir',        is_dir(TMP_DIR) && is_writable(TMP_DIR),
        'tmp/ yazılabilir ✓',
        'ERİŞİM HATASI: chmod 755 tmp/ yapın'],
    ['PHP Sürümü >= 7.4',       version_compare(PHP_VERSION, '7.4', '>='),
        'PHP ' . PHP_VERSION . ' ✓',
        'PHP ' . PHP_VERSION . ' — 7.4+ gerekli'],
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>YTDownloader – Kurulum & Durum</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
.check-row { display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid var(--border); gap:12px; }
.check-row:last-child { border-bottom:none; }
.check-label { font-size:14px;font-weight:600; }
.check-note  { font-size:12px;color:var(--text-muted);margin-top:2px; }
.check-fail  .check-note { color:#fca5a5; }
.card { background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:24px;margin-bottom:24px; }
</style>
</head>
<body>
<nav class="navbar">
  <a href="index.php" class="navbar-brand"><span class="yt-icon">▶</span>YT<span>Downloader</span></a>
  <a href="index.php" class="btn btn-ghost btn-sm">← Ana Sayfa</a>
</nav>

<div class="container" style="max-width:800px;padding-top:40px;padding-bottom:60px;">
  <h1 style="font-size:1.8rem;font-weight:800;margin-bottom:8px;">⚙️ Kurulum &amp; Durum</h1>
  <p style="color:var(--text-muted);margin-bottom:32px;">Sunucu uyumluluğunu kontrol edin ve yt-dlp'yi yönetin.</p>

  <?php if ($msg): ?>
  <div class="alert alert-<?= $type ?>" style="margin-bottom:24px;"><pre style="margin:0;white-space:pre-wrap;"><?= htmlspecialchars($msg) ?></pre></div>
  <?php endif; ?>

  <!-- Sistem Kontrolü -->
  <div class="card">
    <h2 style="font-size:1.1rem;margin-bottom:4px;">🔍 Sistem Kontrolü</h2>
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:20px;">Tüm satırlar ✅ olmalı uygulamanın çalışması için.</p>
    <?php foreach ($checks as $ch): list($label, $ok, $okMsg, $failMsg) = $ch; ?>
    <div class="check-row <?= $ok ? '' : 'check-fail' ?>">
      <div>
        <div class="check-label"><?= htmlspecialchars($label) ?></div>
        <div class="check-note"><?= htmlspecialchars($ok ? $okMsg : $failMsg) ?></div>
      </div>
      <span style="font-size:22px;flex-shrink:0;"><?= $ok ? '✅' : '❌' ?></span>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- yt-dlp Yönetimi -->
  <div class="card">
    <h2 style="font-size:1.1rem;margin-bottom:16px;">🔧 yt-dlp Yönetimi</h2>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
      <form method="POST">
        <input type="hidden" name="action" value="install">
        <button type="submit" class="btn btn-primary">⬇️ yt-dlp İndir / Yeniden Kur</button>
      </form>
      <?php if ($ytdlp->isInstalled()): ?>
      <form method="POST">
        <input type="hidden" name="action" value="update">
        <button type="submit" class="btn btn-ghost">🔄 Güncelle</button>
      </form>
      <?php endif; ?>
    </div>
    <p style="font-size:12px;color:var(--text-muted);">Binary: <code style="background:var(--bg);padding:2px 6px;border-radius:4px;"><?= htmlspecialchars(YTDLP_BIN) ?></code></p>
  </div>

  <!-- Depolama -->
  <div class="card">
    <h2 style="font-size:1.1rem;margin-bottom:16px;">💾 Depolama</h2>
    <div style="display:flex;gap:32px;margin-bottom:20px;flex-wrap:wrap;">
      <div><div style="font-size:1.5rem;font-weight:800;color:var(--accent);"><?= $downloadSize ?></div><div style="font-size:12px;color:var(--text-muted);">Toplam İndirme</div></div>
      <div><div style="font-size:1.5rem;font-weight:800;color:var(--accent);"><?= $fileCount ?></div><div style="font-size:12px;color:var(--text-muted);">Video Dosyası</div></div>
    </div>
    <form method="POST" onsubmit="return confirm('Tüm indirilen dosyalar silinecek!')">
      <input type="hidden" name="action" value="clean">
      <button type="submit" class="btn btn-danger btn-sm">🗑 Tüm İndirmeleri Temizle</button>
    </form>
  </div>

  <!-- PHP Bilgisi -->
  <div class="card">
    <h2 style="font-size:1.1rem;margin-bottom:16px;">ℹ️ PHP &amp; Sunucu</h2>
    <div style="font-size:13px;display:flex;flex-direction:column;gap:8px;">
      <div><strong>PHP:</strong> <?= PHP_VERSION ?></div>
      <div><strong>OS:</strong> <?= PHP_OS ?></div>
      <div><strong>APP_ROOT:</strong> <span style="color:var(--text-muted);font-size:12px;"><?= htmlspecialchars(APP_ROOT) ?></span></div>
      <div><strong>Disable Functions:</strong> <span style="color:var(--text-muted);font-size:12px;"><?= ini_get('disable_functions') ?: 'Yok (iyi!)' ?></span></div>
      <div><strong>Max Execution Time:</strong> <?= ini_get('max_execution_time') ?>s</div>
      <div><strong>Memory Limit:</strong> <?= ini_get('memory_limit') ?></div>
    </div>
  </div>

  <!-- Hızlı Kurulum Rehberi -->
  <div class="card">
    <h2 style="font-size:1.1rem;margin-bottom:16px;">📋 Kurulum Rehberi</h2>
    <ol style="font-size:14px;color:var(--text-muted);line-height:2;padding-left:20px;">
      <li>exec() ❌ ise → <strong>cPanel > Select PHP Version > Disable Functions</strong> → <code>exec</code> ve <code>shell_exec</code>'i listeden kaldırın</li>
      <li>downloads/ veya tmp/ ❌ ise → <strong>cPanel > File Manager</strong> → klasörü seç → Permissions → <strong>755</strong></li>
      <li>yt-dlp ❌ ise → bu sayfada <strong>"yt-dlp İndir"</strong> butonuna tıklayın</li>
      <li>Hepsi ✅ olunca → <a href="index.php" style="color:var(--accent);">Ana Sayfaya Dön</a></li>
    </ol>
  </div>
</div>

<footer><p>YTDownloader Setup · PHP <?= PHP_VERSION ?></p></footer>
</body>
</html>
