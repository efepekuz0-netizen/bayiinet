# Bayiinet — Render kurulumu

## 1. Dosyaları yükle
Bu paketteki dosyaları GitHub deponuza **aynı klasör yollarıyla** yükleyin (var olanların üzerine yazın).
Önceki "bayiinet-duzeltme.zip" dosyasını **kullanmayın**.

## 2. Render'da Web Service oluştur
New → Web Service → depoyu seçin → Runtime: **Docker**.

## 3. Environment (ortam değişkenleri)

| Anahtar | Değer |
|---|---|
| `APP_KEY` | `base64:` ile başlayan 32 baytlık anahtar (aşağıya bakın) |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://www.bayiinet.com` |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | MySQL sunucu adresi |
| `DB_PORT` | `3306` |
| `DB_DATABASE` | veritabanı adı |
| `DB_USERNAME` | kullanıcı adı |
| `DB_PASSWORD` | şifre |
| `ADMIN_EMAIL` | yönetici e-posta adresiniz |
| `ADMIN_PASSWORD` | en az 8 karakterli güçlü şifre |

APP_KEY üretmek için (Windows PowerShell):

    "base64:" + [Convert]::ToBase64String((1..32 | % { Get-Random -Maximum 256 }))

Uzak MySQL sunucunuz Render'ın IP'lerine bağlantı izni vermelidir.

## 4. Giriş
Açılışta tablolar otomatik oluşturulur ve ADMIN_EMAIL / ADMIN_PASSWORD ile yönetici hesabı açılır.
Giriş adresi: `/giris`. (Demo şifreli test hesapları oluşturulmaz.)

## 5. Bayiye Trendyol'dan ürün gönderme
Yönetim → Bayiler → bayi seç → **Trendyol'a Ürün Gönder**:
1. Bayinin Trendyol satıcı numarası, API Key ve API Secret bilgilerini girin.
2. Ürünleri, Trendyol kategori ve marka numarasıyla seçip gönderin.
3. Birkaç dakika sonra "Sonuç sorgula" ile Trendyol'un yanıtını görün.
