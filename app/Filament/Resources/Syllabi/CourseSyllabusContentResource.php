<?php

namespace App\Filament\Resources\Syllabi;

use App\Filament\Resources\Syllabi\Pages\CreateCourseSyllabusContent;
use App\Filament\Resources\Syllabi\Pages\EditCourseSyllabusContent;
use App\Filament\Resources\Syllabi\Pages\ListCourseSyllabusContents;
use App\Filament\Resources\Syllabi\Schemas\CourseSyllabusContentCreateForm;
use App\Models\CourseSyllabusContent;
use Illuminate\Support\Facades\Route;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Select;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CourseSyllabusContentResource extends Resource
{
    protected static ?string $model = CourseSyllabusContent::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationLabel = 'Tantárgyi adatlapok';

    protected static \UnitEnum|string|null $navigationGroup = 'Tantárgyi adatlap';

    protected static ?int $navigationSort = 2;



    public static function form(Schema $schema): Schema
    {
        $routeName = Route::currentRouteName() ?? '';

        if (str_contains($routeName, 'course-syllabus-contents.create')) {
            return CourseSyllabusContentCreateForm::configure($schema);
        }

        return $schema->components([
            Wizard::make([
                // Step 1: Info (Read-only Program & Discipline Info)
                Wizard\Step::make('info')
                    ->label('1. A program adatai')
                    ->schema(function (?CourseSyllabusContent $record) {
                        if (!$record || !$record->courseAssignment) {
                            return [
                                Placeholder::make('no_assignment')
                                    ->content('Please create the course assignment first.'),
                            ];
                        }

                        $builder = new \App\Services\CourseSyllabusFormBuilder();
                        $readonlyData = $builder->getReadonlyData($record, $record->courseAssignment);

                        // Get template to access config
                        $template = \App\Models\SyllabusTemplate::find($record->template_id);
                        if (!$template) {
                            return [Placeholder::make('error')->content('Template not found')];
                        }

                        // Build program section dynamically from config
                        $programSchema = [];
                        $programPlaceholders = $template->getPlaceholdersBySection('1_program_info');
                        usort($programPlaceholders, fn($a, $b) => $a['display_order'] <=> $b['display_order']);

                        foreach ($programPlaceholders as $placeholder) {
                            $name = $placeholder['name'];
                            $programSchema[] = Placeholder::make($name)
                                ->label($placeholder['description'])
                                ->content($readonlyData[$name] ?? '-');
                        }

                        // Build discipline section dynamically from config
                        $disciplineSchema = [];
                        $disciplinePlaceholders = $template->getPlaceholdersBySection('2_discipline_info');
                        usort($disciplinePlaceholders, fn($a, $b) => $a['display_order'] <=> $b['display_order']);

                        foreach ($disciplinePlaceholders as $placeholder) {
                            $name = $placeholder['name'];
                            $disciplineSchema[] = Placeholder::make($name)
                                ->label($placeholder['description'])
                                ->content($readonlyData[$name] ?? '-');
                        }

                        return [
                            Section::make('1. A program adatai')
                                ->schema($programSchema)
                                ->columns(2),

                            Section::make('2. A tantárgy adatai')
                                ->schema($disciplineSchema)
                                ->columns(2),
                        ];
                    })
                    ->columns(1),

                // Step 2: Time Allocation
                Wizard\Step::make('time')
                    ->label('3. Becsült teljes idő')
                    ->schema(function (?CourseSyllabusContent $record) {
                        if (!$record) {
                            return [];
                        }

                        $builder = new \App\Services\CourseSyllabusFormBuilder();
                        $readonlyData = $builder->getReadonlyData($record, $record->courseAssignment);

                        // Get template to access config
                        $template = \App\Models\SyllabusTemplate::find($record->template_id);
                        if (!$template) {
                            return [];
                        }

                        // Build readonly time allocation schema (3.1-3.6)
                        $timeAllocationSchema = [];
                        $timeAllocationPlaceholders = $template->getPlaceholdersBySection('3_time_allocation');
                        usort($timeAllocationPlaceholders, fn($a, $b) => $a['display_order'] <=> $b['display_order']);

                        // Only add readonly placeholders (is_editable: false)
                        foreach ($timeAllocationPlaceholders as $placeholder) {
                            if ($placeholder['is_editable'] === false) {
                                $name = $placeholder['name'];
                                $timeAllocationSchema[] = Placeholder::make($name)
                                    ->label($placeholder['description'])
                                    ->content($readonlyData[$name] ?? '-');
                            }
                        }

                        // Build editable individual study schema (3.10)
                        $editableSchema = [];
                        foreach ($timeAllocationPlaceholders as $placeholder) {
                            if ($placeholder['is_editable'] === true && $placeholder['form_section'] === 'individual_study') {
                                $name = $placeholder['name'];
                                $editableSchema[] = TextInput::make('editable_data.individual_study.' . $name)
                                    ->label($placeholder['description'])
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->suffix('ore');
                            }
                        }

                        $sections = [
                            Section::make('3. Óraszámok és eloszlás')
                                ->schema($timeAllocationSchema)
                                ->columns(2),
                        ];

                        if (!empty($editableSchema)) {
                            $sections[] = Section::make('3.10. Az egyéni tanulásra szánt idő eloszlása')
                                ->schema($editableSchema)
                                ->columns(2);
                        }

                        return $sections;
                    }),

                // Step 3: Prerequisites & Conditions
                Wizard\Step::make('prerequisites')
                    ->label('Előfeltételek és feltételek')
                    ->schema([
                        Section::make('4. Előfeltételek')
                            ->schema([
                                Textarea::make('editable_data.prerequisites.prereq_curr')
                                    ->label('4.1. Tantervi')
                                    ->rows(3)
                                    ->placeholder('Nincs'),
                                Textarea::make('editable_data.prerequisites.prereq_comp')
                                    ->label('4.2. Kompetencia')
                                    ->rows(3)
                                    ->placeholder('HTML, CSS, JavaScript'),
                            ])
                            ->columns(1),

                        Section::make('5. Feltételek')
                            ->schema([
                                Textarea::make('editable_data.conditions.cond_course')
                                    ->label('5.1. Az előadás lebonyolításához')
                                    ->rows(3),
                                Textarea::make('editable_data.conditions.cond_pract')
                                    ->label('5.2. A szeminárium/laboratórium/projekt/gyakorlat lebonyolításához')
                                    ->rows(3),
                            ])
                            ->columns(1),
                    ]),

                // Step 4: Learning Outcomes (Read-only)
                Wizard\Step::make('learning_outcomes')
                    ->label('Tanulási eredmények')
                    ->schema(function (?CourseSyllabusContent $record) {
                        if (!$record || !$record->courseAssignment) {
                            return [];
                        }

                        $course = $record->courseAssignment->curriculumCourse;

                        // Handle both string and array formats
                        $knowledge = $course->learning_outcomes_knowledge;
                        if (is_string($knowledge)) {
                            $knowledge = json_decode($knowledge, true) ?? [];
                        } elseif (!is_array($knowledge)) {
                            $knowledge = [];
                        }

                        $skills = $course->learning_outcomes_skills;
                        if (is_string($skills)) {
                            $skills = json_decode($skills, true) ?? [];
                        } elseif (!is_array($skills)) {
                            $skills = [];
                        }

                        $responsibility = $course->learning_outcomes_responsibility;
                        if (is_string($responsibility)) {
                            $responsibility = json_decode($responsibility, true) ?? [];
                        } elseif (!is_array($responsibility)) {
                            $responsibility = [];
                        }

                        return [
                            Placeholder::make('learn_know')
                                ->label('6. Ismeretek')
                                ->content('• ' . implode("\n• ", $knowledge ?: ['Nincs megadva'])),

                            Placeholder::make('learn_skills')
                                ->label('6. Készségek')
                                ->content('• ' . implode("\n• ", $skills ?: ['Nincs megadva'])),

                            Placeholder::make('learn_resp')
                                ->label('6. Felelősség és autonómia')
                                ->content('• ' . implode("\n• ", $responsibility ?: ['Nincs megadva'])),
                        ];
                    }),

                // Step 5: Objectives
                Wizard\Step::make('objectives')
                    ->label('Célkitűzések')
                    ->schema([
                        Textarea::make('editable_data.objectives.obj_gen')
                            ->label('7.1. A tantárgy általános célkitűzése')
                            ->required()
                            ->rows(5),
                        Textarea::make('editable_data.objectives.obj_spec')
                            ->label('7.2. Specifikus célkitűzések')
                            ->required()
                            ->rows(5),
                    ]),

                // Step 6: Course Content
                Wizard\Step::make('content_course')
                    ->label('Előadás tartalma')
                    ->schema([
                        Section::make('8.1. Előadás - Témák')
                            ->schema([
                                Repeater::make('editable_data.content_course.course_topics')
                                    ->label('Előadás témái')
                                    ->schema([
                                        Textarea::make('topic')
                                            ->label('Téma')
                                            ->required()
                                            ->rows(2)
                                            ->columnSpan(2),
                                        TextInput::make('hours')
                                            ->label('Óra')
                                            ->required()
                                            ->numeric(),
                                    ])
                                    ->columns(3)
                                    ->defaultItems(0)
                                    ->addActionLabel('Téma hozzáadása'),
                            ]),

                        Section::make('8.1. Előadás - Oktatási módszerek')
                            ->schema([
                                Textarea::make('editable_data.content_course.course_teaching_methods')
                                    ->label('Oktatási módszerek')
                                    ->rows(3)
                                    ->placeholder('Előadás: leírás, magyarázat, gyakorlati példák, demonstrációk.'),
                            ]),

                        Section::make('8.1. Előadás - Könyvészet')
                            ->schema([
                                Repeater::make('editable_data.content_course.bibliography_course')
                                    ->label('Könyvészet')
                                    ->schema([
                                        Textarea::make('citation')
                                            ->label('Bibliográfiai hivatkozás')
                                            ->required()
                                            ->rows(2),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel('Hivatkozás hozzáadása'),
                            ]),
                    ]),

                // Step 7: Laboratory Content (Conditional)
                Wizard\Step::make('content_laboratory')
                    ->label('Laboratórium tartalma')
                    ->schema([
                        Section::make('8.3. Laboratórium - Feladatok')
                            ->schema([
                                Repeater::make('editable_data.content_laboratory.laboratory_tasks')
                                    ->label('Laboratóriumi feladatok')
                                    ->schema([
                                        Textarea::make('task')
                                            ->label('Feladat')
                                            ->required()
                                            ->rows(2)
                                            ->columnSpan(2),
                                        TextInput::make('hours')
                                            ->label('Óra')
                                            ->required()
                                            ->numeric(),
                                    ])
                                    ->columns(3)
                                    ->defaultItems(0)
                                    ->addActionLabel('Feladat hozzáadása'),
                            ]),

                        Section::make('8.3. Laboratórium - Oktatási módszerek')
                            ->schema([
                                Textarea::make('editable_data.content_laboratory.laboratory_teaching_methods')
                                    ->label('Oktatási módszerek')
                                    ->rows(3),
                            ]),

                        Section::make('8.3. Laboratórium - Könyvészet')
                            ->schema([
                                Repeater::make('editable_data.content_laboratory.bibliography_laboratory')
                                    ->label('Könyvészet')
                                    ->schema([
                                        Textarea::make('citation')
                                            ->label('Bibliográfiai hivatkozás')
                                            ->required()
                                            ->rows(2),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel('Hivatkozás hozzáadása'),
                            ]),
                    ])
                    ->hidden(fn(?CourseSyllabusContent $record) =>
                        !$record || !$record->courseAssignment || ($record->courseAssignment->curriculumCourse->lab_hours ?? 0) == 0
                    ),

                // Step 7.1: Seminar Content (Conditional)
                Wizard\Step::make('content_seminar')
                    ->label('Szeminárium tartalma')
                    ->schema([
                        Section::make('8.2. Szeminárium - Témák')
                            ->schema([
                                Repeater::make('editable_data.content_seminar.seminar_topics')
                                    ->label('Szeminárium témái')
                                    ->schema([
                                        Textarea::make('topic')
                                            ->label('Téma')
                                            ->required()
                                            ->rows(2)
                                            ->columnSpan(2),
                                        TextInput::make('hours')
                                            ->label('Óra')
                                            ->required()
                                            ->numeric(),
                                    ])
                                    ->columns(3)
                                    ->defaultItems(0)
                                    ->addActionLabel('Téma hozzáadása'),
                            ]),

                        Section::make('8.2. Szeminárium - Oktatási módszerek')
                            ->schema([
                                Textarea::make('editable_data.content_seminar.seminar_teaching_methods')
                                    ->label('Oktatási módszerek')
                                    ->rows(3),
                            ]),

                        Section::make('8.2. Szeminárium - Könyvészet')
                            ->schema([
                                Repeater::make('editable_data.content_seminar.bibliography_seminar')
                                    ->label('Könyvészet')
                                    ->schema([
                                        Textarea::make('citation')
                                            ->label('Bibliográfiai hivatkozás')
                                            ->required()
                                            ->rows(2),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel('Hivatkozás hozzáadása'),
                            ]),
                    ])
                    ->hidden(fn(?CourseSyllabusContent $record) =>
                        !$record || !$record->courseAssignment || ($record->courseAssignment->curriculumCourse->seminar_hours ?? 0) == 0
                    ),

                // Step 7.2: Project Content (Conditional)
                Wizard\Step::make('content_project')
                    ->label('Projekt tartalma')
                    ->schema([
                        Section::make('8.4. Projekt - Tartalom')
                            ->schema([
                                Repeater::make('editable_data.content_project.project_content')
                                    ->label('Projekt tartalma')
                                    ->schema([
                                        Textarea::make('content')
                                            ->label('Tartalom')
                                            ->required()
                                            ->rows(2)
                                            ->columnSpan(2),
                                        TextInput::make('hours')
                                            ->label('Óra')
                                            ->required()
                                            ->numeric(),
                                    ])
                                    ->columns(3)
                                    ->defaultItems(0)
                                    ->addActionLabel('Tartalom hozzáadása'),
                            ]),

                        Section::make('8.4. Projekt - Oktatási módszerek')
                            ->schema([
                                Textarea::make('editable_data.content_project.project_teaching_methods')
                                    ->label('Oktatási módszerek')
                                    ->rows(3),
                            ]),

                        Section::make('8.4. Projekt - Könyvészet')
                            ->schema([
                                Repeater::make('editable_data.content_project.bibliography_project')
                                    ->label('Könyvészet')
                                    ->schema([
                                        Textarea::make('citation')
                                            ->label('Bibliográfiai hivatkozás')
                                            ->required()
                                            ->rows(2),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel('Hivatkozás hozzáadása'),
                            ]),
                    ])
                    ->hidden(fn(?CourseSyllabusContent $record) =>
                        !$record || !$record->courseAssignment || ($record->courseAssignment->curriculumCourse->project_hours ?? 0) == 0
                    ),

                // Step 7.3: Practice Content (Conditional)
                Wizard\Step::make('content_practice')
                    ->label('Gyakorlat tartalma')
                    ->schema([
                        Section::make('8.5. Gyakorlat - Tartalom')
                            ->schema([
                                Repeater::make('editable_data.content_practice.practice_content')
                                    ->label('Gyakorlat tartalma')
                                    ->schema([
                                        Textarea::make('content')
                                            ->label('Tartalom')
                                            ->required()
                                            ->rows(2)
                                            ->columnSpan(2),
                                        TextInput::make('hours')
                                            ->label('Óra')
                                            ->required()
                                            ->numeric(),
                                    ])
                                    ->columns(3)
                                    ->defaultItems(0)
                                    ->addActionLabel('Tartalom hozzáadása'),
                            ]),

                        Section::make('8.5. Gyakorlat - Oktatási módszerek')
                            ->schema([
                                Textarea::make('editable_data.content_practice.practice_teaching_methods')
                                    ->label('Oktatási módszerek')
                                    ->rows(3),
                            ]),

                        Section::make('8.5. Gyakorlat - Könyvészet')
                            ->schema([
                                Repeater::make('editable_data.content_practice.bibliography_practice')
                                    ->label('Könyvészet')
                                    ->schema([
                                        Textarea::make('citation')
                                            ->label('Bibliográfiai hivatkozás')
                                            ->required()
                                            ->rows(2),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel('Hivatkozás hozzáadása'),
                            ]),
                    ])
                    ->hidden(fn(?CourseSyllabusContent $record) =>
                        !$record || !$record->courseAssignment || ($record->courseAssignment->curriculumCourse->practice_hours ?? 0) == 0
                    ),

                // Step 8: Alignment
                Wizard\Step::make('alignment')
                    ->label('Összehangolás')
                    ->schema([
                        Textarea::make('editable_data.alignment.alignment_content')
                            ->label('9.A tantárgy tartalmának összehangolása az oktatásszervezési elvárásokkal')
                            ->required()
                            ->rows(5),
                    ]),

                // Step 9: Evaluation
                Wizard\Step::make('evaluation')
                    ->label('Értékelés')
                    ->schema([
                        Textarea::make('editable_data.evaluation.evaluation_conditions')
                            ->label('10.A. Az értékelésen való részvétel feltételei')
                            ->rows(3),

                        Section::make('10.B. Kritériumok, módszerek és súlyozás')
                            ->schema([
                                // Curs - Sor 1
                                Section::make('Előadás - Értékelés 1')
                                    ->schema([
                                        Textarea::make('editable_data.evaluation.course_evaluation_criteria_1')
                                            ->label('Értékelési kritériumok')
                                            ->rows(2),
                                        Textarea::make('editable_data.evaluation.course_evaluation_methods_1')
                                            ->label('Értékelési módszerek')
                                            ->rows(2),
                                        TextInput::make('editable_data.evaluation.course_evaluation_weight_1')
                                            ->label('Súly')
                                            ->suffix('%'),
                                    ])->columns(3),

                                // Curs - Sor 2
                                Section::make('Előadás - Értékelés 2')
                                    ->schema([
                                        Textarea::make('editable_data.evaluation.course_evaluation_criteria_2')
                                            ->label('Értékelési kritériumok')
                                            ->rows(2),
                                        Textarea::make('editable_data.evaluation.course_evaluation_methods_2')
                                            ->label('Értékelési módszerek')
                                            ->rows(2),
                                        TextInput::make('editable_data.evaluation.course_evaluation_weight_2')
                                            ->label('Súly')
                                            ->suffix('%'),
                                    ])->columns(3),

                                // Seminar
                                Section::make('Szeminárium')
                                    ->schema([
                                        Textarea::make('editable_data.evaluation.seminar_evaluation_criteria')
                                            ->label('Értékelési kritériumok')
                                            ->rows(2),
                                        Textarea::make('editable_data.evaluation.seminar_evaluation_methods')
                                            ->label('Értékelési módszerek')
                                            ->rows(2),
                                        TextInput::make('editable_data.evaluation.seminar_evaluation_weight')
                                            ->label('Súly')
                                            ->suffix('%'),
                                    ])->columns(3),

                                // Laborator
                                Section::make('Laboratórium')
                                    ->schema([
                                        Textarea::make('editable_data.evaluation.laboratory_evaluation_criteria')
                                            ->label('Értékelési kritériumok')
                                            ->rows(2),
                                        Textarea::make('editable_data.evaluation.laboratory_evaluation_methods')
                                            ->label('Értékelési módszerek')
                                            ->rows(2),
                                        TextInput::make('editable_data.evaluation.laboratory_evaluation_weight')
                                            ->label('Súly')
                                            ->suffix('%'),
                                    ])->columns(3),

                                // Proiect
                                Section::make('Projekt')
                                    ->schema([
                                        Textarea::make('editable_data.evaluation.project_evaluation_criteria')
                                            ->label('Értékelési kritériumok')
                                            ->rows(2),
                                        Textarea::make('editable_data.evaluation.project_evaluation_methods')
                                            ->label('Értékelési módszerek')
                                            ->rows(2),
                                        TextInput::make('editable_data.evaluation.project_evaluation_weight')
                                            ->label('Súly')
                                            ->suffix('%'),
                                    ])->columns(3),

                                // Practică
                                Section::make('Gyakorlat')
                                    ->schema([
                                        Textarea::make('editable_data.evaluation.practice_evaluation_criteria')
                                            ->label('Értékelési kritériumok')
                                            ->rows(2),
                                        Textarea::make('editable_data.evaluation.practice_evaluation_methods')
                                            ->label('Értékelési módszerek')
                                            ->rows(2),
                                        TextInput::make('editable_data.evaluation.practice_evaluation_weight')
                                            ->label('Súly')
                                            ->suffix('%'),
                                    ])->columns(3),
                            ]),

                        Textarea::make('editable_data.evaluation.minimum_performance_standards')
                            ->label('10.6. Minimális teljesítménystandard')
                            ->required()
                            ->rows(3),
                    ]),

                // Step 10: Preview & Finalize
                // Pasul 10: Previzualizare și Finalizare
                Wizard\Step::make('preview')
                    ->label('Előnézet és véglegesítés')
                    ->schema([
                        Placeholder::make('preview_info')
                            ->label('Fontos információk')
                            ->content('Kérjük, véglegesítés előtt ellenőrizze a tantárgyi adatlap tartalmát. Zárolás után minden további módosítás adminisztrátori jóváhagyást igényel.'),

                        DatePicker::make('editable_data.signatures.appr_date')
                            ->label('A tanszéki jóváhagyás dátuma')
                            ->default(now()),

                        Toggle::make('is_locked')
                            ->label('A tantárgyi adatlap véglegesítése és zárolása')
                            ->helperText('Véglegesítés után a tartalom szerkesztése csak adminisztrátori jóváhagyással lehetséges'),
                    ]),
            ])
            ->persistStepInQueryString()
            ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('courseAssignment.curriculumCourse.curriculum.academicYear.year_code')
                    ->label('Tanév')
                    ->sortable(),

                TextColumn::make('courseAssignment.curriculumCourse.curriculum.program.name_hu')
                    ->label('Szak')
                    ->sortable(),



                TextColumn::make('courseAssignment.curriculumCourse.course_name_hu')
                    ->label('Tantárgy neve')
                    ->searchable()
                    ->sortable(),



                BadgeColumn::make('status')
                    ->label('Státusz')
                    ->colors([
                        'warning' => 'draft',
                        'success' => 'approved',
                    ])
                    ->formatStateUsing(function ($state) {
                        return $state === 'draft' ? 'Vázlat' : 'Jóváhagyva';
                    })
                    ->sortable(),



                TextColumn::make('updated_at')
                    ->label('Utolsó módosítás')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('program_id')
                    ->label('Szak (Program)')
                    ->relationship('courseAssignment.curriculumCourse.curriculum.program', 'name_hu')
                    ->searchable()
                    ->multiple()
                    ->preload(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourseSyllabusContents::route('/'),
            'create' => CreateCourseSyllabusContent::route('/create'),
            'edit' => EditCourseSyllabusContent::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getEloquentQuery()->count();
    }

    /**
     * Retrieve the eloquent query, applying additional filters for users with the "teacher" role.
     * Filters the query based on the teacher's associated course assignments (`course_leader_id`,
     * `seminar_leader_id`, `lab_leader_id`, `project_leader_id`). If the user is a teacher without
     * an associated teacher record, no results will be returned.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && $user->hasRole('teacher')) {
            $teacher = $user->teacher;

            if ($teacher) {
                $query->whereHas('courseAssignment', function ($q) use ($teacher) {
                    $q->where('course_leader_id', $teacher->id)
                      ->orWhere('seminar_leader_id', $teacher->id)
                      ->orWhere('lab_leader_id', $teacher->id)
                      ->orWhere('project_leader_id', $teacher->id);
                });
            } else {
                // Ha nincs rekord akkor üresen marad
                $query->whereRaw('1 = 0');
            }
        } elseif ($user && $user->hasRole('department_admin')) {
            $departmentId = $user->getDepartmentId();

            if ($departmentId) {
                $query->whereHas('courseAssignment.curriculumCourse.curriculum.program', function ($q) use ($departmentId) {
                    $q->where('department_id', $departmentId);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }
}
