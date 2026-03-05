# YTDownloader - Kurulum Kılavuzu

## cPanel'e Yükleme

### 1. Dosyaları Yükleyin
- cPanel File Manager → `public_html/ytdownloader/` klasörüne zip'i çıkartın
- (veya istediğiniz subdirectory'e)

### 2. Klasör İzinlerini Ayarlayın
```bash
chmod 755 bin/
chmod 755 tmp/
chmod 755 downloads/
```
cPanel File Manager → sağ tık → Change Permissions → 755

### 3. setup.php'yi Ziyaret Edin
```
https://siteniz.com/ytdownloader/setup.php
```
- Tüm checkler ✅ yeşil görünüyorsa hazırsınız
- "exec() Fonksiyonu" ❌ kırmızıysa: **cPanel → Select PHP Version → Disable Functions → exec'i listeden kaldırın**

### 4. yt-dlp'yi Kurun
Setup sayfasında "yt-dlp İndir/Yeniden Kur" butonuna tıklayın.
Sunucu internet erişimi varsa otomatik indirilir.

### 5. Kullanmaya Başlayın
```
https://siteniz.com/ytdownloader/
```

---

## Kullanım

1. YouTube kanal URL'sini yapıştırın:
   - `https://www.youtube.com/@kanaladi`
   - `https://www.youtube.com/channel/UCxxxxxx`
2. Kaliteyi seçin (En İyi / 1080p / 720p / 480p / 360p / MP3)
3. "Listele" butonuna tıklayın → videolar listelenir
4. Tek tek tıklayarak seçin VEYA "Tümünü Seç"
5. "Seçilenleri İndir" veya "Tümünü İndir"
6. İndirme kuyruğunda ilerlemeyi izleyin
7. Tamamlandığında dosya linkine tıklayıp indirin

---

## Sorun Giderme

| Hata | Çözüm |
|------|-------|
| exec() devre dışı | cPanel > PHP Selector > Disable Functions > exec kaldır |
| yt-dlp indirmiyor | Sunucunun internete erişimi var mı? curl aktif mi? |
| Video listesi boş | Kanal URL doğru mu? Yt-dlp sürümünü güncelleyin |
| İndirme başlamıyor | downloads/ ve tmp/ klasörleri yazılabilir mi? |
| Timeout | .htaccess'teki max_execution_time ayarı sayfaya uygulanıyor mu? |

---

## Teknik Detaylar

- **Backend:** PHP 7.4+
- **İndirici:** yt-dlp (otomatik kurulum)  
- **Desteklenen:** YouTube kanalları, playlistler, tekli videolar
- **Hosting:** cPanel uyumlu, Node.js/Composer gerektirmez
- **Kalite:** MP4 (1080p/720p/480p/360p) ve MP3 (audo only)
