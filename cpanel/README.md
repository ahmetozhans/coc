# cPanel geçişi

Bu hazırlık dalı, herkese açık sayfaları, sayaç uçlarını ve öneri inceleme panelini PHP ile sunar. Deneme ortamında doğrulanmadan canlıya geçirilmemelidir.

1. Güncel kaynaktan `node scripts/build-cpanel-php.mjs /path/to/new-release` çalıştır.
2. Paketteki `public_html` içeriğini alan adının Document Root klasörüne kopyala. `mahanpro-private` klasörünü Document Root'un yanına ve web'den erişilemeyen bir konuma yerleştir. PHP 8.2+ ve `pdo_mysql` etkin olmalı. Temiz adresler Apache `mod_rewrite` ve `.htaccess` desteği gerektirir.
3. cPanel'de MySQL/MariaDB veritabanı ve yetkili kullanıcı oluştur. `schema.sql` dosyasını phpMyAdmin'de **yeni ve boş** veritabanına uygula. `config.example.php` dosyasını özel klasörde `config.php` olarak kopyalayıp cPanel bağlantı bilgilerini gir.
4. cPanel Terminal'de özel klasörde `php seed.php catalog.json guides.json` çalıştır. Bu adım GitHub'daki yayımlanmış içerik anlık görüntüsünü değişmeyen kimliklerle içe aktarır. D1 üzerindeki panel düzenlemelerini veya sayaçları aktarmak için kullanma.
5. Deneme ortamında `/`, `/bases`, `/attacks`, `/armies`, `/sitemap.xml`, `/robots.txt` ve `/api/content` adreslerini kontrol et. Eski `.html` sayfa adresleri 301 döndürmeli. Tarayıcıda JavaScript kapalıyken galeri ve rehberlerin ana içeriği görünmeli.

## Öneri paneli ve geçmiş

- HTTPS üzerinden `/admin` sayfasını kullan. Uzun, rastgele ve benzersiz bir parola üret. Parolayı terminal komutuna veya Git'e yazmadan PHP `password_hash` ile özetle ve yalnızca özel `config.php` dosyasındaki `admin_password_hash` alanına özeti koy. Boş özetle panel kapalı kalır.
- Panel, özel klasördeki `content-inbox.json` önerilerini okur. Kararlar tam öneri parmak izine bağlanır. Panelde onay vermek içeriği kendiliğinden yayımlamaz.
- D1 geçmişi için `layout_events`, `layout_favorites` ve `content_reviews` tablolarını tam olarak dışa aktar. Kökünde bu üç anahtarın yer aldığı ve değerleri satır listeleri olan özel bir JSON dosyası hazırla. Web'e açık klasöre koyma. Yeni hedef henüz trafik almıyorken `php import-history.php /private/export.json` çalıştır ve üç tablo sayısını kaynakla karşılaştır. Komut boş hedef tablolar ister. Aktif tarayıcı favorileri alan adı değişince tarayıcıdan yeni adrese kendiliğinden taşınmaz.

## Canlıya geçişten önce

Mevcut Studio'nun içerik ve site ayarları düzenleyicisi, D1 `admin_content` düzenlemeleri, son sayaç farklarının aktarımı ve onaylı içerik yayın otomasyonunun yeni hedefe bağlanması tamamlanmalıdır. PHP/MySQL, Apache yönlendirmeleri, oturum, onay, sayaç tekilleştirmesi ve içerik yayınını hedef cPanel'de doğrulamadan DNS değiştirme.
