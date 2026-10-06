@extends('layouts.app')
@section('title', 'Ayarlar')
@section('content')
<div class="mb-3"><h4 class="mb-1">Sistem ayarları</h4><div class="text-muted small">Stok alarm eşiği, panel çalışma modeli ve sunucu görevleri.</div></div>
<div class="row g-3">
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Ürün ve panel</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.settings.update') }}">
                    @csrf @method('PUT')
                    <div class="mb-3"><label class="form-label">Kritik stok eşiği</label><input type="number" name="critical_stock_threshold" class="form-control" min="0" max="100000" value="{{ $settings['critical_stock_threshold'] }}" required><div class="form-text">Ürün veya varyant stoğu bu sayı ve altına düşünce kritik stok ekranında görünür.</div></div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">XML kâr oranı (%)</label><input type="number" step="0.1" name="xml_margin_percent" class="form-control" min="0" max="500" value="{{ $settings['xml_margin_percent'] }}"></div>
                        <div class="col-md-6"><label class="form-label">XML KDV oranı (%)</label><input type="number" step="0.1" name="xml_tax_rate" class="form-control" min="0" max="100" value="{{ $settings['xml_tax_rate'] }}"></div>
                    </div>
                    <div class="form-check mt-3 mb-3"><input type="checkbox" name="xml_prices_include_tax" value="1" class="form-check-input" id="settingsXmlVat" @checked($settings['xml_prices_include_tax'] === '1')><label class="form-check-label" for="settingsXmlVat">XML alış fiyatları KDV dahil</label></div>
                    <div class="form-text mb-3">Kaydettiğiniz XML kâr oranı mevcut ürünlere de yeniden uygulanır. XML'deki ürün tax alanı varsa ürünün kendi oranı korunur, boşsa burada belirlediğiniz oran kullanılır.</div>
                    <button class="btn btn-primary">Ayarları kaydet</button>
                </form>
                <hr>
                <div class="mb-2"><strong>XML aktarım şekli</strong><span class="badge text-bg-primary ms-2">Dosya yükleme</span></div>
                <p class="text-muted small mb-0">XML içe aktarımları yönetici tarafından yüklenir. Otomatik URL senkronizasyonu ve pazaryeri API bağlantıları bu sürümde yapılandırılmadı.</p>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Zamanlanmış görevler (cron)</div>
            <div class="card-body">
                <div class="alert alert-info small">Sunucuda Laravel zamanlayıcısını her dakika çalıştırmak için aşağıdaki cron satırını barındırma paneline ekleyin. Henüz zamanlanmış XML çekim görevi tanımlı değildir.</div>
                <label class="form-label">Laravel Scheduler</label>
                <div class="input-group mb-3"><input id="schedulerCommand" class="form-control font-monospace" readonly value="* * * * * cd {{ base_path() }} && php artisan schedule:run >> /dev/null 2>&1"><button class="btn btn-outline-secondary" type="button" data-copy-target="schedulerCommand">Kopyala</button></div>
                <label class="form-label">Kuyruk işçisi (yalnızca kuyruk kullanıldığında)</label>
                <div class="input-group"><input id="queueCommand" class="form-control font-monospace" readonly value="php artisan queue:work --stop-when-empty"><button class="btn btn-outline-secondary" type="button" data-copy-target="queueCommand">Kopyala</button></div>
                <div class="form-text mt-2">Cron kayıtlarının sunucuda gerçekten eklenip çalıştığı bu panelden doğrulanamaz.</div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-copy-target]').forEach((button) => {
    button.addEventListener('click', async () => {
        const input = document.getElementById(button.dataset.copyTarget);
        await navigator.clipboard.writeText(input.value);
        button.textContent = 'Kopyalandı';
        setTimeout(() => button.textContent = 'Kopyala', 1500);
    });
});
</script>
@endpush
