<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$import = App\Models\XmlImport::where("status", "processing")->first();
if ($import) {
    echo "Bulunan XML Import ID: {$import->id}, Dosya: {$import->file_path}\n";
    
    // Eğer projenizde XML\i
