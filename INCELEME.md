# Bayiinet — Kapsamlı Kod İncelemesi

> ## ✅ Durum — 2026-10-09 (düzeltme sonrası)
>
> Aşağıdaki bulguların tamamı **kodda düzeltildi**. Kalan yalnızca sizin
> tarafınızda yapılacak işlemler (sır temizliği, altyapı) "Bekleyen işler"
> bölümünde listelenmiştir.
>
> | Bulgu | Durum | Ne yapıldı |
> |---|---|---|
> | **K1** SQLite + sırlar depoda | ⚠️ Kısmen | `database/database.sqlite` depodan silindi ve `.gitignore`'a alındı. **Geçmiş temizliği + API anahtarı/parola rotasyonu sizde.** |
> | **K2** `.gitignore` yok | ✅ | Eklendi; ayrıca `.env.example` ve `config/bayiinet.php` |
> | **K3** Giriş/kayıt hız sınırlaması yok | ✅ | `throttle:5,1` giriş, `throttle:3,1` kayıt; şifre kuralı tek merkez (`Password::min(8)`) |
> | **K4** XML feed'de çift escaping | ✅ | Feed artık elle kaçırma yapmıyor; geçersiz karakterler temizleniyor |
> | **K5** Varyant fiyatı yazılmıyor | ✅ | `product_variants` tablosuna `variant_price` / `variant_stock` / `variant_images` eklendi; feed ve Trendyol senkronu bu alanı kullanıyor |
> | **Y1** Feed'de N+1 + bellek | ✅ | `chunkById` ile üretim, `effective_stock` yüklü ilişkiyi kullanıyor |
> | **Y2** Varyantlar silinip yeniden yazılıyor | ✅ | Varyantlar artık barkod/sku/ad+değer ile **upsert** ediliyor; ID'ler korunuyor, listing bağlantısı kopmuyor; `sku`/`variant_price`/`price_diff` parse ediliyor |
> | **Y3** Fiyatlama ürün başına | ✅ | `PricingService::recalculateImported()` — toplu CASE'li SQL |
> | **Y4** Kategori eşleştirmede ürün başına yazma | ✅ | Eşleşmeler bellekte toplanıp iş sonunda yazılıyor; kategori ağacı bir kez indeksleniyor |
> | **Y5** Trendyol'da N+1 + senkron bekleme | ✅ | Tek `whereIn` ile listing sorgusu; batch doğrulaması en fazla ~9 sn bekler, kalanı `VerifyTrendyolBatch` işine devredilir |
> | **Y6** `default_profit_margin` yazılmıyor | ✅ | Tek anahtar: `profit_margin`. Ayarlar ekranına eklendi, `PricingService::profitMargin()` eski anahtarları da okur |
> | **Y7** `orders.total_amount` ölü kolon | ✅ | Modelden ve görünümden kaldırıldı (`notes`, `XmlImport`/`DealerAnnouncement`/`BlacklistEntry` ölü alanları da) |
> | **O1** `env()` kullanımı | ✅ | `EnsureAdminUser` artık `config('bayiinet.admin.*')` okuyor |
> | **O2** Kuyruk/zamanlayıcı aynı konteynerde | ⚠️ Kısmen | `start.sh` worker'ı `--timeout=900` (retry_after 960'tan küçük) + `--tries=3` ile çalıştırıyor. **Ayrı Background Worker servisi önerilir** (bkz. `KURULUM.md`) |
> | **O3** Üretimde `artisan serve` | 📄 Belge | `KURULUM.md`'ye nginx/php-fpm & FrankenPHP önerisi eklendi |
> | **O4** Cache anahtarı uyuşmazlığı | ✅ | `home_main_categories_v2` her yerde |
> | **O5** Hata durumunda DB'ye tekrar soruluyor | ✅ | Fallback sayfalayıcı tamamen bellekte |
> | **O6** `ShouldBeUnique` kilidine elle müdahale | ✅ | `uniqueFor` 600 sn; asıl koruma `TrendyolSendProgress` üzerinden |
> | **O7** İptal tüm kuyruğu siliyor | ✅ | İptal bayi bazlı; batch işleri bayrağı görüp kendini atlıyor |
> | **O8** `composer.lock` yok | 📄 Belge | Composer sandbox'ta kurulu olmadığı için üretilemedi — yerelde `composer install` sonrası commit edin |
> | **O9** Test altyapısı sıfır | ⏳ | Eklenmedi (bu turda kapsam dışı) |
> | **O10** Ölü model alanları | ✅ | Tespit edilen tüm ölü kolonlar temizlendi |
> | **O11** Dokümantasyon/seeder çelişkileri | ✅ | `README.md` (eski adıyla `README-BAYIXML.md`) ve `KURULUM.md` güncellendi; `AGENTS.md` projeye özel hâle getirildi; seeder `profit_margin` yazıyor |
> | **O12** Ölü iskelet dosyası | ✅ | `welcome.blade.php`, `resources/css`, `resources/js` silindi |
> | **O13** Oturum çerezi `secure` değil | ✅ | Varsayılan `true` (`SESSION_SECURE_COOKIE` ile kapatılabilir) |
> | **O14** Denetim izi yok | ✅ | `AdminAudit` servisi: fiyat değişikliği, bayi onay/askı/bakiye, Trendyol gönderim & toplu silme, ayarlar kayda geçiyor |
> | **O15** Şifre kuralları tutarsız | ✅ | `AppServiceProvider` içinde tek `Password::defaults()` |
> | **O16** Trendyol API'sinde retry yok | ✅ | `Http::retry(3, 1500ms, ConnectionException)`; DELETE artık gövde gönderiyor |
>
> ### Trendyol ürün gönderimi — kök nedenler
> 1. **Sayaçlar yanlış hesaplanıyordu:** Trendyol ürünü kabul ettiği hâlde
>    "0 gönderildi" görünüyordu. Artık tüm sayaçlar batch kayıtlarından
>    türetiliyor ve tekrar hesaplanabilir (`TrendyolSendProgress`).
> 2. **Kategori/özellik hatası tüm ürünleri blokluyordu:** Trendyol kategori
>    özellik servisinden hata dönerse her ürün "hazırlanamadı" sayılıyordu.
>    Artık boş özellik listesiyle devam ediyor ve hatayı raporluyor.
> 3. **Marka araması ürün başına yapılıyordu:** benzersiz marka adları bir kez
>    çözülüyor.
> 4. **Barkod 40 karakter sınırını aşıyordu:** Trendyol'un reddettiği
>    gönderimlerin bir kısmı bu yüzden başarısızdı.
> 5. **Batch doğrulaması ~24 sn blokluyordu:** 15.000 üründe işler saatler
>    sürüyordu. Artık en fazla ~9 sn bekleniyor, kalanı arka planda.
>
> ### Saatlik otomasyon
> `routes/console.php` içinde: saatlik XML yenileme, saatlik Trendyol senkronu,
> 15 dakikada bir batch sonucu kontrolü ve 5 dakikada bir zamanlayıcı nabzı.
> Her çalışma `AutomationStatus`'a yazılır ve **Yönetim → Otomasyon**
> ekranından izlenir.
>
> ### Bekleyen işler (sizde)
> 1. **Trendyol API anahtarını ve yönetici parolalarını yenileyin** (K1).
> 2. Depo geçmişini temizleyin: `git filter-repo --path database/database.sqlite --invert-paths`.
> 3. `composer.lock` dosyasını commit edin.
> 4. Render'da ikinci bir **Background Worker** servisi açın.
>
> ---

---

## 0. Genel tablo (orijinal rapor)


---

## 0. Genel tablo

Proje **Laravel 12/13 tabanlı bir XML bayilik (dropship) platformu**: tedarikçi XML'lerini içe aktarıyor, kâr marjı uygulayıp bayilere özel XML feed sunuyor, bayilerin kendi Trendyol mağazalarına ürün/fiyat/stok gönderiyor ve bakiye ile çalışan bir sipariş akışı yönetiyor.

| Katman | Dosya | Durum |
|---|---|---|
| Rotalar | 1 (`routes/web.php`, ~140 rota) | İyi organize, admin/bayi ayrımı net |
| Controller | 13 | Genel olarak temiz; 3 tanesi "şişman" (Operations, Marketplace, DealerTrendyol) |
| Servis | 7 | `DealerTrendyolService` 1018 satır — tek başına Trendyol gönderim, batch doğrulama, özellik çıkarımı, kategori eşleme, silme, stok eşitleme işlerini yapıyor |
| Job | 6 | Kuyruk mimarisi mantıklı (orchestrator + batch + verify) |
| Model | 17 | Sade, cast'ler doğru; ölü kolonlar var |
| Migration | 23 | Geriye dönük uyumlu ama 5 tanesi "yama" niteliğinde |
| Test | **yok** | `tests/` klasörü, `phpunit.xml` yok |
| Frontend | 43 Blade (CDN Bootstrap) | Sade, mobil uyumlu; XSS riski düşük (hiç `{!! !!}` yok) |

**İyi yapılanlar** (koruyun):
- Bakiye, stok ve sipariş işlemleri `DB::transaction` + `lockForUpdate` ile yarış durumuna karşı korunmuş (`DealerController::addBalance`, `Dealer\OrderController::store`, `Admin\OrderController::updateStatus`).
- Trendyol API anahtarları ve pazaryeri kimlik bilgileri `encrypted:array` cast ile şifreli saklanıyor.
- XML kaynak URL'i için SSRF koruması yazılmış (`XmlImportService::isPublicHttpUrl`: özel IP/loopback/localhost engeli, yönlendirme kapalı).
- Sipariş durum geçişleri bir durum makinesiyle sınırlanmış; iade/stok geri alma tek transaction içinde.
- Trendyol gönderimi kuyruk + `ShouldBeUnique` + iptal edilebilir durum önbelleği ile kurgulanmış.
- Trendyol fiyat motoru (`TrendyolPriceCalculator`) kargo ücretini fiyata göre iteratif çözüyor — doğru yaklaşım.

---

## 🔴 Kritik bulgular

### K1. 35 MB SQLite veritabanı Git'e commit edilmiş
`database/database.sqlite` depoda takipli. İçeriği incelendi:

| Tablo | Kayıt |
|---|---|
| `products` | **14.972** (gerçek tedarikçi verisi: `teknodayim.com` XML'i) |
| `users` | 3 → `admin@bayixml.test`, `bayi@bayixml.test`, `admin@test.com` |
| `marketplace_connections` | 1 → Trendyol mağaza no `1312765`, **şifreli API key + secret** |
| `sessions` | 2 aktif oturum |
| `sources` | 1 (gerçek tedarikçi feed URL'i) |

Sorunlar: (a) gerçek veri + şifreli API sırları herkese açık depoda; (b) `.pack` 5,9 MB, her klon 35 MB çekiyor; (c) bu dosya **yarı-migrasyonlu**: 23 migration dosyasından yalnızca **18'i** bu veritabanında çalışmış.

> Sonuç olarak `products` tablosunda `cost_price`, `sell_price`, `xml_margin_percent`, `is_featured`, `show_on_homepage` **yok**; `dealers` tablosunda `trendyol_*`, `suspended_at`, `tax_document_path` **yok**.

Kodun içindeki `Schema::hasColumn(...)` kontrolleri (`DealerController:54,58,82`), `HomeController::hasProductColumn()` ve `2026_10_09_000001_fix_dealer_columns` migration'ı **bu bozuk veritabanının semptomlarını bypass ediyor**; kök neden (migrations'ların hepsinin çalışmaması) tedavi edilmemiş.

**Öneri:**
1. `database/*.sqlite` → `.gitignore`, dosyayı depodan silin, geçmişi temizleyin (`git filter-repo` veya BFG).
2. Trendyol API anahtarını ve admin parolalarını **rotate edin** (şifreli olsa da APP_KEY sızarsa çözülür).
3. Tüm migration'ları tek, doğrusal, idempotent bir şemada toplayın; sonra tüm `Schema::hasColumn` savunmalarını ve `hasProductColumn()` cache'ini kaldırın.

### K2. `.gitignore` yok
`vendor/`, `.env`, `storage/`, `bootstrap/cache/`, `.phpunit.cache/`, `database/*.sqlite` korumasız. Yerelde `composer install` yapan biri istemeden `vendor/`'ı commit edebilir. Ayrıca eksik: `.env.example`, `composer.lock`, `phpunit.xml`, `tests/`, `package.json`.

### K3. Giriş ve kayıt için hız sınırlaması yok
`AuthController::login` / `register` üzerinde `throttle` yok; depoda hiçbir yerde `RateLimiter`/`throttle` kullanılmamış. Laravel'in `web` grubu varsayılan olarak throttle içermez.
→ Admin paneline yönelik **sınırsız brute-force** ve sınırsız bayi kaydı (spam) mümkün. Ayrıca kayıt şifresi `min:6` (`AuthController:66`) — KURULUM'da "en az 8 karakter" deniyor, çelişki var.

**Öneri:** `Route::middleware('throttle:5,1')` login'e, `throttle:3,1` kayda; tek bir `Password::defaults()` kuralı her yerde.

### K4. XML feed'de çift escaping — çıktı bozuk
`app/Http/Controllers/XmlFeedController.php:77-105` her metni önce `htmlspecialchars()` ile kodlayıp sonra `SimpleXMLElement::addChild()`'a veriyor. `addChild` değeri **tekrar** escape eder:

```
"A & B" → htmlspecialchars → "A &amp; B" → addChild → "A &amp;amp; B"
```

Başlığında/açıklamasında `&`, `<`, `>`, `"` olan ürünlerde feed bozuk çıkıyor. **Düzeltme:** `addChild`'a ham değeri verin (kendisi escape eder). Aynı mesele varyant düğümlerinde (115-121) de var.

### K5. Varyant fiyatı XML'e hiç yazılmıyor
`XmlFeedController.php:119-121` `$variant->price` okuyor. Ancak `ProductVariant` modelinde ve `product_variants` tablosunda **`price` kolonu yok** (`variant_price`, `price_diff` var). `isset()` kontrolü daima `false` → varyantlı ürünlerin XML'inde fiyat hiç görünmüyor. `variant_price` kullanılmalı.

---

## 🟠 Yüksek önemli bulgular

### Y1. XML feed üretiminde N+1 ve bellek patlaması
`XmlFeedController::generateXml()`:
- `Product::with('variants')->get()` tüm kataloğu (≈15.000 ürün + varyant + `images` JSON) tek seferde belleğe alıyor.
- Ardından her üründe `$product->effective_stock` çağrılıyor. `Product::getEffectiveStockAttribute()` `variants()->sum('stock')` yapıyor — bu **ilişkiyi kullanmaz, yeni bir sorgu üretir**. `with('variants')` boşa gidiyor: **ürün başına +1 sorgu (≈15.000)**, her 5 dakikada bir.

**Öneri:** accessor'ı yüklenmiş ilişkiyi kullanacak şekilde değiştirin (`$this->variants->sum('stock')`), feed'i `chunkById` ile üretip `response()->stream()` ile akıtın.

### Y2. Her XML içe aktarımında tüm varyantlar silinip yeniden yaratılıyor
`XmlImportService::upsertProductBatch():434-455` → `ProductVariant::whereIn('product_id', …)->delete()` + `insert()`.
Sonuçlar:
- `dealer_trendyol_listings.product_variant_id` FK'sı `nullOnDelete` → **saatlik XML yenilemesinde tüm Trendyol listing'lerinin varyant bağlantısı kopuyor**, varyant ID'leri sürekli değişiyor.
- `SyncTrendyolCatalog` varyant bazlı eşleştirmesi ID üzerinden değil barkod üzerinden yürüdüğü için ayakta kalıyor, ama yerel takip bozuluyor.
- Ayrıca XML'den `variant_price`, `sku`, `price_diff` **hiç parse edilmiyor** (only `barcode/name/value/color/stock`) → varyant fiyat farkı kalıcı olarak 0.

**Öneri:** varyantları silmek yerine `upsert` edin (barkod/sku üzerinden), `variant_price`/`sku`/`price_diff` eşlemesini ekleyin.

### Y3. Fiyatlandırma ürün başına, tek HTTP isteğinde
`XmlImportService::upsertProductBatch():458-460`:
```php
Product::whereIn('id', $productIds)->each(fn ($product) => $pricing->applyToProduct($product));
```
`applyToProduct` ürün başına ~3 sorgu çalıştırıyor (source çekme + save). 15.000 ürün → **≈45.000 sorgu tek istekte** (`POST /admin/kaynaklar/{id}/guncelle` ve `POST /admin/urunler/cek`). Zaman aşımı/502 kaçınılmaz.
**Öneri:** `PricingService::bulkApplyXmlMargin()`'deki CASE'li toplu `UPDATE` yaklaşımını buraya taşıyın (aynı formül, tek SQL).

### Y4. Kategori eşleştirmede ürün başına veritabanı yazma
`TrendyolCategoryMatcher::match()` her eşleşmede `remember()` → `PlatformSetting::read()` + `write()` yapıyor (kilitsiz read-modify-write). 15.000 ürünlük bir gönderimde **15.000×(1 select + 1 upsert)** ve `by_xml` haritası sınırsız büyüyor.
**Öneri:** eşleşmeleri bellekte toplayıp job sonunda tek seferde yazın.

### Y5. Trendyol gönderiminde N+1 + senkron bekleme
- `DealerTrendyolService::send():158-161` her varyant için ayrı `DealerTrendyolListing` sorgusu → N+1. Tek `whereIn('barcode')` ile çekilmeli.
- `waitAndApplyBatchResult():821-852` batch başına **8 × 3 sn ≈ 24 sn senkron uyuyor**. 15.000 ürün / 40'lık batch = 375 batch × 24 sn ≈ **2,5 saat tek worker**. Kuyruk zaten var (`VerifyTrendyolBatch`) — bekleme döngüsü 1-2 denemeye indirilip kalanı arka plana bırakılmalı.

### Y6. `default_profit_margin` ayarı hiç yazılıyor
- Okuyanlar: `Admin\DashboardController:19`, `Dealer\ProductController:33`
- Yazan: **hiç kimse.** Admin paneli `xml_margin_percent`, `min_margin_percent`, `default_marketplace_margin` yazıyor (`OperationsController::updatePricing/updateSettings`); `DatabaseSeeder` ise `default_margin_percent` (üçüncü bir isim!) yazıyor.

Sonuç: **admin panelindeki "tahmini kazanç" hep 0**, **bayi katalog sayfasındaki kâr oranı hep 0**.
**Öneri:** tek bir isimlendirme (`profit_margin`), yazılmayan anahtarı ya ayarlar ekranına ekleyin ya da okumaları kaldırın.

### Y7. `orders.total_amount` ölü kolon
`Order::$fillable` ve `$casts` içinde var, **migration'da karşılığı yok** ve hiçbir yerde yazılmıyor. `resources/views/dealer/orders/show.blade.php:28` `$order->total ?? $order->total_amount` diyor — `total` her zaman dolu olduğu için şans eseri çalışıyor. Modelden kaldırılmalı.

---

## 🟡 Orta önemli bulgular

**O1 — `env()` kullanımı.** `EnsureAdminUser:16-17` `env('ADMIN_EMAIL')` okuyor. `php artisan config:cache` uygulanırsa `env()` config dışında `null` döner ve **yönetici hesabı sessizce oluşmaz**. `config/app.php`'ye taşıyıp `config()` ile okuyun.

**O2 — Kuyruk ve zamanlayıcı web konteynerinde.** `docker/start.sh`, `queue:work` + `schedule:work` + `artisan serve`'i aynı konteynerde arka planda çalıştırıyor. Yeniden başlatmada/dağıtımda işler kaybolabilir, bağımsız ölçekleme yok. Render'da ayrı **Background Worker** servisi önerilir. Ayrıca `--timeout=960` ile `retry_after=960` eşit — Laravel timeout'un `retry_after`'dan **küçük** olmasını ister, aksi halde aynı iş iki worker'a düşebilir.

**O3 — Üretimde `php artisan serve`.** PHP'nin gömülü geliştirme sunucusu üretim için anti-pattern: tek iş parçacığı, zayıf statik dosya/sunucu davranışı, TLS yok. nginx/php-fpm veya FrankenPHP + Octane'e geçin.

**O4 — Cache anahtarı uyuşmazlığı.** `HomeController` kategorileri `home_main_categories_v2` anahtarıyla önbelleğe alıyor; `XmlImportService:137` import sonrası `home_main_categories` (v1!) anahtarını siliyor. → Yeni kategoriler importtan sonra 10 dk görünmüyor ve o `Cache::forget` ölü kod.

**O5 — Hata durumunda DB'ye tekrar soruluyor.** `HomeController:97` fallback olarak `Product::whereRaw('1=0')->paginate(24)` çalıştırıyor. Veritabanı erişilemezse (en olası 500 nedeni) bu da patlar. Fallback tamamen bellekte üretilmeli.

**O6 — `ShouldBeUnique` kilidine elle müdahale.** `DealerTrendyolController:183, 230, 233` framework'ün iç önbellek anahtarına (`laravel_unique_job:…`) doğrudan erişip `forceRelease()` çağırıyor (iki farklı olası anahtar tahmin edilmiş). Laravel sürüm yükseltmesinde sessizce kırılır. Ayrıca `uniqueFor = 120` sn iken gönderim saatlerce sürebiliyor → kilit düşer, **çift gönderim** mümkün. `uniqueFor` işlem süresiyle ölçeklenmeli.

**O7 — İptal tüm kuyruğu siliyor.** `cancelSend():223` → `DB::table('jobs')->where('queue','marketplace')->delete()`. **Diğer bayilerin** bekleyen işleri de silinir. Bayi bazlı filtre eklenmeli.

**O8 — `composer.lock` yok.** Dockerfile `composer install --no-dev` çalıştırıyor; kilit dosyası olmadan her derleme farklı sürümler çözebilir → "aynı kod, farklı davranış". `composer.lock` commit edin.

**O9 — Test altyapısı sıfır.** `tests/` ve `phpunit.xml` yok (composer.json `Tests\` namespace'ini var olmayan bir dizine bağlamış). Bakiye düşümü, iade/stok geri alma, fiyat hesabı, Trendyol payload doğrulama gibi kritik yollar için en azından özellik testleri şart.

**O10 — Ölü model alanları.** Şemada karşılığı olmayan kolonlar `$fillable` içinde: `BlacklistEntry` (`entry_type`, `entry_value`, `notes`), `DealerAnnouncement` (`content`, `priority`, `active_from`, `active_until`), `XmlImport` (`products_created`, `products_updated`, `completed_at`), `Order` (`total_amount`). Bunlara kitle ataması yapılırsa SQL hatası. Ayrıca `PlatformSetting` `$timestamps = false` ama tabloda `timestamps()` var; `MarketplaceOrder.payload` `NOT NULL` ve zorunlu.

**O11 — Dokümantasyon ve seeder çelişkileri.** README `admin@bayixml.test / password` diyor, `DatabaseSeeder` **`admin@bayiinet.test`** oluşturuyor, KURULUM ise "demo hesap oluşturulmaz" diyor. README hâlâ `/bayi/*` yollarını ve "BayiXML" adını anlatıyor (artık kök yollar + Bayiinet). `AGENTS.md` hâlâ jenerik Laravel Boost şablonu.

**O12 — Ölü iskelet dosyası.** `resources/views/welcome.blade.php` **72 KB** (inline derlenmiş Tailwind) ve hiçbir rotadan render edilmiyor; içinde `@vite()` var, ama `package.json`/`vite.config.js` yok → render edilmeye çalışılsa 500. `resources/css/app.css`, `resources/js/app.js` de derlenemiyor. Silinmeli.

**O13 — Oturum çerezi `secure` değil.** `config/session.php:172` → `env('SESSION_SECURE_COOKIE')` varsayılan `null` = `false`. HTTPS arkasında çalışan üretimde `SESSION_SECURE_COOKIE=true` verilmeli (veya varsayılan `true` yapılmalı).

**O14 — Denetim izi (audit log) yok.** Bakiye hareketleri dışında hiçbir admin eylemi kaydedilmiyor: ürün fiyatı değişikliği, bayi onay/askı, **Trendyol'dan toplu ürün silme** tamamen iz bırakmadan yapılıyor. En azından `activity_log` tablosu veya `Log::info` ile kritik eylemler kayda geçmeli.

**O15 — Şifre kuralları tutarsız.** Kayıt `min:6`, profil güncelleme `Password::defaults()`, `EnsureAdminUser` elle `mb_strlen < 8` kontrolü. Tek merkezden yönetilmeli.

**O16 — Trendyol API'sinde yeniden deneme/limit yönetimi yok.** `TrendyolMarketplaceService::send()` 429/5xx için yeniden deneme yapmıyor; `decode()` sadece mesaj üretiyor. Saatlik senkron + 15 dakikada bir batch kontrolü, birden fazla bayide Trendyol limitlerine takılabilir. `Http::retry()` + exponential backoff eklenmeli.

---

## 🟢 Küçük / temizlik

- `XmlImportService::parseProduct():343` — görseller için `for ($i = 1; $i <= max($imageCount, 15); $i++)`: `image_count` 0 olsa bile 15 tur dönüyor.
- `DealerTrendyolService:149` ve `:233` — sabit kodlanmış yedek marka ID'si `2613880` ("masaüstü BRAND_ID"). Yapılandırılabilir olmalı.
- `DealerTrendyolService::send():103-123` — marka çözümlemesi 14 günlük cache ile yapılıyor, iyi; ama `resolveGenericBrandId` çağrısı ürün başına değil, bir kez yapılıyor — doğru.
- `Admin\OperationsController::updateSettings():203-207` — `$data` içindeki her anahtarı doğrudan `PlatformSetting::write` ile yazıyor (whitelist yok). Şimdilik validated olduğu için sorun yok ama kırılgan.
- `Admin\DealerController::update()` — Trendyol kimlik bilgileri her güncellemede `trendyol_last_error`'ı sıfırlıyor; yalnızca kimlik değiştiğinde sıfırlanmalı.
- `bootstrap/app.php` — `trustProxies(at: '*')`: tüm proxy başlıklarına güveniliyor; Render arkasında pratikte sorun değil ama ileride sıkılaştırılabilir.
- `resources/views/admin/dealers/trendyol.blade.php:236` — "en fazla 5000" yazıyor, kod artık limitsiz gönderiyor. Metin güncellenmeli.
- `DatabaseSeeder` 3 demo ürün için `via.placeholder.com` görselleri kullanıyor (artık çalışmayan servis).
- `Product::getEffectiveStockAttribute()` varyantlı ürünlerde `sum()` döndürüyor; `?? 0` gereksiz (`sum` zaten sayı döner).

---

## Önerilen yol haritası

**1. hafta (güvenlik & hijyen)**
1. `.gitignore` ekle → `database/*.sqlite`'ı kaldır, geçmişi temizle, **Trendyol API anahtarını ve parolaları rotate et** (K1, K2)
2. Login/kayıt throttle + tek tip şifre kuralı (K3, O15)
3. XML feed'deki çift escaping ve varyant fiyatı düzelt (K4, K5)
4. `SESSION_SECURE_COOKIE=true`; `env()` → `config()` (O1, O13)
5. `composer.lock` commit et (O8)

**2. hafta (doğruluk & performans)**
6. Migration'ları tek doğrusal şemaya indirge → `Schema::hasColumn` savunmalarını kaldır (K1)
7. `default_profit_margin` / `default_margin_percent` üçlüsünü tek anahtara indir (Y6)
8. XML feed'i `chunkById` + stream + N+1 gider (Y1)
9. Varyantları silmek yerine `upsert`; `variant_price`/`sku` parse et (Y2)
10. Toplu fiyat uygulamasını import yoluna taşı (Y3)
11. Kategori eşleme yazmalarını toplu hale getir (Y4)

**3. hafta (altyapı & gözlemlenebilirlik)**
12. Kuyruk + zamanlayıcıyı ayrı worker servisine taşı, `artisan serve` → nginx/php-fpm (O2, O3)
13. `ShouldBeUnique` süresini işlem süresine göre ayarla; iptali bayi bazlı yap (O6, O7)
14. Admin eylemleri için audit log (O14)
15. Trendyol istemcisine retry/backoff (O16)
16. Eleştirel yollar için test iskeleti kur; `welcome.blade.php` + ölü kolonları temizle (O9, O10, O12)

---

### Not
Bu rapor statik incelemeye dayanır; hiçbir kod değiştirilmedi. İsterseniz yukarıdaki maddelerden birini veya birkaçını seçin, sırayla düzeltip test ederek ilerleyelim — en yüksek getirisi olan yer K1 (veritabanı + sır temizliği) ve K3/K4/K5 (güvenlik + bozuk XML çıktısı).
