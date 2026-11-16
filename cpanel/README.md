# cPanel geçişi

Bu dal herkese açık sayfaları, sayaçları, İçerik Stüdyosu'nu ve öneri inceleme panelini PHP/MySQL üzerinden sunar. Önce deneme ortamında kur.

1. Güncel kaynaktan `node scripts/build-cpanel-php.mjs /path/to/new-release` çalıştır.
2. Paketteki `public_html` içeriğini alan adının Document Root klasörüne kopyala. `mahanpro-private` klasörünü Document Root'un yanına ve web'den erişilemeyen bir konuma yerleştir. PHP 8.2+ ve `pdo_mysql` etkin olmalı. Temiz adresler Apache `mod_rewrite` ve `.htaccess` desteği gerektirir.
3. cPanel'de MySQL/MariaDB veritabanı ve yetkili kullanıcı oluştur. `schema.sql` dosyasını phpMyAdmin'de **yeni ve boş** veritabanına uygula. `config.example.php` dosyasını özel klasörde `config.php` olarak kopyalayıp cPanel bağlantı bilgilerini gir.
4. cPanel Terminal'de özel klasörde `php seed.php catalog.json guides.json` çalıştır. Bu adım GitHub'daki yayımlanmış içerik anlık görüntüsünü değişmeyen kimliklerle içe aktarır. D1 üzerindeki panel düzenlemelerini veya sayaçları aktarmak için kullanma.
5. Deneme ortamında `/`, `/bases`, `/attacks`, `/armies`, `/sitemap.xml`, `/robots.txt` ve `/api/content` adreslerini kontrol et. Eski `.html` sayfa adresleri 301 döndürmeli. Tarayıcıda JavaScript kapalıyken galeri ve rehberlerin ana içeriği görünmeli.

## Öneri paneli ve geçmiş

- HTTPS üzerinden `/admin` sayfasını kullan. Uzun, rastgele ve benzersiz bir parola üret. Parolayı terminal komutuna veya Git'e yazmadan PHP `password_hash` ile özetle ve yalnızca özel `config.php` dosyasındaki `admin_password_hash` alanına özeti koy. Boş özetle panel kapalı kalır.
- Panel, özel klasördeki `content-inbox.json` önerilerini okur. Kararlar tam öneri parmak izine bağlanır. Panelde onay vermek içeriği kendiliğinden yayımlamaz.
- D1 geçmişi için `layout_events`, `layout_favorites`, `content_reviews` ve `admin_content` tablolarını tam olarak dışa aktar. Kökünde bu dört anahtarın yer aldığı ve değerleri satır listeleri olan özel bir JSON dosyası hazırla. Kullanılmamış bir tablo için boş liste kullan. Dosyayı web köküne koyma. Yeni hedef henüz trafik almıyorken `php import-history.php /private/export.json` çalıştır, kaynak ve hedef satır sayılarını ve CMS revizyonunu karşılaştır. Komut boş hedef tablolar ve değiştirilmemiş CMS ister. Tarayıcı favorileri alan adı değişince tarayıcıdan yeni adrese kendiliğinden taşınmaz.
- Giriş yaptıktan sonra `/studio` üzerinden içerik, görünüm, SEO ayarları, sayaçlar ve panel yedekleri yönetilebilir.

## cPanel'de onaylı yayın

Yeni önerileri özel klasördeki `content-inbox.json` dosyasına, `CONTENT_AUTOMATION.md` kurallarını izleyerek hazırla. Dosyayı aynı dosya sisteminde atomik değiştir. Önce yedeğini al ve bekleyen kayıtları koru. Mevcut GitHub Pages görevi cPanel'e kendiliğinden yazmaz. Araştırma ve yayın işlemini bu komutları çalıştırabilen yetkili bir yürütücüye bağla.

- PHP `curl` ve `fileinfo` uzantıları açık olmalı. `config.php` içindeki `document_root` gerçek mutlak web kökü olmalı.
- Düzen için kaynak sayfasını ve eşleşen resmi oyun bağlantısını kontrol et. Kaynaktan yeni indirilen, kırpılmamış görseli özel klasöre koy ve `php publish-approved.php PROPOSAL_ID /private/verified-image.webp` çalıştır.
- Rehber için metni değiştirme. Ordu bağlantısı varsa kaynakta aynı bağlantının yayımlandığını tekrar kontrol et. `php publish-approved.php PROPOSAL_ID` çalıştır.
- Komut güncel MySQL onayını, tam öneri parmak izini, resmi bağlantıyı, görsel baytlarını, kapasiteyi ve manuel düzenleme çakışmasını kontrol eder. Kayıt eklendikten sonra canlı sayfayı ve API'yi kontrol eder. Doğrulama hatasında kayıt görünür olabilir; komut kısmi durumu bildirir ve aynı öneriyle tekrar denenebilir.

## Canlıya geçişten önce

Son D1 anlık görüntüsünü cPanel trafik almadan hemen önce al ve sayımları karşılaştır. PHP/MySQL, Apache yönlendirmeleri, oturum, onay, sayaç tekilleştirmesi ve içerik yayınını hedef cPanel'de doğrula. Otomasyon yeni hedefte içerik yayımlayabildiğinde ve geçmiş karşılaştırması geçtiğinde DNS geçişini yap. Eski yayını geri dönüş için hazır tut.
