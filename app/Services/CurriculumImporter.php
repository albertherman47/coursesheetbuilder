<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Curriculum;
use App\Models\CurriculumCourse;
use App\Models\Program;
use App\Models\Teacher;
use App\Models\CourseAssignment;
use Illuminate\Support\Facades\DB;

/**
 * CurriculumImporter
 *
 * Feladata: egy JSON fájlból tantervi adatokat importál az adatbázisba.
 * Az import során létrejön:
 * - tanév (AcademicYear)
 * - tanterv (Curriculum)
 * - kurzusok (CurriculumCourse)
 * - tanár-hozzárendelések (CourseAssignment)
 *
 * A teljes művelet tranzakcióban fut, így hiba esetén minden visszagörgetődik.
 */
class CurriculumImporter
{
    /**
     * JSON tanterv importálása fájlból.
     *
     * @param string $absolutePath JSON fájl teljes elérési útja
     * @param bool $force Ha true, felülírja a meglévő tantervet
     *
     * @return array
     *  - status: imported | skipped | failed
     *  - message: visszajelző szöveg
     *  - course_count: sikeresen importált kurzusok száma
     */
    public function importFromFile(string $absolutePath, bool $force = false): array
    {
        // 1. A fájl létezik? Ha nem, hibát adunk.

        if (!file_exists($absolutePath)) {
            return array(
                'status'  => 'failed',
                'message' => "File not found: {$absolutePath}"
            );
        }

        // 2. JSON beolvasása és alap ellenőrzés.
        $jsonText = file_get_contents($absolutePath);
        $data = json_decode($jsonText, true);

        if (!is_array($data) || !isset($data['curriculum']) || !isset($data['courses'])) {
            return array(
                'status'  => 'failed',
                'message' => "Invalid JSON format. Expected 'curriculum' and 'courses' keys."
            );
        }

        // 3. Program és tanév kinyerése a JSON‑ból.

        $programName     = $data['curriculum']['program'];
        $academicYearStr = $data['curriculum']['academic_year'];   // pl. "2023/24"

        // Megkeressük a programot (legyen akár a neve, akár a kódja)
        $program = Program::where('name_hu', $programName)
            ->orWhere('code', 'CBGECO')
            ->first();

        if (!$program) {
            return array(
                'status'  => 'failed',
                'message' => "Program not found for name: {$programName}"
            );
        }

        // 4. Tanév szétválasztása.
        $parts = explode('/', $academicYearStr);
        if (count($parts) != 2) {
            return array(
                'status'  => 'failed',
                'message' => "Invalid academic year format. Expected YYYY/YY. Got: {$academicYearStr}"
            );
        }

        $startYear = (int)$parts[0];
        $endYear   = 2000 + (int)$parts[1];   // pl. "24" -> 2024


        // 5. Tranzakció indítása – ha valami hibázik, visszagörgetünk.
        DB::beginTransaction();
        try {
            // 5.1. AcademicYear rekord létrehozása vagy meglévő visszaadása
            $academicYear = AcademicYear::firstOrCreate(
                array('year_code' => $academicYearStr),
                array(
                    'start_year' => $startYear,
                    'end_year'   => $endYear
                )
            );

            // 5.2. Létező tanterv keresése ( ugyanaz a program + év )
            $existing = Curriculum::where('program_id', $program->id)
                ->where('academic_year_id', $academicYear->id)
                ->first();

            if ($existing && !$force) {
                DB::rollBack();
                return array(
                    'status'  => 'skipped',
                    'message' => "Curriculum for {$program->name_hu} in {$academicYearStr} already exists. Use --force to re-import."
                );
            }

            if ($existing && $force) {
                $existing->delete();
            }

            // 5.3. Új Curriculum rekord létrehozása
            $curriculum = Curriculum::create(array(
                'program_id'        => $program->id,
                'academic_year_id'  => $academicYear->id,
                'name'              => "Plan de învățământ - {$program->name_ro} {$academicYearStr}",
                'hours_per_credit'  => 28
            ));

            // 6. Kurzusok felvétele (a JSON‑ból)
            $courses = array();   // kódból visszakapott kurzus objektumok (kulcs = code)

            foreach ($data['courses'] as $c) {
                $course = CurriculumCourse::create(array(
                    'curriculum_id'               => $curriculum->id,
                    'course_code'                 => $c['code'],
                    'course_name_hu'              => $c['name_hu'],
                    'course_name_ro'              => $c['name_ro'],
                    'course_name_en'              => $c['name_en'],
                    'study_year'                  => $c['year'],
                    'semester'                    => $c['semester'],
                    'credits'                     => $c['credits'],
                    'lecture_hours'               => $c['lecture_hours'],
                    'lecture_hours_online'        => 0,
                    'seminar_hours'               => $c['seminar_hours'],
                    'seminar_hours_online'        => 0,
                    'lab_hours'                   => $c['lab_hours'],
                    'lab_hours_online'            => 0,
                    'project_hours'               => $c['project_hours'],
                    'project_hours_online'        => 0,
                    'course_type'                 => $c['course_type'],
                    'formative_category'          => $c['category'],
                    'exam_type'                   => $c['exam_type'],
                    'activity_type'               => CurriculumCourse::resolveActivityTypeFromCourseData($c),
                    'learning_outcomes_knowledge' => $c['learning_outcomes']['knowledge'] ?? null,
                    'learning_outcomes_skills'    => $c['learning_outcomes']['skills'] ?? null,
                    'learning_outcomes_responsibility' => $c['learning_outcomes']['responsibility_autonomy'] ?? null
                ));

                $courses[$c['code']] = $course;
            }

            // 7. Tanárok hozzárendelése (hard‑coded kódtáblázat)
            $palTeacher   = Teacher::where('neptun_code', 'PAL001')->first();
            $kovacsTeacher = Teacher::where('neptun_code', 'KOV001')->first();

            if ($palTeacher && $kovacsTeacher) {
                $palCodes = array('CBEI0241', 'CBEI0430', 'CBEI0261', 'CBEI0471');
                foreach ($palCodes as $code) {
                    if (isset($courses[$code])) {
                        CourseAssignment::create(array(
                            'curriculum_course_id' => $courses[$code]->id,
                            'course_leader_id'     => $palTeacher->id,
                            'seminar_leader_id'    => null,
                            'lab_leader_id'        => null,
                            'project_leader_id'    => null
                        ));
                    }
                }

                $kovacsCodes = array('CBEM0311', 'CBGG0761');
                foreach ($kovacsCodes as $code) {
                    if (isset($courses[$code])) {
                        CourseAssignment::create(array(
                            'curriculum_course_id' => $courses[$code]->id,
                            'course_leader_id'     => $kovacsTeacher->id,
                            'seminar_leader_id'    => null,
                            'lab_leader_id'        => null,
                            'project_leader_id'    => null
                        ));
                    }
                }
            }


            DB::commit();

            return array(
                'status'       => 'imported',
                'message'      => "Imported curriculum for {$program->name_hu} - {$academicYearStr}.",
                'course_count' => count($courses)
            );
        } catch (\Exception $e) {
            // Valami balul sült el – rollback és hiba visszaadása
            DB::rollBack();
            return array(
                'status'  => 'failed',
                'message' => 'Import failed: ' . $e->getMessage()
            );
        }
    }
}
