# cPanel geçişi

Bu hazırlık dalı, mevcut tasarımı kullanarak herkese açık sayfaları PHP ile sunar. Yayının tamamı için geçiş öncesi denemede kullanılmalıdır. Yönetim paneli, onay kayıtları, otomatik yayın ve sayaç geçmişi bu aşamada taşınmadı.

1. Güncel kaynaktan `node scripts/build-cpanel-php.mjs /path/to/new-release` çalıştır.
2. Paketteki `public_html` içeriğini alan adının Document Root klasörüne kopyala. `mahanpro-private` klasörünü Document Root'un yanına ve web'den erişilemeyen bir konuma yerleştir. PHP 8.2+ ve `pdo_mysql` etkin olmalı. Temiz adresler Apache `mod_rewrite` ve `.htaccess` desteği gerektirir.
3. cPanel'de MySQL/MariaDB veritabanı ve yetkili kullanıcı oluştur. `schema.sql` dosyasını phpMyAdmin'de **yeni ve boş** veritabanına uygula. `config.example.php` dosyasını özel klasörde `config.php` olarak kopyalayıp cPanel bağlantı bilgilerini gir.
4. cPanel Terminal'de özel klasörde `php seed.php catalog.json guides.json` çalıştır. Bu adım GitHub'daki yayımlanmış içerik anlık görüntüsünü değişmeyen kimliklerle içe aktarır. D1 üzerindeki panel düzenlemelerini veya sayaçları aktarmak için kullanma.
5. Deneme ortamında `/`, `/bases`, `/attacks`, `/armies`, `/sitemap.xml`, `/robots.txt` ve `/api/content` adreslerini kontrol et. Eski `.html` sayfa adresleri 301 döndürmeli. Tarayıcıda JavaScript kapalıyken galeri ve rehberlerin ana içeriği görünmeli.

Canlı geçişten önce yönetim panelinin kimlik doğrulaması, D1 verilerinin bire bir aktarımı, onay parmak izi kontrolleri ve yeni yayının otomasyona bağlanması tamamlanmalı. DNS değişikliğinden önce kaynak ve hedef kayıtları karşılaştır.
