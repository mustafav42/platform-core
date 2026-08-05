# 11_REPOSITORY_WORKFLOW

## Çalışma Modeli

DOA her sprint için yalnızca değişen veya yeni dosyaları içeren ZIP paketi hazırlar.

Founder:

1. Paketi açar.
2. İçeriği repository köküne kopyalar.
3. GitHub Desktop'ta değişiklikleri inceler.
4. Önerilen commit mesajıyla commit yapar.
5. Push origin ile GitHub'a gönderir.

## Güvenlik Kuralları

- Mevcut dosyalar kullanıcı onayı olmadan ezilmez.
- Paketler varsayılan olarak yalnızca yeni dosyalar içerir.
- Güncellenecek mevcut dosyalar ayrıca belirtilir.
- Her commit öncesi GitHub Desktop değişiklik listesi kontrol edilir.
- Repository projenin tek doğruluk kaynağıdır.

## Platformlar

- Mac ve Windows aynı repository üzerinden çalışabilir.
- Bilgisayar değiştirildiğinde GitHub Desktop ile repository yeniden clone edilir.
- Yerel değişiklikler push edilmeden bilgisayar değiştirilmez.
