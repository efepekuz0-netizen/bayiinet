# Bayiinet — Render kurulumu

> **Saatlik otomasyonun (XML + Trendyol) sorunsuz çalışması için ayrıntılı
> rehber: [`RENDER.md`](RENDER.md).** Bu dosya kısa özet niteliğindedir.

## 1. Dosyaları yükle
Bu depoyu GitHub'a yükleyin. `vendor/`, `.env`, `database/*.sqlite` sürüm
kontrolüne **dahil değildir** (`.gitignore`).

## 2. Servisler

Depodaki `render.yaml` ile **New → Blueprint** diyerek üç servisi birden
oluşturabilirsiniz (ücretli plan): web + background worker + cron job.

Elle kurulum:

| Servis | Tür | Komut |
|---|---|---|
| Web | Web Service (Docker) | `sh docker/start.sh` (Dockerfile varsayılanı) |
| Worker | Background Worker (Docker) | `dockerCommand: sh docker/worker.sh` |
| Cron | Cron Job (Docker) | `dockerCommand: sh docker/cron.sh`, `* * * * *` |

> Web servisi de açılışta kuyruk işçilerini ve zamanlayıcıyı çalıştırır
> (`docker/start.sh`), bu yüzden **ücretsiz planda tek servis yeterlidir** —
> ancak servis 15 dakika trafik almazsa uykuya geçer. O durumda harici bir cron
> servisiyle `/cron/keepalive?token=…` ve `/cron/hourly?token=…` çağırın
> (bkz. `RENDER.md` bölüm 3).

`php artisan serve` üretim için ideal değildir; imkânınız varsa nginx/php-fpm
veya FrankenPHP + Octane'e geçin.

## 3. Environment (ortam değişkenleri)

| Anahtar | Değer |
|---|---|
| `APP_KEY` | **Kalıcı olmalı.** `php artisan key:generate --show` çıktısı. Boş bırakılırsa her yeniden başlatmada değişir ve bayilerin Trendyol şifreleri çözülemez. |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://www.bayiinet.com` |
| `DB_CONNECTION` | `pgsql` (Render Postgres) veya `mysql` |
| `DB_URL` | Render'ın verdiği bağlantı dizesi (`DATABASE_URL` da okunur) |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Bağlantı dizesi kullanmıyorsanız |
| `ADMIN_EMAIL` | yönetici e-posta adresiniz |
| `ADMIN_PASSWORD` | en az 8 karakterli güçlü şifre |
| `SESSION_SECURE_COOKIE` | `true` (HTTPS arkasında) |
| `CRON_TOKEN` | Harici cron uçları için gizli anahtar (`openssl rand -hex 24`) |
| `AUTO_XML_SYNC` | saatlik XML senkronu — varsayılan `true` |
| `AUTO_TRENDYOL_SYNC` | saatlik Trendyol senkronu — varsayılan `true` |
| `RUN_QUEUE_WORKERS` / `RUN_SCHEDULER` | Web konteynerinde işçi/zamanlayıcı çalışsın mı (ayrı worker+cron kullanıyorsanız `false`) |
| `TRENDYOL_FALLBACK_BRAND_ID` | marka bulunamazsa kullanılacak Trendyol marka ID — varsayılan `2613880` |
| `TRENDYOL_COMMISSION_RATE` | bayi fiyatı hesabında komisyon (%) — varsayılan `15` |

APP_KEY üretmek için (PowerShell):

    "base64:" + [Convert]::ToBase64String((1..32 | % { Get-Random -Maximum 256 }))

Uzak MySQL sunucunuz Render'ın IP'lerine bağlantı izni vermelidir.

## 4. İlk açılış

Açılışta tablolar otomatik oluşturulur (`php artisan migrate --force`) ve
`ADMIN_EMAIL` / `ADMIN_PASSWORD` ile yönetici hesabı açılır.
Giriş adresi: `/giris`. (Demo şifreli test hesapları oluşturulmaz.)

## 5. Otomasyonun çalıştığını doğrulama

Tek komutla tanı:

    php artisan bayiinet:doctor

**Yönetim → Otomasyon** ekranında:

- *Zamanlayıcı: Çalışıyor* yazıyorsa `schedule:work` (veya cron) ayakta demektir.
- Her görevin son çalışma zamanı ve sonucu (başarılı / uyarı / hata) listelenir.
- "Şimdi XML çek" / "Şimdi Trendyol senkronu" düğmeleri ile zamanlayıcıyı
  beklemeden deneyebilirsiniz.

Kuyrukta bekleyen iş sayısı sürekli büyüyorsa `queue:work` süreci çalışmıyor
demektir (`/cron/queue?token=…` geçici çözümdür).

## 6. Bayiye Trendyol'dan ürün gönderme

Yönetim → Bayiler → bayi seç → **Trendyol'a Ürün Gönder**:

1. Bayinin Trendyol satıcı numarası, API Key ve API Secret bilgilerini girin
   ("Bağlantıyı test et" ile doğrulayın).
2. Ürünleri, Trendyol kategorisi ve markasıyla seçip gönderin. Gönderim
   arka planda sürer; ilerleme aynı sayfadaki durum kutusunda görünür.
3. "Sonuç sorgula" ile Trendyol'un batch yanıtını okuyun; okunamayan batch'ler
   15 dakikada bir otomatik olarak yeniden kontrol edilir.
4. Saatlik otomasyon açıkken yeni ürünler her turda otomatik gönderilir;
   Trendyol'da zaten oluşmuş ilanlara yeniden "ürün oluştur" gönderilmez,
   yalnızca fiyat/stok tazelenir.

## 7. Güvenlik — ilk kurulumda yapılacaklar

Depoya geçmişte işlenmiş gerçek veriler (SQLite veritabanı) nedeniyle:

1. **Trendyol API anahtarını ve yönetici parolalarını yenileyin (rotate).**
2. Depo geçmişini temizleyin (BFG veya `git filter-repo` ile
   `database/database.sqlite` dosyasını geçmişten çıkarın) ve uzaktaki depoyu
   yeniden push edin.
3. `APP_KEY` değerini yeni bir değerle değiştirip anahtarları yeniden girin.
