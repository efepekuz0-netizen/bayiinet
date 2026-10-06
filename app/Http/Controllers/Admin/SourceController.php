<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Source;
use App\Models\XmlImport;
use App\Services\XmlImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SourceController extends Controller
{
    public function index()
    {
        $sources = Source::withCount('products')->latest()->get();
        $imports = XmlImport::with('source')->latest()->take(20)->get();

        return view('admin.sources.index', compact('sources', 'imports'));
    }

    public function create()
    {
        return view('admin.sources.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:file,url',
            'url' => 'exclude_unless:type,url|required|url:http,https',
            'priority' => 'nullable|integer|min:1|max:100',
        ]);

        $data['slug'] = Str::slug($data['name']).'-'.Str::random(4);
        $data['is_active'] = true;
        $data['priority'] = $data['priority'] ?? 10;

        Source::create($data);

        return redirect()->route('admin.sources.index')->with('success', 'Kaynak oluşturuldu.');
    }

    public function upload(Request $request, Source $source, XmlImportService $importService)
    {
        $request->validate([
            'xml_file' => 'required|file|mimes:xml,txt|max:51200', // 50MB
        ]);

        $file = $request->file('xml_file');
        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs("xml/{$source->id}", Str::uuid().".{$extension}");

        if ($path === false) {
            throw new \RuntimeException('XML dosyası güvenli depolama alanına kaydedilemedi.');
        }

        $source->update(['file_path' => $path]);

        $import = $importService->importFromFile($source, Storage::disk('local')->path($path), auth()->id());

        if ($import->status === 'completed') {
            return redirect()->route('admin.sources.index')
                ->with('success', "Import tamamlandı: {$import->created_count} yeni, {$import->updated_count} güncellendi.");
        }

        return redirect()->route('admin.sources.index')
            ->with('error', 'Import başarısız: '.($import->log ?? 'Bilinmeyen hata'));
    }

    public function refresh(Source $source, XmlImportService $importService)
    {
        abort_unless($source->type === 'url', 404);

        $import = $importService->importFromUrl($source, auth()->id());

        if ($import->status === 'completed') {
            return redirect()->route('admin.sources.index')
                ->with('success', "XML güncellendi: {$import->created_count} yeni, {$import->updated_count} güncellendi.");
        }

        return redirect()->route('admin.sources.index')
            ->with('error', 'XML güncellenemedi: '.($import->log ?? 'Bilinmeyen hata'));
    }

    public function destroy(Source $source)
    {
        $source->delete();

        return redirect()->route('admin.sources.index')->with('success', 'Kaynak silindi.');
    }
}
