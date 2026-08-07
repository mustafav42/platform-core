# Sprint-006 Test Checklist — Admin Logout

## Test

1. Yönetici PIN ile `/admin` üzerinden giriş yap.
2. Enterprise / ana yönetim panelini aç.
3. Sağ üst profil menüsünden **Çıkış** seç.
4. 404 oluşmadığını doğrula.
5. Tarayıcının `/admin/` PIN giriş ekranına döndüğünü doğrula.
6. Tarayıcı geri tuşuna basıp korumalı Enterprise sayfasına dönmeyi dene.
7. Oturum kapandığı için tekrar `/admin/` giriş ekranına yönlendirildiğini doğrula.

## Beklenen Sonuç

- `/admin/logout.php` 404 vermez.
- Aktif session tamamen sonlandırılır.
- Session cookie süresi sonlandırılır.
- Kullanıcı `/admin/` giriş ekranına yönlendirilir.
