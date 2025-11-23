<?php

namespace App\Http\Controllers\AcademicProcesses;

use App\Http\Controllers\Controller;
use IncadevUns\CoreDomain\Models\Course;
use IncadevUns\CoreDomain\Models\CourseVersion;
use IncadevUns\CoreDomain\Models\Enrollment;
use IncadevUns\CoreDomain\Enums\EnrollmentAcademicStatus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CourseController extends Controller
{
    /**
     * Display a listing of courses with filters and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));
        $perPage = (int) $request->get('per_page', 10);

        // Consulta base con todas las relaciones necesarias
        $query = Course::query()
            ->select([
                'courses.id',
                'courses.name',
                'courses.description',
                'courses.image_path',
                'courses.created_at',
                'courses.updated_at',
            ])
            ->withCount(['versions as versions_count'])
            ->withCount([
                'versions as active_versions' => function ($q) {
                    $q->where('status', 'published');
                }
            ]);

        // Filtro de búsqueda
        if ($search !== '') {
            $searchLower = Str::lower($search);
            $query->where(function ($q) use ($searchLower) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$searchLower}%"])
                    ->orWhereRaw('LOWER(description) LIKE ?', ["%{$searchLower}%"]);
            });
        }

        // Ordenar por ID descendente
        $query->orderByDesc('courses.id');

        // Obtener resultados paginados
        $courses = $query->paginate($perPage);

        // Obtener IDs de cursos para calcular estudiantes
        $courseIds = collect($courses->items())->pluck('id')->toArray();

        $studentsPerCourse = [];
        if (!empty($courseIds)) {
            $studentsPerCourse = DB::table('enrollments')
                ->join('groups', 'enrollments.group_id', '=', 'groups.id')
                ->join('course_versions', 'groups.course_version_id', '=', 'course_versions.id')
                ->whereIn('course_versions.course_id', $courseIds)
                ->select('course_versions.course_id', DB::raw('COUNT(DISTINCT enrollments.user_id) as total_students'))
                ->groupBy('course_versions.course_id')
                ->get()
                ->keyBy('course_id');
        }

        // Transformar datos para el frontend
        $transformedCourses = collect($courses->items())->map(function ($course) use ($studentsPerCourse) {
            $studentData = $studentsPerCourse->get($course->id);

            return [
                'id' => $course->id,
                'name' => $course->name,
                'description' => $course->description,
                'image_path' => $course->image_path,
                'versions_count' => $course->versions_count ?? 0,
                'active_versions' => $course->active_versions ?? 0,
                'total_students' => $studentData ? (int) $studentData->total_students : 0,
                'created_at' => $course->created_at->toIso8601String(),
                'updated_at' => $course->updated_at->toIso8601String(),
            ];
        });

        return response()->json([
            'courses' => $transformedCourses,
            'stats' => $this->calculateStats(),
            'pagination' => [
                'current_page' => $courses->currentPage(),
                'last_page' => $courses->lastPage(),
                'per_page' => $courses->perPage(),
                'total' => $courses->total(),
                'from' => $courses->firstItem(),
                'to' => $courses->lastItem(),
            ]
        ]);
    }

    /**
     * Get course statistics
     */
    public function statistics(): JsonResponse
    {
        return response()->json($this->calculateStats());
    }

    /**
     * Calculate statistics for courses
     */
    private function calculateStats(): array
    {
        $totalCourses = Course::count();

        $totalVersions = CourseVersion::count();

        $activeCourses = Course::whereHas('versions', function ($q) {
            $q->where('status', 'published');
        })->count();

        // Total de estudiantes únicos en todos los cursos
        $totalStudents = DB::table('enrollments')
            ->distinct('user_id')
            ->count('user_id');

        // Cursos con más matriculados
        $topCourses = DB::table('courses')
            ->join('course_versions', 'courses.id', '=', 'course_versions.course_id')
            ->join('groups', 'course_versions.id', '=', 'groups.course_version_id')
            ->join('enrollments', 'groups.id', '=', 'enrollments.group_id')
            ->select('courses.name', DB::raw('COUNT(DISTINCT enrollments.user_id) as students_count'))
            ->groupBy('courses.id', 'courses.name')
            ->orderByDesc('students_count')
            ->limit(5)
            ->get();

        return [
            'total_courses' => $totalCourses,
            'total_versions' => $totalVersions,
            'total_students' => $totalStudents,
            'active_courses' => $activeCourses,
            'top_courses' => $topCourses,
        ];
    }

    /**
     * Display the specified course
     */
    public function show(string $id): JsonResponse
    {
        $course = Course::with(['versions'])
            ->withCount(['versions as versions_count'])
            ->withCount([
                'versions as active_versions' => function ($q) {
                    $q->where('status', 'published');
                }
            ])
            ->find($id);

        if (!$course) {
            return response()->json(['error' => 'Curso no encontrado'], 404);
        }

        // Calcular total de estudiantes
        $totalStudents = DB::table('enrollments')
            ->join('groups', 'enrollments.group_id', '=', 'groups.id')
            ->join('course_versions', 'groups.course_version_id', '=', 'course_versions.id')
            ->where('course_versions.course_id', $id)
            ->distinct('enrollments.user_id')
            ->count('enrollments.user_id');

        $courseData = [
            'id' => $course->id,
            'name' => $course->name,
            'description' => $course->description,
            'image_path' => $course->image_path,
            'versions_count' => $course->versions_count ?? 0,
            'active_versions' => $course->active_versions ?? 0,
            'total_students' => $totalStudents,
            'created_at' => $course->created_at->toIso8601String(),
            'updated_at' => $course->updated_at->toIso8601String(),
            'versions' => $course->versions->map(function ($version) {
                return [
                    'id' => $version->id,
                    'version' => $version->version,
                    'name' => $version->name,
                    'price' => (float) $version->price,
                    'status' => $version->status,
                    'created_at' => $version->created_at->toIso8601String(),
                ];
            }),
        ];

        return response()->json(['data' => $courseData]);
    }

    /**
     * Store a newly created course
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image_path' => 'nullable|string|max:255',
        ], [
            'name.required' => 'El nombre del curso es obligatorio',
            'name.max' => 'El nombre no puede exceder 255 caracteres',
        ]);

        DB::beginTransaction();
        try {
            // Verificar si ya existe un curso con el mismo nombre
            $existingCourse = Course::whereRaw('LOWER(name) = ?', [Str::lower($validated['name'])])->first();

            if ($existingCourse) {
                return response()->json([
                    'error' => 'Ya existe un curso con ese nombre'
                ], 422);
            }

            $course = Course::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'image_path' => $validated['image_path'] ?? null,
            ]);

            DB::commit();
            Log::info('Curso creado', ['course_id' => $course->id, 'name' => $course->name]);

            return response()->json([
                'message' => 'Curso creado exitosamente',
                'data' => $course
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear curso', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Error al crear el curso: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified course
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $course = Course::find($id);

        if (!$course) {
            return response()->json(['error' => 'Curso no encontrado'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'image_path' => 'nullable|string|max:255',
        ], [
            'name.required' => 'El nombre del curso es obligatorio',
            'name.max' => 'El nombre no puede exceder 255 caracteres',
        ]);

        DB::beginTransaction();
        try {
            // Verificar si el nuevo nombre ya existe en otro curso
            if (isset($validated['name'])) {
                $existingCourse = Course::whereRaw('LOWER(name) = ?', [Str::lower($validated['name'])])
                    ->where('id', '!=', $id)
                    ->first();

                if ($existingCourse) {
                    return response()->json([
                        'error' => 'Ya existe otro curso con ese nombre'
                    ], 422);
                }
            }

            $updateData = [];

            if (isset($validated['name'])) {
                $updateData['name'] = $validated['name'];
            }

            if (array_key_exists('description', $validated)) {
                $updateData['description'] = $validated['description'];
            }

            if (array_key_exists('image_path', $validated)) {
                $updateData['image_path'] = $validated['image_path'];
            }

            $course->update($updateData);

            DB::commit();
            Log::info('Curso actualizado', ['course_id' => $course->id]);

            return response()->json([
                'success' => true,
                'message' => 'Curso actualizado exitosamente',
                'data' => $course
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar curso', ['course_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Error al actualizar el curso: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified course
     */
    public function destroy(string $id): JsonResponse
    {
        $course = Course::find($id);

        if (!$course) {
            return response()->json(['error' => 'Curso no encontrado'], 404);
        }

        DB::beginTransaction();
        try {
            // Verificar si tiene estudiantes matriculados (cualquier estado)
            $activeEnrollments = DB::table('enrollments')
                ->join('groups', 'enrollments.group_id', '=', 'groups.id')
                ->join('course_versions', 'groups.course_version_id', '=', 'course_versions.id')
                ->where('course_versions.course_id', $id)
                ->count();

            if ($activeEnrollments > 0) {
                DB::rollBack();
                return response()->json([
                    'error' => 'No se puede eliminar el curso porque tiene estudiantes matriculados'
                ], 400);
            }

            // Eliminar el curso
            $course->delete();

            DB::commit();
            Log::info('Curso eliminado', ['course_id' => $id, 'name' => $course->name]);

            return response()->json([
                'success' => true,
                'message' => 'Curso eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar curso', ['course_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Error al eliminar el curso: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export courses to CSV
     */
    public function exportCsv(Request $request)
    {
        $courses = Course::with(['versions'])
            ->withCount(['versions as versions_count'])
            ->withCount([
                'versions as active_versions' => function ($q) {
                    $q->where('status', 'published');
                }
            ])
            ->orderBy('name')
            ->get();

        $filename = 'cursos_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($courses) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM para UTF-8

            fputcsv($handle, [
                'ID',
                'Nombre',
                'Descripción',
                'Total Versiones',
                'Versiones Activas',
                'Total Estudiantes',
                'Fecha de Creación'
            ]);

            foreach ($courses as $course) {
                // Calcular estudiantes por curso
                $totalStudents = DB::table('enrollments')
                    ->join('groups', 'enrollments.group_id', '=', 'groups.id')
                    ->join('course_versions', 'groups.course_version_id', '=', 'course_versions.id')
                    ->where('course_versions.course_id', $course->id)
                    ->distinct('enrollments.user_id')
                    ->count('enrollments.user_id');

                fputcsv($handle, [
                    $course->id,
                    $course->name,
                    $course->description ?? 'Sin descripción',
                    $course->versions_count ?? 0,
                    $course->active_versions ?? 0,
                    $totalStudents,
                    Carbon::parse($course->created_at)->format('d/m/Y H:i'),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Get data for PDF export (used by frontend)
     */
    public function getExportData()
    {
        $courses = Course::with(['versions'])
            ->withCount(['versions as versions_count'])
            ->withCount([
                'versions as active_versions' => function ($q) {
                    $q->where('status', 'published');
                }
            ])
            ->orderBy('name')
            ->get();

        $transformedCourses = $courses->map(function ($course) {
            // Calcular estudiantes por curso
            $totalStudents = DB::table('enrollments')
                ->join('groups', 'enrollments.group_id', '=', 'groups.id')
                ->join('course_versions', 'groups.course_version_id', '=', 'course_versions.id')
                ->where('course_versions.course_id', $course->id)
                ->distinct('enrollments.user_id')
                ->count('enrollments.user_id');

            return [
                'id' => $course->id,
                'name' => $course->name,
                'description' => $course->description ?? 'Sin descripción',
                'versions_count' => $course->versions_count ?? 0,
                'active_versions' => $course->active_versions ?? 0,
                'total_students' => $totalStudents,
                'created_at' => Carbon::parse($course->created_at)->format('d/m/Y'),
            ];
        });

        return response()->json([
            'courses' => $transformedCourses,
            'stats' => $this->calculateStats(),
            'generated_at' => Carbon::now()->toIso8601String(),
        ]);
    }

    /**
     * Redirect to PDF export view
     */
    /**
     * Get data for PDF export
     */
    public function exportPdf(): JsonResponse
    {
        $courses = Course::with(['versions'])
            ->withCount(['versions as versions_count'])
            ->withCount([
                'versions as active_versions' => function ($q) {
                    $q->where('status', 'published');
                }
            ])
            ->orderBy('name')
            ->get();

        $transformedCourses = $courses->map(function ($course) {
            // Calcular estudiantes por curso
            $totalStudents = DB::table('enrollments')
                ->join('groups', 'enrollments.group_id', '=', 'groups.id')
                ->join('course_versions', 'groups.course_version_id', '=', 'course_versions.id')
                ->where('course_versions.course_id', $course->id)
                ->distinct('enrollments.user_id')
                ->count('enrollments.user_id');

            return [
                'id' => $course->id,
                'name' => $course->name,
                'description' => $course->description ?? 'Sin descripción',
                'versions_count' => $course->versions_count ?? 0,
                'active_versions' => $course->active_versions ?? 0,
                'total_students' => $totalStudents,
                'created_at' => Carbon::parse($course->created_at)->format('d/m/Y'),
            ];
        });

        return response()->json([
            'courses' => $transformedCourses,
            'stats' => $this->calculateStats(),
            'generated_at' => Carbon::now()->toIso8601String(),
        ]);
    }
}
