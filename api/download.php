<?php
// API: Video indir
require_once dirname(dirname(__FILE__)) . '/config.php';
require_once dirname(__FILE__) . '/YtDlp.php';

header('Content-Type: application/json; charset=utf-8');

$videoUrl = trim($_POST['video_url'] ?? '');
$quality  = trim($_POST['quality'] ?? DEFAULT_QUALITY);
$jobId    = trim($_POST['job_id'] ?? '');

if (!$videoUrl) {
    echo json_encode(['success' => false, 'error' => 'video_url gerekli']);
    exit;
}

if (!$jobId) {
    $jobId = 'job_' . substr(md5($videoUrl . microtime()), 0, 12);
}

// job_id güvenlik filtresi
$jobId = preg_replace('/[^a-zA-Z0-9_-]/', '', $jobId);

$ytdlp = new YtDlp();

if (!$ytdlp->isInstalled()) {
    $r = $ytdlp->install();
    if (!$r['success']) {
        echo json_encode($r);
        exit;
    }
}

echo json_encode($ytdlp->downloadVideo($videoUrl, $jobId, $quality));
