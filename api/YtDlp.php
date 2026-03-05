<?php
require_once dirname(dirname(__FILE__)) . '/config.php';

class YtDlp {

    private $bin;

    public function __construct() {
        $this->bin = YTDLP_BIN;
    }

    /* ── Kullanılabilirlik kontrolleri ───────────────────────── */

    public static function execAvailable() {
        if (!function_exists('exec')) return false;
        $d = array_map('trim', explode(',', ini_get('disable_functions')));
        return !in_array('exec', $d);
    }

    public static function shellExecAvailable() {
        if (!function_exists('shell_exec')) return false;
        $d = array_map('trim', explode(',', ini_get('disable_functions')));
        return !in_array('shell_exec', $d);
    }

    public static function procOpenAvailable() {
        if (!function_exists('proc_open')) return false;
        $d = array_map('trim', explode(',', ini_get('disable_functions')));
        return !in_array('proc_open', $d);
    }

    public function isInstalled() {
        return file_exists($this->bin) && is_executable($this->bin);
    }

    /** TMPDIR ön eki — noexec /tmp sorununu çözer (cPanel shared hosting) */
    private function tmpEnv() {
        return 'TMPDIR=' . escapeshellarg(rtrim(TMP_DIR, '/')) . ' ';
    }

    /* ── yt-dlp kurulum ─────────────────────────────────────── */

    public function install() {
        if (!self::execAvailable()) {
            return ['success' => false, 'error' => 'exec() devre dışı. cPanel > PHP Selector > Disable Functions > exec\'i kaldırın.'];
        }

        $os = strtolower(PHP_OS);

        // Windows
        if (strpos($os, 'win') !== false) {
            $url = 'https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp.exe';
        } else {
            // Linux standalone binary — kendi Python runtime'ını içerir, sistem Python'a bağımlı değil
            // Mimari tespiti
            $arch = '';
            exec('uname -m 2>/dev/null', $archOut);
            $arch = trim($archOut[0] ?? '');

            if ($arch === 'aarch64' || $arch === 'arm64') {
                $url = 'https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp_linux_aarch64';
            } elseif (strpos($arch, 'armv7') !== false) {
                $url = 'https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp_linux_armv7l';
            } else {
                // x86_64 (en yaygın cPanel sunucusu)
                $url = 'https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp_linux';
            }
        }

        $binDir = dirname($this->bin);
        if (!is_dir($binDir)) mkdir($binDir, 0755, true);

        // Önce eski binary'yi sil
        if (file_exists($this->bin)) @unlink($this->bin);

        // 1. wget ile indir
        $out = [];
        exec("wget -q --timeout=120 --no-check-certificate \"$url\" -O \"{$this->bin}\" 2>&1", $out, $ret);
        if ($ret === 0 && file_exists($this->bin) && filesize($this->bin) > 500000) {
            chmod($this->bin, 0755);
            $verOut = [];
            exec($this->tmpEnv() . escapeshellarg($this->bin) . ' --version 2>&1', $verOut);
            if (!empty($verOut[0]) && strpos($verOut[0], 'Error') === false) {
                return ['success' => true, 'version' => trim($verOut[0])];
            }
        }

        // 2. curl ile dene
        @unlink($this->bin);
        exec("curl -sL --max-time 120 --insecure \"$url\" -o \"{$this->bin}\" 2>&1", $out, $ret);
        if ($ret === 0 && file_exists($this->bin) && filesize($this->bin) > 500000) {
            chmod($this->bin, 0755);
            $verOut = [];
            exec($this->tmpEnv() . escapeshellarg($this->bin) . ' --version 2>&1', $verOut);
            if (!empty($verOut[0]) && strpos($verOut[0], 'Error') === false) {
                return ['success' => true, 'version' => trim($verOut[0])];
            }
        }

        // 3. file_get_contents dene
        @unlink($this->bin);
        $ctx = stream_context_create([
            'http' => ['timeout' => 120, 'follow_location' => true, 'user_agent' => 'curl/7.68.0'],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data && strlen($data) > 500000) {
            file_put_contents($this->bin, $data);
            chmod($this->bin, 0755);
            $verOut = [];
            exec($this->tmpEnv() . escapeshellarg($this->bin) . ' --version 2>&1', $verOut);
            if (!empty($verOut[0]) && strpos($verOut[0], 'Error') === false) {
                return ['success' => true, 'version' => trim($verOut[0])];
            }
        }

        $fsize = file_exists($this->bin) ? filesize($this->bin) : 0;
        return [
            'success' => false,
            'error'   => "yt-dlp_linux indirilemedi veya çalıştırılamadı. Dosya boyutu: {$fsize} bytes. "
                       . "URL: $url. Çıktı: " . implode(' | ', array_slice($out, 0, 3)),
        ];
    }

    /* ── Kanal video listesi ─────────────────────────────────── */

    public function fetchChannelVideos($url) {
        if (!$this->isInstalled()) {
            return ['success' => false, 'error' => 'yt-dlp kurulu değil. setup.php sayfasından kurun.'];
        }
        if (!self::execAvailable()) {
            return ['success' => false, 'error' => 'exec() devre dışı. cPanel > PHP Selector > Disable Functions > exec kaldırın.'];
        }

        // Shorts tab URL'yi doğru formata çevir
        // @kanal/shorts → @kanal (yt-dlp tüm shorts'u listeler)
        $fetchUrl = $url;

        $tmpEnv = $this->tmpEnv();
        $cmd = $tmpEnv . escapeshellarg($this->bin)
             . ' --flat-playlist'
             . ' --dump-json'
             . ' --no-warnings'
             . ' --no-color'
             . ' --ignore-errors'
             . ' ' . escapeshellarg($fetchUrl)
             . ' 2>&1';

        $output = [];
        $ret    = 0;
        exec($cmd, $output, $ret);

        // JSON satırlarını ve hata satırlarını ayır
        $videos     = [];
        $errorLines = [];

        foreach ($output as $line) {
            $line = trim($line);
            if ($line === '') continue;

            if ($line[0] === '{') {
                $info = json_decode($line, true);
                if ($info && isset($info['id'])) {
                    // Thumbnail seç
                    $thumb = "https://img.youtube.com/vi/{$info['id']}/hqdefault.jpg";
                    if (!empty($info['thumbnails']) && is_array($info['thumbnails'])) {
                        $last  = end($info['thumbnails']);
                        $thumb = $last['url'] ?? $thumb;
                    }

                    // Süre
                    $duration = '?';
                    if (!empty($info['duration']) && $info['duration'] > 0) {
                        $duration = gmdate($info['duration'] >= 3600 ? 'H:i:s' : 'i:s', (int)$info['duration']);
                    }

                    $videos[] = [
                        'id'          => $info['id'],
                        'title'       => $info['title'] ?? 'Başlıksız',
                        'url'         => 'https://www.youtube.com/watch?v=' . $info['id'],
                        'thumbnail'   => $thumb,
                        'duration'    => $duration,
                        'view_count'  => (int)($info['view_count'] ?? 0),
                        'uploader'    => $info['uploader'] ?? ($info['channel'] ?? ''),
                        'upload_date' => $info['upload_date'] ?? '',
                    ];
                }
            } else {
                // Hata / uyarı satırı
                if (stripos($line, 'error') !== false || stripos($line, 'WARNING') !== false) {
                    $errorLines[] = $line;
                }
            }
        }

        if (count($videos) === 0) {
            $detail = count($errorLines) > 0
                ? implode(' | ', array_slice($errorLines, 0, 3))
                : 'yt-dlp çıktı üretmedi (ret=' . $ret . ')';
            return ['success' => false, 'error' => 'Video listesi boş. Detay: ' . $detail];
        }

        return ['success' => true, 'videos' => $videos, 'count' => count($videos)];
    }

    /* ── Video indirme ───────────────────────────────────────── */

    private function qualityFlag($quality) {
        switch ($quality) {
            case '1080p': return '-f "bestvideo[height<=1080]+bestaudio/best[height<=1080]"';
            case '720p':  return '-f "bestvideo[height<=720]+bestaudio/best[height<=720]"';
            case '480p':  return '-f "bestvideo[height<=480]+bestaudio/best[height<=480]"';
            case '360p':  return '-f "bestvideo[height<=360]+bestaudio/best[height<=360]"';
            case 'audio': return '-f bestaudio --extract-audio --audio-format mp3';
            default:      return '-f bestvideo+bestaudio/best';
        }
    }

    public function downloadVideo($videoUrl, $jobId, $quality = 'best') {
        if (!$this->isInstalled()) {
            return ['success' => false, 'error' => 'yt-dlp kurulu değil.'];
        }

        $outDir = DOWNLOAD_DIR . $jobId . '/';
        if (!is_dir($outDir)) mkdir($outDir, 0755, true);

        $logFile    = TMP_DIR . $jobId . '.log';
        $pidFile    = TMP_DIR . $jobId . '.pid';
        $workerFile = TMP_DIR . $jobId . '_worker.php';

        $formatFlag = $this->qualityFlag($quality);
        $outTpl     = $outDir . '%(title)s.%(ext)s';
        $tmpDir     = rtrim(TMP_DIR, '/');
        $binPath    = $this->bin;

        // ── Yöntem 1: proc_open ──────────────────────────────────
        if (self::procOpenAvailable()) {
            $cmd = 'TMPDIR=' . escapeshellarg($tmpDir) . ' '
                 . escapeshellarg($binPath)
                 . " $formatFlag"
                 . ' --merge-output-format mp4 --no-warnings --no-color --newline'
                 . ' -o ' . escapeshellarg($outTpl)
                 . ' ' . escapeshellarg($videoUrl);

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['file', $logFile, 'w'],
                2 => ['file', $logFile, 'a'],
            ];
            $pipes   = [];
            $process = proc_open($cmd, $descriptors, $pipes);
            if (is_resource($process)) {
                $status = proc_get_status($process);
                fclose($pipes[0]);
                proc_close($process);
                $pid = $status['pid'] ?? '';
                file_put_contents($pidFile, $pid);
                return ['success' => true, 'job_id' => $jobId, 'pid' => $pid, 'method' => 'proc_open'];
            }
        }

        // ── Yöntem 2: PHP Worker Script + exec() ─────────────────
        // exec() ile PHP'yi arka planda çalıştır — shell_exec gerekmez
        if (self::execAvailable()) {
            // Worker PHP dosyası oluştur
            $escapedLog    = addslashes($logFile);
            $escapedTmp    = addslashes($tmpDir);
            $escapedBin    = addslashes($binPath);
            $escapedOut    = addslashes($outTpl);
            $escapedUrl    = addslashes($videoUrl);
            $escapedFmt    = addslashes($formatFlag);

            $workerCode = '<?php' . "\n"
                . 'set_time_limit(0);' . "\n"
                . 'ignore_user_abort(true);' . "\n"
                . '$cmd = \'TMPDIR=\' . escapeshellarg(\'' . $escapedTmp . '\') . \' \'' . "\n"
                . '     . escapeshellarg(\'' . $escapedBin . '\')' . "\n"
                . '     . \' ' . $escapedFmt . '\'' . "\n"
                . '     . \' --merge-output-format mp4 --no-warnings --no-color --newline\'' . "\n"
                . '     . \' -o \' . escapeshellarg(\'' . $escapedOut . '\')' . "\n"
                . '     . \' \' . escapeshellarg(\'' . $escapedUrl . '\');' . "\n"
                . 'exec($cmd . \' > \' . escapeshellarg(\'' . $escapedLog . '\') . \' 2>&1\');' . "\n";

            file_put_contents($workerFile, $workerCode);
            chmod($workerFile, 0644);

            // PHP binary'yi bul
            $phpBin = PHP_BINARY ?: 'php';
            $bgCmd  = 'nohup ' . escapeshellarg($phpBin) . ' ' . escapeshellarg($workerFile)
                    . ' > /dev/null 2>&1 &';
            exec($bgCmd, $bgOut, $bgRet);

            // PID bul (lsof/ps ile)
            sleep(1);
            $pid = '';
            $psOut = [];
            exec('pgrep -f ' . escapeshellarg(basename($workerFile)) . ' 2>/dev/null', $psOut);
            if (!empty($psOut[0])) $pid = trim($psOut[0]);

            file_put_contents($pidFile, $pid);
            file_put_contents($logFile, ''); // Boş log oluştur (pending göstergesi)
            return ['success' => true, 'job_id' => $jobId, 'pid' => $pid, 'method' => 'php_worker'];
        }

        // ── Yöntem 3: shell_exec ─────────────────────────────────
        if (self::shellExecAvailable()) {
            $cmd = 'TMPDIR=' . escapeshellarg($tmpDir) . ' '
                 . escapeshellarg($binPath)
                 . " $formatFlag"
                 . ' --merge-output-format mp4 --no-warnings --no-color --newline'
                 . ' -o ' . escapeshellarg($outTpl)
                 . ' ' . escapeshellarg($videoUrl)
                 . ' > ' . escapeshellarg($logFile) . ' 2>&1 & echo $!';
            $pid = trim(shell_exec($cmd));
            file_put_contents($pidFile, $pid);
            return ['success' => true, 'job_id' => $jobId, 'pid' => $pid, 'method' => 'shell_exec'];
        }

        return ['success' => false, 'error' => 'Arka plan indirme başlatılamadı. exec(), shell_exec() veya proc_open() fonksiyonlarından en az birinin aktif olması gerekiyor.'];
    }

    /* ── Durum takibi ────────────────────────────────────────── */

    public function getStatus($jobId) {
        $logFile = TMP_DIR . $jobId . '.log';
        $pidFile = TMP_DIR . $jobId . '.pid';
        $outDir  = DOWNLOAD_DIR . $jobId . '/';

        if (!file_exists($logFile)) {
            return ['status' => 'not_found', 'progress' => 0, 'files' => []];
        }

        $log = file_get_contents($logFile);
        $pid = file_exists($pidFile) ? trim(file_get_contents($pidFile)) : '';

        // Çalışıyor mu?
        $running = false;
        if ($pid && self::execAvailable()) {
            exec("kill -0 $pid 2>/dev/null", $k, $kRet);
            $running = ($kRet === 0);
        }

        // Progress
        $progress = 0;
        $speed    = '';
        $eta      = '';

        if (preg_match_all('/(\d+\.?\d*)%/', $log, $m)) {
            $progress = (float)end($m[1]);
        }
        if (preg_match('/at\s+([\d.]+[KMGTk]i?B\/s)/', $log, $m))  $speed = $m[1];
        if (preg_match('/ETA\s+([\d:]+)/', $log, $m)) $eta = $m[1];

        $done  = !$running && (
            strpos($log, '[download] 100%') !== false ||
            strpos($log, 'has already been downloaded') !== false ||
            strpos($log, 'Deleting original file') !== false
        );
        $hasContent = strlen(trim($log)) > 5;
        $error = !$running && !$done && $hasContent && $progress < 99;

        // İndirilen dosyalar
        $files = [];
        if (is_dir($outDir)) {
            foreach ((glob($outDir . '*') ?: []) as $f) {
                if (is_file($f) && filesize($f) > 1000) {
                    $files[] = [
                        'name' => basename($f),
                        'size' => filesize($f),
                        'url'  => '/downloads/' . $jobId . '/' . rawurlencode(basename($f)),
                    ];
                }
            }
        }

        if ($done && empty($files)) {
            $done  = false;
            $error = true;
        }

        $statusStr = 'pending';
        if ($done)         $statusStr = 'done';
        elseif ($running)  $statusStr = 'downloading';
        elseif ($error)    $statusStr = 'error';

        return [
            'status'   => $statusStr,
            'progress' => $progress,
            'speed'    => $speed,
            'eta'      => $eta,
            'files'    => $files,
            'log_tail' => nl2br(htmlspecialchars(substr($log, -2000))),
        ];
    }

    /* ── Yardımcı metodlar ───────────────────────────────────── */

    public function killJob($jobId) {
        $pidFile = TMP_DIR . $jobId . '.pid';
        if (file_exists($pidFile) && self::execAvailable()) {
            $pid = trim(file_get_contents($pidFile));
            if ($pid) {
                exec("kill $pid 2>/dev/null");
                exec("kill -9 $pid 2>/dev/null");
            }
            @unlink($pidFile);
        }
    }

    public function deleteJob($jobId) {
        $this->killJob($jobId);
        $outDir     = DOWNLOAD_DIR . $jobId . '/';
        $logFile    = TMP_DIR . $jobId . '.log';
        $workerFile = TMP_DIR . $jobId . '_worker.php';
        if (is_dir($outDir)) {
            $files = glob($outDir . '*') ?: [];
            array_map('unlink', $files);
            @rmdir($outDir);
        }
        if (file_exists($logFile))    @unlink($logFile);
        if (file_exists($workerFile)) @unlink($workerFile);
    }

    public function update() {
        if (!self::execAvailable()) return 'exec() devre dışı.';
        $out = [];
        exec($this->tmpEnv() . escapeshellarg($this->bin) . ' -U 2>&1', $out);
        return implode("\n", $out);
    }

    public function version() {
        if (!$this->isInstalled() || !self::execAvailable()) return '—';
        $out = [];
        exec($this->tmpEnv() . escapeshellarg($this->bin) . ' --version 2>&1', $out);
        return trim($out[0] ?? '—');
    }
}
