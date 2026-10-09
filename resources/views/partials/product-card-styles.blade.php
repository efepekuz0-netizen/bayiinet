{{-- Ürün kartı stilleri — tüm sayfalarda ortak (home, katalog, ürün detay) --}}
<style>
    .product-card {
        border: 1px solid #e8edf5;
        border-radius: 14px;
        overflow: hidden;
        background: #fff;
        transition: box-shadow .2s ease, transform .2s ease, border-color .2s ease;
        height: 100%;
    }
    .product-card:hover {
        box-shadow: 0 10px 26px rgba(37, 99, 235, .13);
        border-color: #cddafc;
        transform: translateY(-2px);
    }
    .pc-img-wrap { position: relative; background: #f1f5f9; }
    .pc-img {
        width: 100%;
        height: var(--pc-img-h, 200px);
        object-fit: cover;
        display: block;
        background: #f1f5f9;
    }
    .pc-img-placeholder {
        width: 100%;
        height: var(--pc-img-h, 200px);
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f1f5f9;
        color: #94a3b8;
    }
    .product-card .pc-brand { font-size: .75rem; color: #64748b; }
    .product-card .pc-title {
        font-size: .9rem;
        line-height: 1.35;
        min-height: 2.7em;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }
    .product-card .pc-price { color: #2563eb; font-weight: 800; font-size: 1.1rem; }
    .product-card .pc-code { font-size: .75rem; color: #94a3b8; }
    .product-card .pc-stock { font-size: .72rem; }

    @media (max-width: 1199.98px) { :root { --pc-img-h: 180px; } }
    @media (max-width: 767.98px) { :root { --pc-img-h: 165px; } }
    @media (max-width: 400px) { :root { --pc-img-h: 140px; } }
</style>
