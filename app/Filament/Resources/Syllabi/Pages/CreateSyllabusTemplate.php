<?php

namespace App\Filament\Resources\Syllabi\Pages;

use App\Filament\Resources\Syllabi\SyllabusTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSyllabusTemplate extends CreateRecord
{
    protected static string $resource = SyllabusTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['placeholders_config'])) {
            $data['placeholders_config'] = self::defaultPlaceholdersConfig();
        }

        if (empty($data['docx_template_path'])) {
            $data['docx_template_path'] = 'template_fisa_disciplinei_2025.docx';
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultPlaceholdersConfig(): array
    {
        $path = base_path('placeholders_config.json');

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }
}
