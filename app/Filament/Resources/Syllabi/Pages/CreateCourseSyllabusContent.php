<?php

namespace App\Filament\Resources\Syllabi\Pages;

use App\Filament\Resources\Syllabi\CourseSyllabusContentResource;
use App\Filament\Resources\Syllabi\Schemas\CourseSyllabusContentCreateForm;
use App\Models\CourseAssignment;
use App\Models\CourseSyllabusContent;
use App\Services\CourseSyllabusFormBuilder;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class CreateCourseSyllabusContent extends CreateRecord
{
    protected static string $resource = CourseSyllabusContentResource::class;

    protected static ?string $title = 'Új tantárgyi adatlap';

    public function form(Schema $schema): Schema
    {
        return CourseSyllabusContentCreateForm::configure($schema);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $assignment = CourseAssignment::query()
            ->with('curriculumCourse.curriculum')
            ->find($data['course_assignment_id'] ?? null);

        if (! $assignment) {
            throw ValidationException::withMessages([
                'course_assignment_id' => 'Válassz egy érvényes tantárgyat.',
            ]);
        }

        if (CourseSyllabusContent::where('course_assignment_id', $assignment->id)->exists()) {
            throw ValidationException::withMessages([
                'course_assignment_id' => 'Ehhez a tantárgyhoz már létezik tantárgyi adatlap.',
            ]);
        }

        $builder = new CourseSyllabusFormBuilder;
        $template = $builder->resolveActiveTemplate($assignment);
        $initialData = $builder->getInitialFormData($assignment, $template);

        return [
            'course_assignment_id' => $assignment->id,
            'template_id' => $template->id,
            'editable_data' => $initialData['editable_data'],
            'status' => 'draft',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return CourseSyllabusContentResource::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Tantárgyi adatlap létrehozva — most kitöltheted a varázslóban.';
    }


    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Létrehozás és kitöltés'),
            $this->getCreateAnotherFormAction()
                ->label('Létrehozás és másik tantárgy'),
        ];
    }
}
