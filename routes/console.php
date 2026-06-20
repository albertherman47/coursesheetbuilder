<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\CurriculumImporter;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('curriculum:import {file} {--force}', function (CurriculumImporter $importer) {
    $file = $this->argument('file');
    $filePath = base_path($file);

    if (!file_exists($filePath)) {
        $filePath = $file; // Próbáljuk meg közvetlen elérési úttal is
    }

    if (!file_exists($filePath)) {
        $this->error("A megadott JSON fájl nem található: {$file}");
        return 1;
    }

    $this->info("Importálás folyamatban: {$filePath}...");
    $result = $importer->importFromFile($filePath, $this->option('force'));

    if ($result['status'] === 'imported') {
        $this->info("Sikeres importálás: {$result['message']}");
        if (isset($result['course_count'])) {
            $this->info("Importált tantárgyak száma: {$result['course_count']}");
        }
    } elseif ($result['status'] === 'skipped') {
        $this->warn("Kihagyva: {$result['message']}");
    } else {
        $this->error("Hiba történt: {$result['message']}");
        return 1;
    }

    return 0;
})->purpose('Importál egy tantervet JSON fájlból');
