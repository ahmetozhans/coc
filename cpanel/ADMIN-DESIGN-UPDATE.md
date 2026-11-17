# Mahanpro: yönetici giriş ekranı ve öneri paneli

MySQL yönetici kurulumu yapılmışsa SQL çalıştırmana gerek yok. İlk kez kuruyorsan paketteki admin-login.sql dosyasını önce phpMyAdmin'de Mahanpro veritabanına içe aktar. Ardından özel config.php dosyasına en az 24 karakterli rastgele bir admin_setup_code ekle ve /admin ekranında parolanı oluştur.

1. public_html/admin-access.css dosyasını cPanel'de aynı konuma yükle.
2. Mevcut mahanpro-private/reviews.php dosyasını yedekle ve paketteki dosyayla değiştir.
3. https://mahanpro.fun/admin adresini aç. Varsayılan kullanıcı adı admin. Daha önce oluşturduğun parola geçerli. Girişten sonra mevcut tam yönetim paneli, İçerik Stüdyosu açılır. Öneri kartlarına panelin İçerik önerileri menüsünden ulaşabilirsin.

Kullanıcı adını değiştirmek istersen özel mahanpro-private/config.php dosyasının return dizisine şu satırı ekle:

    'admin_username' => 'yeni_kullanici',

Ad 3–32 karakter, harf, rakam, nokta, tire veya alt çizgi içerebilir. Büyük ve küçük harfler aynı kabul edilir. Mevcut admin_setup_code, parola ve veritabanı ayarları olduğu gibi kalır. HTTPS ve mevcut güvenlik kontrolleri uygulanmaya devam eder.
