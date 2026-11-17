# Mahanpro canlı klan sorgusu

Bu paket, mevcut Mahanpro PHP/MySQL kurulumunu günceller. cPanel Dosya Yöneticisi'nde mevcut dosyaların yedeğini aldıktan sonra aşağıdaki dosyaları aynı yerlere koy:

1. mahanpro-private/clan.php
2. public_html/clan-live.js
3. public_html/index.php
4. mahanpro-private/templates/clan.html

Mevcut mahanpro-private/config.php dosyasındaki return dizisine şu satırı ekle. Örnekteki yer tutucunun yerine Supercell API anahtarını yaz:

    'clash_api_token' => 'ANAHTARINI_BURAYA_YAPISTIR',

Dosya web kökü dışında kalmalı. Anahtarı tarayıcıya, GitHub'a veya sohbet mesajına koyma. PHP cURL uzantısı etkin olmalı. Supercell panelindeki izinli IP, PHP isteklerinin internete çıktığı sabit IP olmalı.

https://mahanpro.fun/clan sayfasında bildiğin gerçek bir klan etiketi sorgula. Klan adı, seviyesi, üye sayısı ve puanı görünmeli. API çalışmasa da oyun içi profil bağlantısı kullanılabilir.

Canlı erişim hatasında tarayıcının ağ sekmesinde /api/clan yanıtına bak. not_configured için config.php ve cURL uzantısını, api_access için anahtarı ve PHP çıkış IP'sini kontrol et. Bu güncelleme veritabanı yapısını değiştirmez ve SQL çalıştırmayı gerektirmez.
