@extends('layouts.app')
@section('title', 'Yeni Kaynak')
@section('content')
<h4 class="mb-4">Yeni XML Kaynağı</h4>
<div class="card" style="max-width:600px">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.sources.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Kaynak Adı</label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" required value="{{ old('name') }}">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="type">Kaynak Tipi</label>
                <select name="type" id="type" class="form-select" required>
                    <option value="url" @selected(old('type', 'url') === 'url')>XML bağlantısı</option>
                    <option value="file" @selected(old('type') === 'file')>Dosya yükleme</option>
                </select>
            </div>
            <div class="mb-3" id="url-field">
                <label class="form-label" for="url">XML bağlantısı</label>
                <input type="url" name="url" id="url" class="form-control @error('url') is-invalid @enderror" value="{{ old('url') }}" placeholder="https://tedarikci.com/urunler.xml">
                @error('url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Kaynağı kaydettikten sonra bağlantıdan XML'i güncelleyebilirsiniz.</div>
            </div>
            <div class="mb-3">
                <label class="form-label">Öncelik (1-100)</label>
                <input type="number" name="priority" class="form-control" value="{{ old('priority', 10) }}" min="1" max="100">
            </div>
            <div class="row g-2 mb-3">
                <div class="col-md-4"><label class="form-label">XML kâr %</label><input type="number" step="0.01" min="0" max="500" name="xml_margin_percent" class="form-control" value="{{ old('xml_margin_percent') }}"></div>
                <div class="col-md-4"><label class="form-label">Minimum kâr %</label><input type="number" step="0.01" min="0" max="500" name="min_margin_percent" class="form-control" value="{{ old('min_margin_percent') }}"></div>
                <div class="col-md-4"><label class="form-label">XML KDV %</label><input type="number" step="0.01" min="0" max="100" name="tax_rate" class="form-control" value="{{ old('tax_rate') }}"></div>
            </div>
            <div class="form-check mb-3"><input type="checkbox" name="prices_include_tax" value="1" class="form-check-input" id="includeTax"><label class="form-check-label" for="includeTax">XML alış fiyatları KDV dahil</label></div>
            <button type="submit" class="btn btn-primary">Kaydet</button>
            <a href="{{ route('admin.sources.index') }}" class="btn btn-link">İptal</a>
        </form>
    </div>
</div>
<script>
    const sourceType = document.getElementById('type');
    const urlField = document.getElementById('url-field');
    const urlInput = document.getElementById('url');

    function updateUrlField() {
        const usesUrl = sourceType.value === 'url';
        urlField.hidden = !usesUrl;
        urlInput.required = usesUrl;
    }

    sourceType.addEventListener('change', updateUrlField);
    updateUrlField();
</script>
@endsection
