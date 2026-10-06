@extends('layouts.app')
@section('title', 'Bayi Duyuruları')
@section('content')
<div class="mb-3"><h4 class="mb-1">İlanlar ve duyurular</h4><div class="text-muted small">Burada yayınlanan duyurular aktif bayilerin panelinde görüntülenir.</div></div>
<div class="card mb-3">
    <div class="card-header bg-white fw-semibold">Duyuru oluştur</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.announcements.store') }}" class="row g-3">
            @csrf
            <div class="col-md-6"><label class="form-label">Başlık</label><input name="title" class="form-control" maxlength="255" required></div>
            <div class="col-md-3"><label class="form-label">Başlangıç</label><input type="datetime-local" name="starts_at" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Bitiş</label><input type="datetime-local" name="ends_at" class="form-control"></div>
            <div class="col-12"><label class="form-label">Duyuru</label><textarea name="body" class="form-control" rows="3" maxlength="5000" required></textarea></div>
            <div class="col-md-8 form-check ms-2"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="announcementActive" checked><label for="announcementActive" class="form-check-label">Aktif olarak yayınla</label></div>
            <div class="col-md-3"><button class="btn btn-primary">Duyuruyu kaydet</button></div>
        </form>
    </div>
</div>
<div class="card">
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>BAŞLIK</th><th>DURUM</th><th>YAYIN TARİHİ</th><th></th></tr></thead><tbody>
    @forelse($announcements as $announcement)
        <tr><td><strong>{{ $announcement->title }}</strong><div class="small text-muted">{{ \Illuminate\Support\Str::limit($announcement->body, 110) }}</div></td><td><span class="badge text-bg-{{ $announcement->is_active ? 'success' : 'secondary' }}">{{ $announcement->is_active ? 'Aktif' : 'Pasif' }}</span></td><td>{{ $announcement->created_at->format('d.m.Y H:i') }}</td><td><form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Sil</button></form></td></tr>
    @empty
        <tr><td colspan="4" class="text-center text-muted py-4">Henüz bayi duyurusu yok.</td></tr>
    @endforelse
    </tbody></table></div>
    <div class="card-footer bg-white">{{ $announcements->links() }}</div>
</div>
@endsection
