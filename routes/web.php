<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Dedicated route for syllabus DOCX download
// Filament/Livewire actions cannot return binary responses directly,
// so we store the generated file path in the session and redirect here.
Route::get('/download-syllabus', function (Request $request) {
    $filePath = session()->pull('syllabus_download_path');

    if (!$filePath || !file_exists($filePath)) {
        abort(404, 'A letöltendő fájl nem található.');
    }

    return response()->download($filePath, basename($filePath))->deleteFileAfterSend(true);
})->middleware(['auth'])->name('download.syllabus');
