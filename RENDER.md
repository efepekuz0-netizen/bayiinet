# Bayiinet — Render'da saatlik otomasyonun sorunsuz çalışması

Bu belge, **Trendyol gönderimi**, **saatlik fiyat/stok + ürün gönderimi** ve
**saatlik XML yenileme** zincirinin Render'da kesintisiz çalışması için
gereken kurulumu ve bu turda yapılan düzeltmeleri anlatır.

---

## 1. Önce şunu bilin: Render'ın iki farklı dünyası var

| | Ücretsiz plan | Ücretli plan (Starter, ~7$/ay) |
|---|---|---|
| Web servisi | 15 dk **trafik almazsa uyur**, ilk istek ~1 dk'da uyanır | Sürekli açık |
| Background Worker | **Yok** | Var |
| Cron Job | **Yok** | Var |
| Diskte kalıcı veri | Yok (her yeniden başlatmada silinir) | Disk eklenebilir |
| Postgres | **30 gün sonra silinir** | Kalıcı |
| Ek not | Çok fazla dış trafik yapan ücretsiz servis askıya alınabilir (yoğun Trendyol/XML trafiği bu kapsama girer) | — |

Bu yüzden iki kurulum yolu var:

* **Yol A (önerilen):** Web + Worker + Cron (ücretli plan) → depodaki `render.yaml`.
* **Yol B:** Yalnız web servisi (ücretsiz plan) + **harici cron servisi**
  (cron-job.org, UptimeRobot…) → `/cron/...` uçları.

---

## 2. Yol A — Blueprint ile üç servis (önerilen)

`render.yaml` dosyası depodadır. Render'da **New → Blueprint** → bu depoyu seçin.
Üç servis ve bir Postgres oluşur:

| Servis | Görev | Komut |
|---|---|---|
| `bayiinet-web` | Panel + HTTP | Dockerfile CMD → `docker/start.sh` |
| `bayiinet-worker` | Kuyruk işçisi (XML + Trendyol) | `sh docker/worker.sh` |
| `bayiinet-cron` | Zamanlayıcı (her dakika `schedule:run`) | `sh docker/cron.sh` |
| `bayiinet-db` | Postgres | — |

Blueprint'i kullanırken **`plan: starter`** satırlarını ücretsiz planda
`free` yapmayı denemeyin: worker ve cron ücretsiz planda desteklenmez.

Blueprint kullanmıyorsanız servisleri elle açın:

1. **Web Service** → Docker → Dockerfile: `./Dockerfile`.
2. **Background Worker** → Docker → `dockerCommand: sh docker/worker.sh`.
3. **Cron Job** → Docker → `dockerCommand: sh docker/cron.sh`,
   `schedule: * * * * *` (her dakika).
4. **Postgres** oluşturup tüm servislere `DB_CONNECTION=pgsql` ve
   `DB_URL` = Postgres *Internal* connection string verin.

> Not: Web servisinde `RUN_QUEUE_WORKERS=false`, `RUN_SCHEDULER=false`
> verilir; işçi ve zamanlayıcı ayrı servislerde çalışır. Üçünü aynı web
> konteynerinde çalıştırmak da mümkündür (varsayılan davranış) ama her dağıtımda
> işler kesilir.

---

## 3. Yol B — Ücretsiz plan (tek web servisi + harici cron)

Ücretsiz planda worker/cron servisi açamazsınız. Bunun yerine:

1. Web servisini normal kurun (Docker, `docker/start.sh`). Bu betik konteyner
   içinde **kuyruk işçilerini ve zamanlayıcıyı** da başlatır.
2. `CRON_TOKEN` ortam değişkeni ekleyin (örn. `openssl rand -hex 24`).
   Bu anahtar olmadan `/cron/...` uçları **kapalıdır** (503).
3. Ücretsiz bir cron servisinde (ör. cron-job.org) iki görev tanımlayın:

| Sıklık | Adres | Ne yapar |
|---|---|---|
| Her 10 dakika | `https://<servis>.onrender.com/cron/keepalive?token=…` | Servisi uyanık tutar (uyumazsa zamanlayıcı da durur) |
| Saatte 1 | `https://<servis>.onrender.com/cron/hourly?token=…` | XML + Trendyol senkronunu tetikler |
| Her 15 dakika | `https://<servis>.onrender.com/cron/recheck?token=…` | Bekleyen Trendyol batch sonuçlarını toplar |

Ek olarak `queue` görevi, işçinin takıldığı durumlar için kuyruğu boşaltır:

```
https://<servis>.onrender.com/cron/queue?token=…&seconds=90
```

**Neden keepalive?** Servis uyuduğunda konteynerdeki `queue:work` ve
`schedule:work` süreçleri de ölür; uyandığında yeniden başlar. 10 dakikalık
keepalive, servisi (ve dolayısıyla otomasyonu) ayakta tutar.

> Ücretsiz planda uzun XML aktarımları (0,1 CPU) dakikalar sürebilir ve Render
> yoğun dış trafik nedeniyle servisi askıya alabilir. Gerçek üretim için
> ücretli plana geçmeniz önerilir.

---

## 4. Ortam değişkenleri (Environment)

### Zorunlu

| Anahtar | Değer / açıklama |
|---|---|
| `APP_KEY` | **Mutlaka kalıcı olarak verin.** `php artisan key:generate --show` çıktısını yapıştırın. Verilmezse her yeniden başlatmada yeni anahtar üretilir ve **bayilerin Trendyol API şifreleri çözülemez** (gönderim tamamen durur). |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://<servis>.onrender.com` (veya kendi alan adınız) |
| `DB_CONNECTION` | `pgsql` (Render Postgres) |
| `DB_URL` | Postgres *Internal Database URL* |
| `DB_SSLMODE` | `require` |
| `CACHE_STORE` | `database` |
| `SESSION_DRIVER` | `database` |
| `QUEUE_CONNECTION` | `database` |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | İlk yönetici hesabı (ilk açılışta oluşturulur) |

> Render Postgres bağlantı dizesini `DATABASE_URL` adıyla verir; uygulama
> ikisini de okur (`DB_URL` öncelikli). `postgres://...` biçimindeki dizeyi
> yapıştırmanız yeterlidir.

### Otomasyon

| Anahtar | Varsayılan | Açıklama |
|---|---|---|
| `AUTO_XML_SYNC` | `true` | Saatlik XML yenileme |
| `AUTO_TRENDYOL_SYNC` | `true` | Saatlik ürün gönderimi + fiyat/stok |
| `AUTOMATION_XML_INTERVAL` | `55` | XML senkronu en fazla kaç dakika arayla çalışsın |
| `AUTOMATION_TRENDYOL_INTERVAL` | `55` | Trendyol senkronu en fazla kaç dakika arayla çalışsın |
| `AUTOMATION_RECHECK_INTERVAL` | `14` | Batch sonucu kontrol sıklığı (dk) |
| `AUTOMATION_INVENTORY_DELAY` | `15` | Gönderimden kaç dk sonra fiyat/stok eşitlensin |
| `CRON_TOKEN` | (boş) | `/cron/...` uçlarının anahtarı; boşsa uçlar kapalı |
| `RUN_QUEUE_WORKERS` | `true` | Web konteynerinde kuyruk işçileri çalışsın |
| `RUN_SCHEDULER` | `true` | Web konteynerinde `schedule:work` çalışsın |
| `RUN_MIGRATIONS` | `true` | Web açılışında `migrate --force` (worker/cron'da `false`) |
| `TRENDYOL_COMMISSION_RATE` | `15` | Fiyat hesabında komisyon (%) |
| `TRENDYOL_THROTTLE_MS` | `250` | API çağrıları arası bekleme (ms) |
| `TRENDYOL_BATCH_TIMEOUT` | `60` | Paket API çağrılarının zaman aşımı (sn) |
| `XML_STREAM_THRESHOLD` | `8388608` | Bu boyuttan büyük XML'ler akış (XMLReader) ile okunur |

---

## 5. Kurulumdan sonra doğrulama (5 dakika)

1. `https://<servis>.onrender.com/up` → **200** (Render health check).
2. `https://<servis>.onrender.com/health-detail` → `db: ok`.
3. Cron/Shell: `php artisan bayiinet:doctor`
   Tüm satırlar yeşil olmalı. Kritik kontroller:
   * `APP_KEY` → kalıcı ve şifreli alanlar okunabiliyor olmalı.
   * `Kuyruk` → bekleyen iş sayısı ve en eski işin yaşı (20 dk'dan eski iş varsa işçi çalışmıyor).
   * `Zamanlayıcı` → son nabız.
4. Panelde **Yönetim → Otomasyon**: "Zamanlayıcı: Çalışıyor" ve görevlerin son
   çalışma zamanı görünmeli.
5. **Şimdi XML çek** ve **Şimdi Trendyol senkronu** düğmeleriyle elle tetikleyip
   sonucu aynı ekrandan izleyin.
6. Bir bayiye ürün gönderip **Durum** kutusunda "gönderildi / doğrulama
   bekliyor / oluştu" sayaçlarının ilerlediğini doğrulayın.

---

## 6. Sık görülen sorunlar ve çözümü

| Belirti | Neden | Çözüm |
|---|---|---|
| "Hata: API anahtarı ve sırrı kaydedilmemiş" / gönderim aniden durdu | `APP_KEY` değişti, şifreli alanlar çözülemiyor | `php artisan bayiinet:doctor` ile kontrol edin; `APP_KEY`'i Render'da kalıcı verin ve bayi API bilgilerini yeniden kaydedin |
| Gönderim başlıyor ama ürünler oluşmuyor, kuyruk sürekli büyüyor | `queue:work` çalışmıyor | Worker servisini (`docker/worker.sh`) açın ya da `/cron/queue?token=…` çağırın; panelde kuyruk sayacına bakın |
| Saatlik görevler saat başında çalışmıyor | Servis uyudu / `schedule:work` yok | Keepalive + `/cron/hourly`; ücretli planda Cron Job (`docker/cron.sh`) |
| "Zamanlayıcıdan nabız alınmadı" uyarısı | Zamanlayıcı süreci öldü | Web'i yeniden başlatın; worker/cron servislerinde `docker/cron.sh` kullanın |
| Her saat aynı ürünler için "barkod zaten mevcut" hatası | Ürün Trendyol'da var ama kayıt "hata" görünüyordu | Bu sürümde otomatik düzeltilir: ilan `created` işaretlenir ve fiyat/stok güncellemesine geçilir |
| İşler 16 dakika boyunca hiç işlenmiyor (dağıtım sonrası) | Ölen konteynerin "reserved" işleri `retry_after=960 sn` sonra geri gelir | Normal davranış; `docker/worker.sh` işçiyi ayrı serviste tuttuğunda yaşanmaz |
| `Allowed memory size exhausted` (XML) | 8 MB üzeri feed'lerde SimpleXML | Bu sürümde akış okuması devrede; hata sürerse `XML_STREAM_THRESHOLD` değerini düşürün (örn. `1048576`) |
| XML indirme `HTTP 301/302` | Feed başka adrese yönlendiriyor | Bu sürümde en fazla 5 yönlendirme izlenir |
| Postgres 30 gün sonra kayboldu | Ücretsiz Render Postgres 30 günde bir silinir | Ücretli veritabanına geçin veya harici MySQL kullanın |
| Render servisi askıya alındı | Ücretsiz planda yüksek dış trafik limiti | Ücretli plana geçin; senkron sıklığını azaltın |

---

## 7. Saatlik akış (bu sürümdeki davranış)

```
her dakika   schedule:run  (schedule:work veya Render Cron Job)
 ├─ nabız (AutomationStatus)
 ├─ XML: son çalışmadan 55+ dk geçtiyse  → ImportSourceJob (kuyruk: default)
 ├─ Trendyol: 55+ dk geçtiyse
 │     ├─ SendDealerTrendyolRun  → yeni ürünler (100'lük paket), fiyat/stok
 │     └─ 15 dk sonra SyncDealerTrendyolInventory (kuyruk: marketplace)
 └─ 14+ dk geçtiyse → bayiinet:recheck-trendyol (bekleyen batch sonuçları)

kuyruk işçisi → marketplaces + default kuyruklarını işler
```

* "Saat başı" yerine **"son çalışmadan bu yana 55 dakika"** kuralı kullanılır:
  sunucu saat başında uykuda olsa bile görev en fazla birkaç dakika gecikmeyle
  telafi edilir (`withoutOverlapping` çift çalışmayı engeller).
* Trendyol'da zaten var olan barkodlar yeniden gönderilmez; fiyat/stok
  güncellemesine geçilir.
* Yarım kalan (konteyner yeniden başlaması) gönderimler 45 dakika sonra
  otomatik olarak yeniden başlatılır.

---

## 8. Bu turda değişen dosyalar

| Dosya | Değişiklik |
|---|---|
| `docker/start.sh` | Kuyruk **silinmiyor**; APP_KEY kalıcılığı; ayrı XML/Trendyol işçileri; yeniden başlayan zamanlayıcı |
| `docker/worker.sh`, `docker/cron.sh` | Render Worker/Cron servisleri için yeni giriş betikleri |
| `render.yaml` | Web + Worker + Cron + Postgres blueprint'i |
| `routes/console.php` | Saatlik görevler "telafi" (catch-up) mantığına geçti |
| `app/Services/AutomationStatus.php` | `markAttempt`, `dueFor` (telafi kontrolü) |
| `app/Services/XmlImportService.php` | Bellek dostu indirme (`sink`) + büyük dosyalar için `XMLReader` akış okuması + sınırlı yönlendirme |
| `app/Services/DealerTrendyolService.php` | "Barkod zaten mevcut" kurtarma, `created` dışındakilere fiyat gönderilmez, API throttle, komisyon ayarı |
| `app/Services/TrendyolSendProgress.php` | `isStale` — yarım kalan gönderim 45 dk sonra devam eder |
| `app/Jobs/ImportSourceJob.php` | 2 deneme + backoff |
| `app/Jobs/SendDealerTrendyolRun.php` | Varyant bazlı "tamamı gönderildi" kontrolü, otomatik tur bilgisi |
| `app/Jobs/SyncDealerTrendyolInventory.php` | Tekilleştirme (unique), hata sayacı |
| `app/Console/Commands/*` | `bayiinet:recheck-trendyol`, `bayiinet:doctor` eklendi |
| `app/Http/Controllers/CronController.php`, `routes/web.php` | Token korumalı `/cron/...` uçları |
| `config/bayiinet.php`, `config/database.php`, `.env.example` | Yeni ayarlar, `DATABASE_URL` desteği |

Sorun devam ederse `php artisan bayiinet:doctor` çıktısını ve
**Yönetim → Otomasyon** ekranındaki sonuçları paylaşın.
