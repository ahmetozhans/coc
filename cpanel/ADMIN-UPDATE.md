# Mahanpro: MySQL üzerinden yönetici parolası

1. phpMyAdmin'de Mahanpro veritabanını seç ve bu paketteki admin-login.sql dosyasını içe aktar. Mevcut içerik, sayaç ve onay tablolarına dokunulmaz.
2. cPanel Dosya Yöneticisi'nde mevcut mahanpro-private/reviews.php dosyasını yedekle ve paketteki aynı dosyayı yerine koy.
3. mahanpro-private/config.php içindeki return dizisine aşağıdaki satırı ekle. Yer tutucu yerine parola yöneticisiyle ürettiğin, en az 24 karakterli rastgele bir kod yaz. Kodda harf, rakam, tire veya alt çizgi kullanılabilir. Düz yönetici parolası yazdığın eski admin_password_hash alanını boş bırak.

    'admin_setup_code' => 'BURAYA_RASTGELE_KOD',

4. https://mahanpro.fun/admin adresini HTTPS sertifika uyarısı olmadan aç. Kurulum kodunu ve kullanmak istediğin yeni yönetici parolasını gir. Sonraki ekranda admin kullanıcı adı ve yeni parolanla giriş yap. İstersen config.php içinde admin_username ile farklı bir ad belirle.
5. Giriş başarılı olduktan sonra config.php içindeki admin_setup_code değerini boşalt veya satırı kaldır. MySQL parolası, CoC anahtarı ve diğer ayarlar aynı kalır.

Mevcut geçerli parola özeti zaten yapılandırılmışsa normal giriş korunur. Kurulum tamamlandıktan sonra yeniden parola kurma formu açılmaz. Parola özeti MySQL'deki admin_credentials tablosunda tutulur.
