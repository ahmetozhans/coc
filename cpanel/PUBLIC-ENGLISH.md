# Ziyaretçi sitesi İngilizce

Bu güncelleme ziyaretçiye görünen arayüzü tek dil olarak İngilizceye sabitler. Admin ve Studio Türkçe arayüzlerini kullanmayı sürdürür.

- Dil seçimi kaldırılır. Tarayıcı dili veya eski vf-language tercihi ziyaretçi dilini değiştirmez.
- Ana sayfa, köy galerisi, detaylar, rehberler, ordular, klan aracı, planlayıcı, durum mesajları ve 404 sayfası İngilizcedir.
- Kayıtlı İngilizce rehber/CMS alanları kullanılır. Mevcut Türkçe standart köy başlıkları ziyaretçi tarafında İngilizce gösterilir. Özel bir metnin İngilizce karşılığı yoksa kategoriye uygun İngilizce varsayılan açıklama kullanılır. Özgün veritabanı metni yönetici için saklanır.
- Köy kimlikleri, mevcut canonical adresleri ve istatistikler aynı kayıtları kullanır. Eski Türkçe slug adresleri korunur.
- Güncelleme mevcut galeriye kayıt eklemez. İnceleme sırasında canlı public endpoint CMS revision 24 ve boş bir aktif galeri döndürüyordu.
- Canlı CSS ve logo dosyaları pakete alınmaz. Ön yüz JavaScript değişiklikleri incelenen canlı dosyalarla birleştirilir. Özel PHP dosyaları kurulumda uyumluluk kontrolünden geçer.
- Kurulum makbuzu sürüme bağlanır. Önceden kurulan sürüm, bu yeni güncellemenin Studio içinde kurulmasını engellemez.

## Kurulum

ZIP'i public_html ve mahanpro-private klasörlerinin bulunduğu ana dizinde çıkar. Studio sayfasını Ctrl+F5 ile yenile. Otomatik Köy Toplama bölümündeki Kurulumu tamamla düğmesine bas. Güncellemeyi aynı Studio alanından uygula.

## Doğrulama

Tarayıcının dilini Türkçe yapıp eski vf-language değerini tr bırakarak ziyaretçi sayfalarını aç. Dil seçimi görünmemeli, html lang en olmalı. Galeri ve detay sayfalarında İngilizce başlıklar görünmeli. Önceden kaydedilmiş düzen URL'si aynı canonical adrese gitmeli. Admin/Studio menüleri Türkçe kalmalı.

Üretime otomatik dağıtım yapılmaz. Çakışan canlı PHP değişiklikleri kurulum ekranında listelenir.
