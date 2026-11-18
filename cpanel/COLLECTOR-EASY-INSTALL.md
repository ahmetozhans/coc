# Mahanpro kolay kurulum

1. cPanel Dosya Yöneticisi'nde public_html ve mahanpro-private klasörlerinin bulunduğu ana dizini aç. ZIP'i bu dizine yükleyip Çıkart düğmesine bas.
2. https://mahanpro.fun/studio#collector adresini aç. Mevcut yönetici hesabınla giriş yaptıktan sonra Studio menüsündeki Otomatik Köy Toplama bölümünü seç. Menü görünmezse Ctrl+F5 ile yenile.
3. Studio içindeki Kurulumu tamamla düğmesine bas. Onay kuyruğu ve tarama ayarları aynı bölümde açılır.

Kurulum ekranı dosyaları karşılaştırır, uyumlu değişiklikleri birleştirir, değişecek dosyaları özel klasöre yedekler ve ek veritabanı tablolarını oluşturur. Aynı satırlarda farklı canlı değişiklikler varsa ilgili dosyaları listeler. Yeni kayıtlar yönetici onay kuyruğuna düşer. İlk taramayı Kaynaklar ve ayarlar bölümünden başlatabilirsin.

Kurulum tamamlandıktan sonra sayfa kilitlenir. Aynı sürümü tekrar kurmaz.

## Günlük zamanlama

Bu adım yalnızca otomatik günlük tarama içindir. cPanel Cron Jobs bölümünde saatte bir çalışacak görev oluştur. KULLANICI bölümünü hosting kullanıcı adınla, PHP yolunu hostingindeki PHP 8.2+ komutuyla değiştir.

15 * * * * /usr/local/bin/ea-php82 /home/KULLANICI/mahanpro-private/collector-cron.php >> /home/KULLANICI/collector-cron.log 2>&1

Ardından panelde otomatik taramayı aç. Günlük sınırı sistem uygular.

Manuel tarama panelden çalışır. Bazı kaynakların görsel erişimi bu ortamda HTTP 403 döndürdü. Hostingindeki ilk taramanın sonuçlarını panelde incele.

## İngilizce içerik

Yeni başlıklar, açıklamalar ve ziyaretçiye görünen detay sayfası İngilizcedir. Admin/Studio arayüzü Türkçe kalır.
