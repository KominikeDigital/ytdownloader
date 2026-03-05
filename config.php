<?php
// ─── YTDownloader Configuration ───────────────────────────────────────────────
define('APP_NAME',    'YTDownloader');
define('APP_VERSION', '1.0.0');

// Uygulama kök dizini (güvenilir yol — tüm include'lar bunu kullanır)
define('APP_ROOT', rtrim(dirname(__FILE__), '/\\'));

// YouTube Data API v3 key (opsiyonel)
define('YOUTUBE_API_KEY', '');

// İndirme kalitesi
define('DEFAULT_QUALITY', 'best');

// İndirilen dosyaların saklanacağı klasör
define('DOWNLOAD_DIR', APP_ROOT . '/downloads/');

// Geçici iş dosyaları
define('TMP_DIR', APP_ROOT . '/tmp/');

// yt-dlp binary konumu
define('YTDLP_BIN', APP_ROOT . '/bin/yt-dlp');

// Zaman dilimi
date_default_timezone_set('Europe/Istanbul');

// PHP hata raporlama (production'da kapalı tut)
error_reporting(0);
@ini_set('display_errors', '0');
@ini_set('max_execution_time', 0);
@ini_set('memory_limit', '256M');
