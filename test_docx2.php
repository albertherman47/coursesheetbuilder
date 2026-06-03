<?php
echo "Template size: " . filesize('template_fisa_disciplinei_2025.docx') . " bytes\n";
echo "\nGenerated syllabi files:\n";
foreach (glob('storage/app/syllabi/*.docx') as $f) {
    echo basename($f) . " = " . filesize($f) . " bytes\n";
}

// Check if template is valid zip/docx
$zip = new ZipArchive();
$result = $zip->open('template_fisa_disciplinei_2025.docx');
if ($result === true) {
    echo "\nTemplate ZIP valid, contains " . $zip->numFiles . " files\n";
    $zip->close();
} else {
    echo "\nTemplate ZIP ERROR code: " . $result . "\n";
}
