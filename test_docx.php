<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== DOCX Generation Test ===" . PHP_EOL;

// Check template exists
$templatePath = base_path('template_fisa_disciplinei_2025.docx');
echo "Template exists: " . (file_exists($templatePath) ? 'YES' : 'NO - MISSING!') . PHP_EOL;

// Check storage/app/syllabi writable
$syllabi = storage_path('app/syllabi');
echo "Syllabi dir exists: " . (is_dir($syllabi) ? 'YES' : 'NO') . PHP_EOL;
echo "Syllabi dir writable: " . (is_writable(dirname($syllabi)) ? 'YES' : 'NO') . PHP_EOL;

// Get first syllabus
$content = App\Models\CourseSyllabusContent::with('courseAssignment')->first();
if (!$content) {
    echo "ERROR: No CourseSyllabusContent found in DB!" . PHP_EOL;
    exit(1);
}
echo "Syllabus ID: " . $content->id . PHP_EOL;
echo "Has courseAssignment: " . ($content->courseAssignment ? 'YES (id=' . $content->courseAssignment->id . ')' : 'NO - NULL!') . PHP_EOL;
echo "Has template: " . ($content->template ? 'YES' : 'NO - NULL!') . PHP_EOL;

// Try to generate
try {
    $generator = new App\Services\SyllabusDocxGenerator($content, $content->courseAssignment);
    $filePath = $generator->generate();
    echo "SUCCESS! File saved: " . $filePath . PHP_EOL;
    echo "File exists: " . (file_exists($filePath) ? 'YES' : 'NO') . PHP_EOL;
    echo "File size: " . filesize($filePath) . " bytes" . PHP_EOL;
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    echo "Trace: " . $e->getTraceAsString() . PHP_EOL;
}
