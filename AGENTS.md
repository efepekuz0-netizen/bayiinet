# Bayiinet — geliştirici notları

Bu depo bir **Laravel 12 / PHP 8.3** uygulamasıdır: tedarikçi XML'lerini içe
aktarır, bayilere özel XML feed üretir, bayilerin Trendyol mağazalarına ürün
gönderir.

## Komutlar

```bash
php artisan serve                                   # geliştirme sunucusu
php artisan queue:work --queue=default,marketplace  # kuyruk işçisi (gerekli)
php artisan schedule:work                           # zamanlanmış görevler
php artisan migrate --seed                          # kurulum
php artisan bayiinet:ensure-admin                   # yönetici hesabı
php artisan test                                    # testler (yoksa eklenmeli)
```

Kuyruk ve zamanlayıcı **ayrı süreçlerdir**; çalışmıyorsa XML içe aktarma ve
Trendyol gönderimi sessizce bekler. Durum `Yönetim → Otomasyon` ekranından görülür.

## Yapı

- `app/Http/Controllers/Admin` — yönetim paneli (Türkçe rota adları: `/admin/kaynaklar`, `/admin/urunler`…)
- `app/Http/Controllers/Dealer` — bayi paneli (kök yollar: `/panel`, `/katalog`)
- `app/Jobs` — ağır işler: `ImportSourceJob`, `SendDealerTrendyolCatalog`,
  `SendDealerTrendyolBatch`, `VerifyTrendyolBatch`, `SyncDealerTrendyolInventory`
- `app/Services`
  - `XmlImportService` — XML ayrıştırma, varyant eşitleme, toplu fiyatlama
  - `PricingService` — kâr oranı / satış fiyatı hesapları (toplu güncelleme)
  - `DealerTrendyolService` — Trendyol ürün gönderimi, fiyat/stok, batch sonucu
  - `TrendyolSendProgress` — gönderim durumunun tek kaynağı (cache tabanlı sayaçlar)
  - `TrendyolCategoryMatcher` — XML kategorisi → Trendyol kategorisi eşlemesi
  - `AutomationStatus` — zamanlanmış görevlerin son çalışma/durum kaydı
  - `AdminAudit` — kritik yönetici eylemlerinin denetim izi
- `routes/console.php` — zamanlayıcı tanımları (saatlik XML/Trendyol, 15 dk batch)
- `config/bayiinet.php` — uygulamaya özel ayarlar (admin, trendyol, automation)

## Sözleşmeler / dikkat edilecekler

- **Arayüz ve mesajlar Türkçe.** Yeni metinleri Türkçe yazın.
- **Uzun işleri HTTP isteği içinde yapmayın** — kuyruğa atın
  (`ImportSourceJob` vb.). 15.000 ürünlük katalogda istek zaman aşımına düşer.
- **N+1'den kaçının**: listelerde `with()` kullanın, `Product::effective_stock`
  yüklü ilişkiyi kullanır.
- **Fiyatlar tek yerden hesaplanır**: `PricingService`. Ürün başına `save()`
  yerine toplu güncelleme (`recalculateImported`) tercih edilir.
- **Job zaman aşımı < kuyruk `retry_after` (960 sn)** olmalı; aksi hâlde iş
  ikinci bir işçiye düşer. Yeni işlerde `$timeout` en fazla 840 olmalı.
- **Yeni migration'lar idempotent olsun** (`Schema::hasColumn` kontrollü),
  çünkü üretimde eksik çalışmış migration'lar var.
- Modelden ölü kolonları temiz tutun: `$fillable` içindeki her alan migration'da
  karşılığı olmalı.
- Git'e asla `vendor/`, `.env`, `database/*.sqlite`, derlenmiş varlık koymayın.
