<?php
// API: kanal video listesi
require_once dirname(dirname(__FILE__)) . '/config.php';
require_once dirname(__FILE__) . '/YtDlp.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$url = trim($_POST['url'] ?? $_GET['url'] ?? '');

if (!$url) {
    echo json_encode(['success' => false, 'error' => 'URL boş']);
    exit;
}

if (!preg_match('/youtube\.com|youtu\.be/i', $url)) {
    echo json_encode(['success' => false, 'error' => 'Geçerli bir YouTube URL\'si girin']);
    exit;
}

$ytdlp = new YtDlp();

if (!YtDlp::execAvailable()) {
    echo json_encode(['success' => false, 'error' => 'exec() devre dışı. setup.php > Sistem Kontrolü bölümüne bakın.']);
    exit;
}

if (!$ytdlp->isInstalled()) {
    $install = $ytdlp->install();
    if (!$install['success']) {
        echo json_encode($install);
        exit;
    }
}

echo json_encode($ytdlp->fetchChannelVideos($url));
