# Mahanpro otomatik köy toplama kurulumu

Bu özellik mevcut PHP/MySQL projesine eklenir. PR hedefi cpanel-php-migration dalıdır. Üretime otomatik dağıtım yapılmaz.

## Mevcut site incelemesi

18 Kasım 2026'da canlı /bases, /api/content ve mevcut köy detay sayfası erişilebilirdi. Canlı mahanpro-night.css ve app.js kök GitHub dosyalarından farklıydı. JavaScript farklarının bir kısmı cPanel derleyicisinin TH19 ve detay URL dönüşümleridir. CSS üzerinde ayrıca yerel değişiklik var. Sunucudaki özel PHP dosyaları bu incelemede okunamadı. Güncelleme paketi canlı tasarım CSS'ini, app.js'yi, Studio şablonunu, config.php'yi veya veritabanı içeriğini değiştirecek bir dosya içermez.

CONTENT_AUTOMATION.md GitHub Pages/D1 akışını tanımlar. Bu cPanel özelliği MySQL'de ayrı öneri tabloları kullanır. Mevcut JSON kuyruğu, eski kararlar ve yayın geçmişi çalışmayı sürdürür.

## Gereksinimler

PHP 8.2+, PDO MySQL, cURL, GD (WebP), DOM, fileinfo, InnoDB ve HTTPS kaynak erişimi. API anahtarı gerekmez.

## Kolay kurulum

Kolay kurulum paketi için BASLA.md içindeki üç adımı kullan. ZIP'i cPanel ana dizininde çıkar, /mahanpro-setup.php adresini aç, mevcut yönetici hesabınla giriş yapıp Kurulumu tamamla düğmesine bas. Ekran dosya yedeğini ve ek SQL tablolarını hazırlar. Aynı satırlarda çakışma varsa ilgili dosyaları gösterir. Dosyanın diğer alanlarındaki özel değişiklikler korunarak birleştirilir.

Kaynak depodan kolay paketi üretmek için node scripts/build-collector-easy-update.mjs /tmp/mahanpro-easy-update çalıştırılabilir.

## Terminal üzerinden kurulum

1. cPanel'den mevcut public_html, mahanpro-private ve veritabanının tam yedeğini al. Önce deneme kopyasında kur.
2. Kaynak depoda şu komutla güncelleme paketi üret. Paketi cPanel ana dizininde public_html dışında collector-update klasörüne aç.

   node scripts/build-collector-update.mjs /tmp/mahanpro-collector-update

3. phpMyAdmin'de mevcut Mahanpro veritabanını seç. Yalnızca paketteki 20261118-layout-collector.sql dosyasını içe aktar. Migration ek tablolar oluşturur ve tekrar çalıştırılabilir. Eski schema.sql ve başlangıç içerik SQL'ini yeniden çalıştırma.
4. Terminal varsa önce kontrolü çalıştır.

   php /home/KULLANICI/collector-update/collector-install.php /home/KULLANICI --check

5. Canlı PHP dosyası referans sürümden farklıysa kurulum dosya isimlerini bildirerek durur. Canlı dosyayı reference/ ve files/ sürümleriyle karşılaştır, değişiklikleri birleştir. Aynı komutu --apply ile çalıştırınca kurucu özel yedek alır ve index.php dosyasını en son değiştirir. Terminal yoksa File Manager'da ek collector modüllerini yükle, ardından ortak PHP dosyalarındaki değişiklikleri canlı dosyalarla birleştir. public_html/index.php en son yüklenmeli. Farklı canlı dosyalara doğrudan üzerine yazma.
6. HTTPS ile /studio üzerinden giriş yap. Otomatik Köy Toplama bağlantısını veya /admin/collector adresini aç.
7. Kaynaklar ve ayarlar bölümünde kaynakları seç. Kaynak başına günlük aday bütçesi varsayılan olarak 20'dir. Manuel tarama başlat ile ilk denemeyi çalıştır. Önizleme, kaynak tarihi, oyun bağlantısı ve İngilizce açıklamayı incele.
8. Gerekiyorsa İngilizce metni düzenle. Düzenleme öneri parmak izini değiştirir. Kayıtları seç, gerçek ekran görüntüsü onayını işaretle ve Seçilenleri onayla ve yayımla düğmesini kullan. Tek kayıt aynı yolla onaylanabilir.
9. cPanel Cron Jobs bölümüne saatlik görevi ekle. PHP yolunu hostingindeki PHP 8.2+ komutuyla değiştir.

   15 * * * * /usr/local/bin/ea-php82 /home/KULLANICI/mahanpro-private/collector-cron.php >> /home/KULLANICI/collector-cron.log 2>&1

10. Panelde otomatik taramayı aç ve UTC saatini belirle. Türkiye saati UTC+3'tür. 04 UTC, Türkiye'de 07'dir. Cron saatlik çağrılsa da last_discovery ve veritabanı kilidi UTC gününde tek keşfe izin verir. Manuel tarama da aynı sınırı kullanır.
11. Tarama geçmişinden sonuçları ve hataları kontrol et. Kaynak erişimi engellenirse hata kaydedilir ve kaynak atlanır.
12. Yayın sonrasında /api/content, galeri, detay URL'si ve sitemap.xml içinde ilgili kaydı kontrol et. WebP görselinin kendi alan adında açıldığını doğrula. Telefon ve masaüstünden menüyü, önizlemeyi, paylaşımı ve oyun bağlantısını dene.

## İşleyiş ve sınırlar

Kaynak kapsamı CoC Base Finder /thN ile img.cocbasefinder.com, Clash Loot /coc/bases/th-N ile assets.clashloot.gg ve ClashLy /bases/thN?sort=new ile storage.clashly.app adresleridir. Kaynaklar panelde açılıp kapatılır. Yeni bir alan adı eklemek için izin kapsamı ve ayrıştırıcı birlikte güncellenir.

Yalnızca herkese açık HTML'de taşınan kayıtlar ayrıştırılır. Kaynak robots.txt kuralları, Crawl-delay ve Retry-After dikkate alınır. İstekler iki saniye aralıklı, süre/boyut sınırlı ve izinli alan adlarına kısıtlıdır. 429/5xx için en fazla üç deneme uygulanır. Dört dakikalık çalışma sınırı ve günlük bütçe dolunca kalan kayıtlar sonraki güne kalır.

TH19'dan TH3'e doğru taranır. Her kaynakta TH başına en çok beş uygun aday incelenir. Üst seviyelerde 20 aktif/bekleyen kayıt varsa haftalık yenileme günü dışında sonraki seviyelere öncelik geçer. Sahte kayıt veya eski tarih üretilmez. Değişmeyen ya da reddedilmiş aynı sürüm yeniden önerilmez.

Gerçek ekran görüntülerinin boyutları ve biçimi kontrol edilir. Görsel kırpılmadan WebP'ye dönüştürülür. Onay öncesi web kökü dışındaki collector-images klasöründe tutulur ve yalnızca yönetici oturumuyla önizlenir. Onay sonrası SHA-256 adlı dosya kendi sunucuna alınır. Görselin gerçekten bağlantıdaki köyle eşleştiğini yönetici önizlemeyi inceleyerek onaylar.

Oyun URL'sinin resmi alan adı, parametreleri ve TH alanı kontrol edilir. Web sayfasının açılması oyun içi doğrulama sayılmaz. Düzenler Doğrulanmadı olarak yayımlanır. Yönetici gerçekten oyunda içe aktardıktan sonra panelde doğrulama tarihini kaydedebilir.

Ziyaretçiye görünen yeni başlık, açıklama, detay sayfası etiketleri ve SEO metinleri İngilizcedir. Admin/Studio Türkçe arayüzünü kullanır. İngilizce metin kategoriye uygun özgün kontrol önerileriyle hazırlanır. Düzenin saldırı başarısı veya görselden teyit edilmemiş bina özellikleri ileri sürülmez. Yerleşime özel savunma gözlemleri onaydan önce düzenlenebilir.

Aynı decoded oyun kimliği, görsel SHA-256 ve yakın görsel parmak izi tekrarları ayrı gösterilir. Yakın benzerlik yanlış eşleşebilir, kaynak kaydı ve resmi bağlantı incelenmelidir.

TH başına 20 aktif düzen sınırı yayın sırasında veritabanı kilidiyle uygulanır. Mevcut kayıt otomatik silinmez. Panel arşivleme önerisi gösterir, yönetici seçer. Arşivlenenler galeri/sitemap'ten çıkar. Mevcut detay URL'si ve istatistikleri korunur. Studio'dan tekrar yayınlama kapasite kontrolüne tabidir. Önceden zaten 20'yi aşan bir seviye varsa otomatik kesinti yapılmaz, sayı azaltılabilir ve yeni artış durdurulur.

Kaynak güncellemelerinde sabit içerik kimliği kullanılır. Studio'da elle düzenlenen kimlikler otomatik güncellenmez. Öneri ve görsel parmak izi eşleşmeden yayın yapılmaz. Toplu yayında herhangi bir kayıt başarısızsa veritabanı işlemi geri alınır.

## Geri alma

Cron'u ve otomatik taramayı kapat. Kurucunun özel yedeğinden değiştirilen PHP dosyalarını geri koy. Ek tabloları tutmak öneri/karar geçmişini korur. Üretim veritabanını eski başlangıç SQL'iyle yenileme. Yeni yayımlanan kayıtlar mevcut public_content tablosunda durur.

## Test

npm ci ve npm test mevcut JavaScript testlerini çalıştırır. tests/cpanel-collector.php yalnızca boş, geçici MariaDB veritabanına temel şemayı ve ek migration'ı kurar. COLLECTOR_TEST_DSN bu veritabanının DSN değeridir. COLLECTOR_SECOND_IMAGE ikinci gerçek Clash Loot görselinin dosya yoludur. İki gerçek görsel ve üç kaynağın alan örnekleri tests/fixtures/collector içinde bulunur.

   COLLECTOR_TEST_DSN='mysql:host=127.0.0.1;dbname=mahanpro_test;charset=utf8mb4' COLLECTOR_SECOND_IMAGE=/private/second-image.webp php tests/cpanel-collector.php /path/to/built/mahanpro-private

Test bağlantısının root ve boş parola varsayılanı yalnızca izole yerel test içindir. Üretim bilgilerini test dosyasına veya GitHub'a yazma.
