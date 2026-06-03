<?php

namespace App\Filament\Resources\CourseAssignments\Pages;

use App\Filament\Resources\CourseAssignments\CourseAssignmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCourseAssignment extends EditRecord
{
    protected static string $resource = CourseAssignmentResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->loadMissing([
            'curriculum.program',
            'curriculum.academicYear',
        ]);

        return $data;
    }

    public function getTitle(): string
    {
        $year = $this->record->curriculum?->academicYear?->year_code;
        $course = $this->record->course_name_hu;

        return $year
            ? "Feladatkiosztás — {$course} ({$year})"
            : "Feladatkiosztás — {$course}";
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
