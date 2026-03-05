<?php
// API: Durum, Kill, Delete, Check, List
require_once dirname(dirname(__FILE__)) . '/config.php';
require_once dirname(__FILE__) . '/YtDlp.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? 'status';
$jobId  = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['job_id'] ?? $_GET['job_id'] ?? '');

$ytdlp = new YtDlp();

switch ($action) {

    case 'status':
        if (!$jobId) { echo json_encode(['error' => 'job_id gerekli']); exit; }
        echo json_encode($ytdlp->getStatus($jobId));
        break;

    case 'kill':
        if (!$jobId) { echo json_encode(['error' => 'job_id gerekli']); exit; }
        $ytdlp->killJob($jobId);
        echo json_encode(['success' => true]);
        break;

    case 'delete':
        if (!$jobId) { echo json_encode(['error' => 'job_id gerekli']); exit; }
        $ytdlp->deleteJob($jobId);
        echo json_encode(['success' => true]);
        break;

    case 'install':
        echo json_encode($ytdlp->install());
        break;

    case 'check':
        echo json_encode([
            'exec_available'       => YtDlp::execAvailable(),
            'shell_exec_available' => YtDlp::shellExecAvailable(),
            'proc_open_available'  => YtDlp::procOpenAvailable(),
            'ytdlp_installed'      => $ytdlp->isInstalled(),
            'ytdlp_version'        => $ytdlp->version(),
            'ytdlp_bin'            => YTDLP_BIN,
            'download_dir'         => DOWNLOAD_DIR,
            'download_writable'    => is_writable(DOWNLOAD_DIR),
            'tmp_dir'              => TMP_DIR,
            'tmp_writable'         => is_writable(TMP_DIR),
            'php_version'          => PHP_VERSION,
            'os'                   => PHP_OS,
            'disable_functions'    => ini_get('disable_functions'),
        ]);
        break;

    case 'list_downloads':
        $downloads = [];
        if (is_dir(DOWNLOAD_DIR)) {
            $dirs = glob(DOWNLOAD_DIR . '*/');
            if ($dirs) {
                foreach ($dirs as $dir) {
                    $jid   = basename(rtrim($dir, '/'));
                    $files = glob($dir . '*');
                    $list  = [];
                    if ($files) {
                        foreach ($files as $f) {
                            if (is_file($f) && filesize($f) > 0) {
                                $sz = filesize($f);
                                $list[] = [
                                    'name'       => basename($f),
                                    'size'       => $sz,
                                    'size_human' => formatBytes($sz),
                                    'url'        => '/downloads/' . $jid . '/' . rawurlencode(basename($f)),
                                    'modified'   => filemtime($f),
                                ];
                            }
                        }
                    }
                    if (!empty($list)) {
                        $downloads[] = ['job_id' => $jid, 'files' => $list];
                    }
                }
            }
        }
        echo json_encode(['downloads' => $downloads]);
        break;

    default:
        echo json_encode(['error' => 'Bilinmeyen action: ' . htmlspecialchars($action)]);
}

function formatBytes($bytes, $precision = 2) {
    $units  = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes  = max($bytes, 0);
    $pow    = $bytes > 0 ? floor(log($bytes) / log(1024)) : 0;
    $pow    = min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}
