# BayiXML — XML Bayilik & Toptan Platformu

Modern, dengeli, production’a yakın XML bayilik sistemi.

## Kurulum (Linux)

```bash
cd bayixml
composer install          # vendor yoksa
cp .env.example .env      # yoksa
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

Aç: **http://127.0.0.1:8000**

## Hesaplar

| Rol   | E-posta              | Şifre     |
|-------|----------------------|-----------|
| Admin | admin@bayixml.test   | password  |
| Bayi  | bayi@bayixml.test    | password  |

## Modüller

### Admin
- Dashboard
- XML Kaynakları (dosya / URL, import, yenile)
- Ürün havuzu + kritik stok
- Kara liste
- Bayiler (onay, bakiye, askı)
- Sipariş yönetimi
- Pazaryeri (Trendyol bağlantı & senkron)
- Kategori & marka
- Kâr & fiyatlama
- İlanlar
- Ayarlar
- Tek XML dışa aktarım
- Kayıtlar (import log)

### Bayi
- Dashboard + bakiye
- Özel XML linki
- Ürün kataloğu
- Sipariş oluştur (bakiyeden düşer)
- Sipariş geçmişi

### XML
- Bayiye özel: `/xml/{token}.xml`
- Admin genel: `/xml/feed.xml`

## Notlar

- SQLite varsayılan; üretimde MySQL önerilir.
- Trendyol API anahtarlarını Ayarlar / Pazaryeri’nden girin.
- `php artisan serve` sadece test içindir.
