<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumCourse extends Model
{
    use HasFactory;

    public const string ACTIVITY_INTEGRAL = 'Asistat integral';

    public const string ACTIVITY_PARTIAL = 'Asistat parțial';

    public const string ACTIVITY_UNASSISTED = 'Neasistat';

    /** @var list<string> */
    public const array CURRICULUM_JSON_FILES = [
        'curriculum_data.json',
        'curriculum_data_2023_24.json',
        'curriculum_data_2024_25.json',
        'marketing_curriculum_data.json',
    ];

    protected $table = 'curriculum_courses';

    protected $fillable = [
        'curriculum_id',
        'course_code',
        'course_name_hu',
        'course_name_ro',
        'course_name_en',
        'study_year',
        'semester',
        'credits',
        'lecture_hours',
        'lecture_hours_online',
        'seminar_hours',
        'seminar_hours_online',
        'lab_hours',
        'lab_hours_online',
        'project_hours',
        'project_hours_online',
        'course_type',
        'formative_category',
        'master_category',
        'exam_type',
        'activity_type',
        'learning_outcomes_knowledge',
        'learning_outcomes_skills',
        'learning_outcomes_responsibility',
    ];

    protected $casts = [
        'learning_outcomes_knowledge' => 'array',
        'learning_outcomes_skills' => 'array',
        'learning_outcomes_responsibility' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (CurriculumCourse $course) {
            if (blank($course->activity_type)) {
                $course->activity_type = static::inferActivityTypeFromHours([
                    'lecture_hours' => $course->lecture_hours,
                    'seminar_hours' => $course->seminar_hours,
                    'lab_hours' => $course->lab_hours,
                    'project_hours' => $course->project_hours,
                ]);
            }
        });
    }

    /**
     * @return list<string>
     */
    public static function allowedActivityTypes(): array
    {
        return [
            self::ACTIVITY_INTEGRAL,
            self::ACTIVITY_PARTIAL,
            self::ACTIVITY_UNASSISTED,
        ];
    }

    public static function normalizeActivityType(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = trim($value);

        foreach (self::allowedActivityTypes() as $allowed) {
            if (strcasecmp($allowed, $normalized) === 0) {
                return $allowed;
            }
        }

        return $normalized;
    }


    public static function resolveActivityTypeFromCourseData(array $courseData): string
    {
        $explicit = $courseData['activity_type'] ?? null;
        if (is_string($explicit) && trim($explicit) !== '') {
            return self::normalizeActivityType($explicit) ?? trim($explicit);
        }

        return self::inferActivityTypeFromHours($courseData);
    }

    public function resolvedActivityType(): string
    {
        if (is_string($this->activity_type) && trim($this->activity_type) !== '') {
            return self::normalizeActivityType($this->activity_type) ?? trim($this->activity_type);
        }

        return self::inferActivityTypeFromHours([
            'lecture_hours' => $this->lecture_hours,
            'seminar_hours' => $this->seminar_hours,
            'lab_hours' => $this->lab_hours,
            'project_hours' => $this->project_hours,
        ]);
    }

    /**
     * @param  array<string, mixed>  $courseData
     */
    public static function inferActivityTypeFromHours(array $courseData): string
    {
        $lecture = (int) ($courseData['lecture_hours'] ?? 0);
        $assisted = (int) ($courseData['seminar_hours'] ?? 0)
            + (int) ($courseData['lab_hours'] ?? 0)
            + (int) ($courseData['project_hours'] ?? 0);

        if ($assisted > 0) {
            return self::ACTIVITY_INTEGRAL;
        }

        return self::ACTIVITY_UNASSISTED;
    }

    public function matchesActivityCheckmark(string $checkmarkType): bool
    {
        return str_contains($this->resolvedActivityType(), $checkmarkType);
    }

    /**
     * Updates the activity types for courses in the system by synchronizing with
     * JSON files and updating missing or empty activity types in the database.
     *
     * If `$jsonOnly` is true, only the JSON files will be updated. If `$dbOnly`
     * is true, only the database records will be updated. Both flags cannot be
     * true at the same time.
     *
     * @param bool $jsonOnly Whether to update only JSON files.
     * @param bool $dbOnly Whether to update only the database.
     * @return array An associative array containing the count of updates made
     *               in JSON files and the database, with keys 'json' and
     *               'database'.
     */
    public static function backfillActivityTypes(bool $jsonOnly = false, bool $dbOnly = false): array
    {
        $jsonUpdated = 0;
        $dbUpdated = 0;

        if (! $dbOnly) {
            foreach (self::CURRICULUM_JSON_FILES as $relativePath) {
                $jsonUpdated += self::syncActivityTypeInJsonFile($relativePath);
            }
        }

        if (! $jsonOnly) {
            self::query()
                ->where(function ($query) {
                    $query->whereNull('activity_type')
                        ->orWhere('activity_type', '');
                })
                ->orderBy('id')
                ->chunkById(100, function ($courses) use (&$dbUpdated) {
                    foreach ($courses as $course) {
                        $course->update(['activity_type' => $course->resolvedActivityType()]);
                        $dbUpdated++;
                    }
                });
        }

        return ['json' => $jsonUpdated, 'database' => $dbUpdated];
    }

    /**
     * Synchronizes the activity type in the JSON file at the specified relative path.
     *
     * This method reads the JSON file, determines if any course data within it requires
     * updating for the "activity_type" field, and updates the file if changes are necessary.
     *
     * @param string $relativePath The relative path to the JSON file.
     *
     * @return int The number of courses whose activity type was updated in the JSON file.
     */
    public static function syncActivityTypeInJsonFile(string $relativePath): int
    {
        $absolutePath = base_path($relativePath);

        if (! file_exists($absolutePath)) {
            return 0;
        }

        $jsonData = json_decode(file_get_contents($absolutePath), true);

        if (! is_array($jsonData) || ! isset($jsonData['courses']) || ! is_array($jsonData['courses'])) {
            return 0;
        }

        $updated = 0;

        foreach ($jsonData['courses'] as $index => $courseData) {
            $resolved = self::resolveActivityTypeFromCourseData($courseData);

            if (($courseData['activity_type'] ?? null) === $resolved) {
                continue;
            }

            $jsonData['courses'][$index]['activity_type'] = $resolved;
            $updated++;
        }

        if ($updated > 0) {
            file_put_contents(
                $absolutePath,
                json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL
            );
        }

        return $updated;
    }

    /**
     * Get the curriculum this course belongs to
     */
    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    /**
     * Get all learning outcomes for this course
     */
    public function learningOutcomes(): HasMany
    {
        return $this->hasMany(LearningOutcome::class);
    }

    /**
     * Get all course assignments for this course
     */
    public function courseAssignments(): HasMany
    {
        return $this->hasMany(CourseAssignment::class);
    }

    /**
     * Get the total hours for this course
     */
    public function getTotalHoursAttribute(): int
    {
        return $this->lecture_hours + $this->seminar_hours + $this->lab_hours + $this->project_hours;
    }

    /**
     * Get the total online hours for this course
     */
    public function getTotalOnlineHoursAttribute(): int
    {
        return $this->lecture_hours_online + $this->seminar_hours_online + $this->lab_hours_online + $this->project_hours_online;
    }
}
