// YTDownloader - Main App JS

const App = {
    quality: 'best',
    selectedVideos: new Set(),
    allVideos: [],
    downloadJobs: {}, // jobId -> {title, status, interval}

    init() {
        this.bindEvents();
        this.checkSetup();
        this.loadDownloads();
    },

    bindEvents() {
        // Search
        document.getElementById('search-btn').addEventListener('click', () => this.fetchChannel());
        document.getElementById('channel-url').addEventListener('keypress', e => {
            if (e.key === 'Enter') this.fetchChannel();
        });

        // Quality buttons
        document.querySelectorAll('.quality-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.quality-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                this.quality = btn.dataset.quality;
            });
        });

        // Select all
        document.getElementById('select-all-btn')?.addEventListener('click', () => this.selectAll());
        document.getElementById('deselect-all-btn')?.addEventListener('click', () => this.deselectAll());
        document.getElementById('download-selected-btn')?.addEventListener('click', () => this.downloadSelected());
        document.getElementById('download-all-btn')?.addEventListener('click', () => this.downloadAll());
    },

    async checkSetup() {
        try {
            const res = await fetch('api/status.php?action=check');
            const data = await res.json();
            if (!data.exec_available) {
                document.getElementById('setup-warning').innerHTML = `
          <div class="alert alert-error">
            ⚠️ <strong>exec() devre dışı!</strong> Hosting ayarlarınızda PHP exec() fonksiyonunu etkinleştirmeniz gerekiyor.
            cPanel → PHP Selector → Disable Functions → exec'i kaldırın.
          </div>`;
                document.getElementById('search-btn').disabled = true;
            } else if (!data.ytdlp_installed) {
                document.getElementById('setup-warning').innerHTML = `
          <div class="alert alert-warning">
            ⚙️ <strong>yt-dlp bulunamadı.</strong> İlk aramada otomatik indirilecek (~10 saniye bekleyin).
            <button class="btn btn-sm btn-outline" style="margin-left:12px;" onclick="App.installYtdlp()">Şimdi Kur</button>
          </div>`;
            }
        } catch (e) { }
    },

    async installYtdlp() {
        this.showLoading('yt-dlp kuruluyor...');
        const res = await fetch('api/status.php', { method: 'POST', body: new URLSearchParams({ action: 'install' }) });
        const data = await res.json();
        this.hideLoading();
        if (data.success) {
            this.toast('yt-dlp başarıyla kuruldu!', 'success');
            document.getElementById('setup-warning').innerHTML = '<div class="alert alert-success">✓ yt-dlp kuruldu. Hazır!</div>';
        } else {
            this.toast('Kurulum hatası: ' + data.error, 'error');
        }
    },

    async fetchChannel() {
        const url = document.getElementById('channel-url').value.trim();
        if (!url) { this.toast('Kanal URL\'si girin', 'error'); return; }

        this.showLoading('Kanal videoları yükleniyor...<br><small>Bu birkaç dakika sürebilir</small>');
        document.getElementById('videos-section').style.display = 'none';
        document.getElementById('error-msg').style.display = 'none';
        this.selectedVideos.clear();

        try {
            const form = new FormData();
            form.append('url', url);
            const res = await fetch('api/fetch.php', { method: 'POST', body: form });
            const data = await res.json();

            if (!data.success) {
                this.showError(data.error || 'Bilinmeyen hata');
                return;
            }

            this.allVideos = data.videos;
            this.renderVideoGrid(data.videos);
            document.getElementById('videos-section').style.display = 'block';
            document.getElementById('video-count-info').textContent = data.count + ' video bulundu';
            this.updateSelectInfo();
            this.toast(`${data.count} video listelendi`, 'success');
        } catch (e) {
            this.showError('Bağlantı hatası: ' + e.message);
        } finally {
            this.hideLoading();
        }
    },

    renderVideoGrid(videos) {
        const grid = document.getElementById('video-grid');
        grid.innerHTML = '';
        videos.forEach((v, i) => {
            const card = document.createElement('div');
            card.className = 'video-card';
            card.dataset.index = i;
            card.innerHTML = `
        <div class="video-check">✓</div>
        <div class="video-thumb">
          <img src="${v.thumbnail}" alt="${this.esc(v.title)}" loading="lazy"
               onerror="this.src='https://img.youtube.com/vi/${v.id}/hqdefault.jpg'">
          <span class="video-duration">${v.duration}</span>
        </div>
        <div class="video-body">
          <div class="video-title">${this.esc(v.title)}</div>
          <div class="video-meta">
            <span>👁 ${this.formatNum(v.view_count)}</span>
            <span>📅 ${this.formatDate(v.upload_date)}</span>
          </div>
        </div>`;
            card.addEventListener('click', () => this.toggleSelect(i, card));
            grid.appendChild(card);
        });
    },

    toggleSelect(index, card) {
        if (this.selectedVideos.has(index)) {
            this.selectedVideos.delete(index);
            card.classList.remove('selected');
        } else {
            this.selectedVideos.add(index);
            card.classList.add('selected');
        }
        this.updateSelectInfo();
    },

    selectAll() {
        this.allVideos.forEach((_, i) => this.selectedVideos.add(i));
        document.querySelectorAll('.video-card').forEach(c => c.classList.add('selected'));
        this.updateSelectInfo();
    },

    deselectAll() {
        this.selectedVideos.clear();
        document.querySelectorAll('.video-card').forEach(c => c.classList.remove('selected'));
        this.updateSelectInfo();
    },

    updateSelectInfo() {
        const el = document.getElementById('selected-count');
        if (el) el.textContent = this.selectedVideos.size;
    },

    async downloadSelected() {
        if (this.selectedVideos.size === 0) { this.toast('Önce video seçin', 'error'); return; }
        const selected = [...this.selectedVideos].map(i => this.allVideos[i]);
        await this.startDownloads(selected);
    },

    async downloadAll() {
        if (this.allVideos.length === 0) { this.toast('Video listesi boş', 'error'); return; }
        await this.startDownloads(this.allVideos);
    },

    async startDownloads(videos) {
        document.getElementById('queue-section').style.display = 'block';
        for (const video of videos) {
            const jobId = 'job_' + Date.now() + '_' + Math.random().toString(36).slice(2, 7);
            this.addQueueItem(jobId, video.title);
            await this.beginDownload(video.url, jobId);
            // Eş zamanlı limit
            await this.sleep(500);
        }
        this.toast(`${videos.length} indirme sıraya alındı`, 'info');
    },

    async beginDownload(videoUrl, jobId) {
        const form = new FormData();
        form.append('video_url', videoUrl);
        form.append('job_id', jobId);
        form.append('quality', this.quality);

        try {
            const res = await fetch('api/download.php', { method: 'POST', body: form });
            const data = await res.json();
            if (!data.success) {
                this.updateQueueItem(jobId, 'error', 0, { error: data.error });
                return;
            }
            this.pollStatus(jobId);
        } catch (e) {
            this.updateQueueItem(jobId, 'error', 0, { error: e.message });
        }
    },

    pollStatus(jobId) {
        const interval = setInterval(async () => {
            try {
                const form = new FormData();
                form.append('action', 'status');
                form.append('job_id', jobId);
                const res = await fetch('api/status.php', { method: 'POST', body: form });
                const data = await res.json();
                this.updateQueueItem(jobId, data.status, data.progress, data);
                if (data.status === 'done' || data.status === 'error') {
                    clearInterval(interval);
                }
            } catch (e) { clearInterval(interval); }
        }, 1500);
        this.downloadJobs[jobId] = { interval };
    },

    addQueueItem(jobId, title) {
        const list = document.getElementById('queue-list');
        const item = document.createElement('div');
        item.className = 'queue-item';
        item.id = 'qi-' + jobId;
        item.innerHTML = `
      <div class="queue-item-header">
        <div class="queue-title" title="${this.esc(title)}">📹 ${this.esc(title)}</div>
        <span class="queue-status pending" id="qs-${jobId}">⏳ Bekliyor</span>
      </div>
      <div class="progress-wrap">
        <div class="progress-bar" id="pb-${jobId}" style="width:0%"></div>
      </div>
      <div class="progress-info">
        <span id="pi-${jobId}">Başlatılıyor...</span>
        <span id="pe-${jobId}"></span>
      </div>
      <div id="pf-${jobId}" class="file-list" style="margin-top:8px;"></div>
      <div style="margin-top:8px;display:flex;gap:6px;">
        <button class="btn btn-sm btn-danger" onclick="App.killJob('${jobId}')">⏹ Durdur</button>
        <button class="btn btn-sm btn-ghost" onclick="App.deleteJob('${jobId}')">🗑 Sil</button>
      </div>`;
        list.prepend(item);
    },

    updateQueueItem(jobId, status, progress, data) {
        const statusEl = document.getElementById('qs-' + jobId);
        const barEl = document.getElementById('pb-' + jobId);
        const infoEl = document.getElementById('pi-' + jobId);
        const etaEl = document.getElementById('pe-' + jobId);
        const filesEl = document.getElementById('pf-' + jobId);
        if (!statusEl) return;

        const labels = { downloading: '⬇️ İndiriliyor', done: '✓ Tamamlandı', error: '✕ Hata', pending: '⏳ Bekliyor' };
        statusEl.className = 'queue-status ' + status;
        statusEl.textContent = labels[status] || status;

        if (barEl) barEl.style.width = progress + '%';
        if (infoEl) {
            if (status === 'error') infoEl.textContent = '⚠ Hata: ' + (data.error || 'Bilinmeyen hata');
            else if (status === 'done') infoEl.textContent = '✓ İndirme tamamlandı';
            else if (data.speed) infoEl.textContent = `%${Math.round(progress)} — ${data.speed}`;
            else infoEl.textContent = `%${Math.round(progress)}`;
        }
        if (etaEl) etaEl.textContent = data.eta ? 'ETA ' + data.eta : '';

        // Dosya linkleri
        if (filesEl && data.files && data.files.length > 0) {
            filesEl.innerHTML = data.files.map(f => `
        <a href="${f.url}" download class="file-item">
          ⬇️ ${this.esc(f.name)}
          <span class="file-size">${this.formatBytes(f.size)}</span>
        </a>`).join('');
        }
    },

    async killJob(jobId) {
        const form = new FormData();
        form.append('action', 'kill');
        form.append('job_id', jobId);
        await fetch('api/status.php', { method: 'POST', body: form });
        if (this.downloadJobs[jobId]) {
            clearInterval(this.downloadJobs[jobId].interval);
        }
        this.updateQueueItem(jobId, 'error', 0, { error: 'Kullanıcı tarafından durduruldu' });
        this.toast('İndirme durduruldu', 'info');
    },

    async deleteJob(jobId) {
        if (!confirm('Bu indirilen dosyaları sil?')) return;
        const form = new FormData();
        form.append('action', 'delete');
        form.append('job_id', jobId);
        await fetch('api/status.php', { method: 'POST', body: form });
        const el = document.getElementById('qi-' + jobId);
        if (el) el.remove();
        this.toast('Silindi', 'info');
    },

    async loadDownloads() {
        try {
            const res = await fetch('api/status.php?action=list_downloads');
            const data = await res.json();
            if (data.downloads && data.downloads.length > 0) {
                document.getElementById('queue-section').style.display = 'block';
                data.downloads.forEach(d => {
                    if (!document.getElementById('qi-' + d.job_id)) {
                        this.addQueueItem(d.job_id, 'Önceki İndirme: ' + d.job_id);
                        this.updateQueueItem(d.job_id, 'done', 100, { files: d.files });
                    }
                });
            }
        } catch (e) { }
    },

    showError(msg) {
        const el = document.getElementById('error-msg');
        el.innerHTML = `<div class="alert alert-error">❌ ${msg}</div>`;
        el.style.display = 'block';
        this.hideLoading();
    },

    showLoading(msg = 'Yükleniyor...') {
        const el = document.getElementById('loading-overlay');
        el.querySelector('.loading-text').innerHTML = msg;
        el.classList.add('active');
    },

    hideLoading() {
        document.getElementById('loading-overlay').classList.remove('active');
    },

    toast(msg, type = 'info') {
        const tc = document.getElementById('toast-container');
        const t = document.createElement('div');
        t.className = `toast ${type}`;
        t.innerHTML = `<span>${type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️'}</span>${msg}`;
        tc.appendChild(t);
        setTimeout(() => t.remove(), 4000);
    },

    esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'); },

    formatNum(n) {
        if (!n) return '0';
        if (n >= 1e6) return (n / 1e6).toFixed(1) + 'M';
        if (n >= 1e3) return (n / 1e3).toFixed(1) + 'K';
        return String(n);
    },

    formatDate(d) {
        if (!d || d.length < 8) return '';
        return d.slice(0, 4) + '/' + d.slice(4, 6) + '/' + d.slice(6, 8);
    },

    formatBytes(b) {
        if (!b) return '';
        const u = ['B', 'KB', 'MB', 'GB'];
        let i = 0;
        while (b >= 1024 && i < u.length - 1) { b /= 1024; i++; }
        return b.toFixed(1) + ' ' + u[i];
    },

    sleep(ms) { return new Promise(r => setTimeout(r, ms)); },
};

document.addEventListener('DOMContentLoaded', () => App.init());
