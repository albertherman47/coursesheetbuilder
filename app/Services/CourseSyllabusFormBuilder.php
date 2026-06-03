<?php

namespace App\Services;

use App\Models\CourseAssignment;
use App\Models\CourseSyllabusContent;
use App\Models\SyllabusTemplate;
use RuntimeException;

class CourseSyllabusFormBuilder
{
    /**
     * @return array{editable_data: array<string, array<string, mixed>>}
     */
    public function getInitialFormData(CourseAssignment $assignment, ?SyllabusTemplate $template = null): array
    {
        $template ??= $this->resolveActiveTemplate($assignment);
        $editable_data = [];

        foreach ($this->getSections($template) as $section) {
            foreach ($section['placeholders'] ?? [] as $placeholder) {
                if (($placeholder['is_editable'] ?? false) === true && ! empty($placeholder['form_section'])) {
                    $formSection = $placeholder['form_section'];
                    $dbField = $placeholder['db_field'];

                    if (! isset($editable_data[$formSection])) {
                        $editable_data[$formSection] = [];
                    }

                    if (($placeholder['form_type'] ?? null) === 'repeater') {
                        $editable_data[$formSection][$dbField] = [];
                    } else {
                        $editable_data[$formSection][$dbField] = '';
                    }
                }
            }
        }

        return ['editable_data' => $editable_data];
    }

    public function getReadonlyData(CourseSyllabusContent $content, CourseAssignment $assignment): array
    {
        $template = SyllabusTemplate::findOrFail($content->template_id);
        $resolver = new PlaceholderResolver($content, $assignment);

        $readonlyData = [];

        foreach ($this->getSections($template) as $section) {
            foreach ($section['placeholders'] ?? [] as $placeholder) {
                if (($placeholder['is_editable'] ?? false) === false) {
                    $value = $resolver->resolve($placeholder);
                    $readonlyData[$placeholder['name']] = $resolver->formatOutput($value, $placeholder);
                }
            }
        }

        return $readonlyData;
    }

    public function getEditableSections(CourseAssignment $assignment): array
    {
        $sections = [
            'meta',
            'individual_study',
            'prerequisites',
            'conditions',
            'objectives',
            'content_course',
            'alignment',
            'evaluation',
            'signatures',
        ];

        if (($assignment->curriculumCourse->seminar_hours ?? 0) > 0) {
            $sections[] = 'content_seminar';
        }

        if (($assignment->curriculumCourse->lab_hours ?? 0) > 0) {
            $sections[] = 'content_laboratory';
        }

        if (($assignment->curriculumCourse->project_hours ?? 0) > 0) {
            $sections[] = 'content_project';
        }

        if (($assignment->curriculumCourse->practice_hours ?? 0) > 0) {
            $sections[] = 'content_practice';
        }

        return $sections;
    }

    public function shouldShowSection(string $formSection, CourseAssignment $assignment): bool
    {
        return match ($formSection) {
            'content_seminar' => ($assignment->curriculumCourse->seminar_hours ?? 0) > 0,
            'content_laboratory' => ($assignment->curriculumCourse->lab_hours ?? 0) > 0,
            'content_project' => ($assignment->curriculumCourse->project_hours ?? 0) > 0,
            'content_practice' => ($assignment->curriculumCourse->practice_hours ?? 0) > 0,
            default => true,
        };
    }

    public function getSectionFormSchema(CourseAssignment $assignment, string $formSection): array
    {
        $template = $this->resolveActiveTemplate($assignment);
        $schema = [];

        foreach ($this->getSections($template) as $section) {
            foreach ($section['placeholders'] ?? [] as $placeholder) {
                if ($placeholder['form_section'] === $formSection && ($placeholder['is_editable'] ?? false) === true) {
                    $schema[] = [
                        'name' => $placeholder['db_field'],
                        'label' => $placeholder['description'],
                        'type' => $placeholder['form_type'] ?? 'text',
                        'required' => $placeholder['validation']['required'] ?? false,
                        'validation' => $placeholder['validation'] ?? [],
                        'placeholder' => $placeholder['name'],
                    ];
                }
            }
        }

        return $schema;
    }

    public function getFormFields(CourseAssignment $assignment): array
    {
        $template = $this->resolveActiveTemplate($assignment);
        $fields = [];

        foreach ($this->getSections($template) as $section) {
            foreach ($section['placeholders'] ?? [] as $placeholder) {
                if (($placeholder['is_editable'] ?? false) === true && ! empty($placeholder['form_section'])) {
                    $fields[$placeholder['name']] = [
                        'form_section' => $placeholder['form_section'],
                        'db_field' => $placeholder['db_field'],
                        'type' => $placeholder['form_type'] ?? 'text',
                        'label' => $placeholder['description'],
                        'validation' => $placeholder['validation'] ?? [],
                        'display_order' => $placeholder['display_order'] ?? 999,
                    ];
                }
            }
        }

        return $fields;
    }

    public function resolveActiveTemplate(CourseAssignment $assignment): SyllabusTemplate
    {
        $assignment->loadMissing('curriculumCourse.curriculum');

        return SyllabusTemplate::where('academic_year_id', $assignment->curriculumCourse->curriculum->academic_year_id)
            ->where('is_active', true)
            ->firstOrFail();
    }

    public function createDraftForAssignment(CourseAssignment $assignment): CourseSyllabusContent
    {
        $existing = CourseSyllabusContent::where('course_assignment_id', $assignment->id)->first();

        if ($existing) {
            return $existing;
        }

        $template = $this->resolveActiveTemplate($assignment);
        $initialData = $this->getInitialFormData($assignment, $template);

        return CourseSyllabusContent::create([
            'course_assignment_id' => $assignment->id,
            'template_id' => $template->id,
            'editable_data' => $initialData['editable_data'],
            'status' => 'draft',
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getSections(SyllabusTemplate $template): array
    {
        if (! $template->hasValidPlaceholdersConfig()) {
            throw new RuntimeException(
                "A(z) «{$template->name}» sablonban hiányzik a placeholders_config (mezőstruktúra). "
                .'Másold át a 2025/26-os sablon beállításait, vagy hozd létre újra a seed / placeholders_config.json alapján.'
            );
        }

        return array_values($template->placeholders_config['sections']);
    }
}
