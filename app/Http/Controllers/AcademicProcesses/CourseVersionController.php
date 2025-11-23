<?php

namespace App\Http\Controllers\AcademicProcesses;

use App\Http\Controllers\Controller;
use IncadevUns\CoreDomain\Models\Course;
use IncadevUns\CoreDomain\Models\CourseVersion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CourseVersionController extends Controller
{
    /**
     * Display a listing of course versions with filters and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));
        $perPage = (int) $request->get('per_page', 10);
        $statusFilter = $request->input('status', 'all');
        $courseFilter = $request->input('course_id', 'all');

        // Consulta base
        $query = CourseVersion::query()
            ->with(['course:id,name'])
            ->select([
                'course_versions.id',
                'course_versions.course_id',
                'course_versions.version',
                'course_versions.name',
                'course_versions.price',
                'course_versions.status',
                'course_versions.created_at',
                'course_versions.updated_at',
            ])
            ->withCount(['modules as modules_count'])
            ->withCount(['groups as groups_count']);

        // Filtro de búsqueda
        if ($search !== '') {
            $searchLower = Str::lower($search);
            $query->where(function ($q) use ($searchLower) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$searchLower}%"])
                    ->orWhereRaw('LOWER(version) LIKE ?', ["%{$searchLower}%"])
                    ->orWhereHas('course', function ($q) use ($searchLower) {
                        $q->whereRaw('LOWER(name) LIKE ?', ["%{$searchLower}%"]);
                    });
            });
        }

        // Filtro por estado
        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        // Filtro por curso
        if ($courseFilter !== 'all') {
            $query->where('course_id', $courseFilter);
        }

        // Ordenar por ID descendente
        $query->orderByDesc('course_versions.id');

        // Obtener resultados paginados
        $versions = $query->paginate($perPage);

        // Obtener IDs de versiones para calcular estudiantes
        $versionIds = collect($versions->items())->pluck('id')->toArray();

        $studentsPerVersion = [];
        if (!empty($versionIds)) {
            $studentsPerVersion = DB::table('enrollments')
                ->join('groups', 'enrollments.group_id', '=', 'groups.id')
                ->whereIn('groups.course_version_id', $versionIds)
                ->select('groups.course_version_id', DB::raw('COUNT(DISTINCT enrollments.user_id) as total_students'))
                ->groupBy('groups.course_version_id')
                ->get()
                ->keyBy('course_version_id');
        }

        // Transformar datos
        $transformedVersions = collect($versions->items())->map(function ($version) use ($studentsPerVersion) {
            $studentData = $studentsPerVersion->get($version->id);

            return [
                'id' => $version->id,
                'course_id' => $version->course_id,
                'course' => $version->course ? [
                    'id' => $version->course->id,
                    'name' => $version->course->name,
                ] : null,
                'version' => $version->version,
                'name' => $version->name,
                'price' => (float) $version->price,
                'status' => $version->status,
                'modules_count' => $version->modules_count ?? 0,
                'groups_count' => $version->groups_count ?? 0,
                'students_count' => $studentData ? (int) $studentData->total_students : 0,
                'created_at' => $version->created_at->toIso8601String(),
                'updated_at' => $version->updated_at->toIso8601String(),
            ];
        });

        // Formato estándar de Laravel para paginación
        return response()->json([
            'data' => $transformedVersions,
            'current_page' => $versions->currentPage(),
            'last_page' => $versions->lastPage(),
            'per_page' => $versions->perPage(),
            'total' => $versions->total(),
            'from' => $versions->firstItem(),
            'to' => $versions->lastItem(),
        ]);
    }

    /**
     * Get statistics for course versions
     */
    public function statistics(): JsonResponse
    {
        return response()->json([
            'data' => $this->calculateStats()
        ]);
    }

    /**
     * Calculate statistics
     */
    private function calculateStats(): array
    {
        $totalVersions = CourseVersion::count();
        $publishedVersions = CourseVersion::where('status', 'published')->count();
        $draftVersions = CourseVersion::where('status', 'draft')->count();
        $archivedVersions = CourseVersion::where('status', 'archived')->count();

        return [
            'total_versions' => $totalVersions,
            'published_versions' => $publishedVersions,
            'draft_versions' => $draftVersions,
            'archived_versions' => $archivedVersions,
        ];
    }

    /**
     * Display the specified course version
     */
    public function show(string $id): JsonResponse
    {
        $version = CourseVersion::with(['course:id,name'])
            ->withCount(['modules as modules_count'])
            ->withCount(['groups as groups_count'])
            ->find($id);

        if (!$version) {
            return response()->json(['error' => 'Versión no encontrada'], 404);
        }

        // Calcular estudiantes
        $studentsCount = DB::table('enrollments')
            ->join('groups', 'enrollments.group_id', '=', 'groups.id')
            ->where('groups.course_version_id', $id)
            ->distinct('enrollments.user_id')
            ->count('enrollments.user_id');

        $versionData = [
            'id' => $version->id,
            'course_id' => $version->course_id,
            'course' => $version->course ? [
                'id' => $version->course->id,
                'name' => $version->course->name,
            ] : null,
            'version' => $version->version,
            'name' => $version->name,
            'price' => (float) $version->price,
            'status' => $version->status,
            'modules_count' => $version->modules_count ?? 0,
            'groups_count' => $version->groups_count ?? 0,
            'students_count' => $studentsCount,
            'created_at' => $version->created_at->toIso8601String(),
            'updated_at' => $version->updated_at->toIso8601String(),
        ];

        return response()->json(['data' => $versionData]);
    }

    /**
     * Store a newly created course version
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'version' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'status' => 'required|in:draft,published,archived',
        ], [
            'course_id.required' => 'El curso es obligatorio',
            'course_id.exists' => 'El curso seleccionado no existe',
            'version.required' => 'La versión es obligatoria',
            'name.required' => 'El nombre es obligatorio',
            'price.required' => 'El precio es obligatorio',
            'price.numeric' => 'El precio debe ser un número',
            'status.required' => 'El estado es obligatorio',
            'status.in' => 'El estado debe ser: draft, published o archived',
        ]);

        DB::beginTransaction();
        try {
            // Verificar si ya existe una versión con el mismo nombre
            $existingVersion = CourseVersion::whereRaw('LOWER(name) = ?', [Str::lower($validated['name'])])->first();

            if ($existingVersion) {
                return response()->json([
                    'error' => 'Ya existe una versión con ese nombre'
                ], 422);
            }

            $version = CourseVersion::create($validated);

            DB::commit();
            Log::info('Versión de curso creada', ['version_id' => $version->id]);

            return response()->json([
                'message' => 'Versión creada exitosamente',
                'data' => $version
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear versión', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Error al crear la versión: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified course version
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $version = CourseVersion::find($id);

        if (!$version) {
            return response()->json(['error' => 'Versión no encontrada'], 404);
        }

        $validated = $request->validate([
            'course_id' => 'sometimes|required|exists:courses,id',
            'version' => 'sometimes|required|string|max:255',
            'name' => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric|min:0',
            'status' => 'sometimes|required|in:draft,published,archived',
        ]);

        DB::beginTransaction();
        try {
            // Verificar nombre único
            if (isset($validated['name'])) {
                $existingVersion = CourseVersion::whereRaw('LOWER(name) = ?', [Str::lower($validated['name'])])
                    ->where('id', '!=', $id)
                    ->first();

                if ($existingVersion) {
                    return response()->json([
                        'error' => 'Ya existe otra versión con ese nombre'
                    ], 422);
                }
            }

            $version->update($validated);

            DB::commit();
            Log::info('Versión actualizada', ['version_id' => $version->id]);

            return response()->json([
                'success' => true,
                'message' => 'Versión actualizada exitosamente',
                'data' => $version
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar versión', ['version_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Error al actualizar la versión: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified course version
     */
    public function destroy(string $id): JsonResponse
    {
        $version = CourseVersion::find($id);

        if (!$version) {
            return response()->json(['error' => 'Versión no encontrada'], 404);
        }

        DB::beginTransaction();
        try {
            // Verificar si tiene grupos con estudiantes
            $activeEnrollments = DB::table('enrollments')
                ->join('groups', 'enrollments.group_id', '=', 'groups.id')
                ->where('groups.course_version_id', $id)
                ->count();

            if ($activeEnrollments > 0) {
                DB::rollBack();
                return response()->json([
                    'error' => 'No se puede eliminar la versión porque tiene estudiantes matriculados'
                ], 400);
            }

            $version->delete();

            DB::commit();
            Log::info('Versión eliminada', ['version_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Versión eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar versión', ['version_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Error al eliminar la versión: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of courses for dropdown
     */
    public function getCourses(): JsonResponse
    {
        $courses = Course::select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $courses]);
    }

    /**
     * Export course versions to CSV
     */
    public function exportCsv(Request $request)
    {
        $versions = CourseVersion::with(['course:id,name'])
            ->withCount(['modules as modules_count'])
            ->withCount(['groups as groups_count'])
            ->orderBy('name')
            ->get();

        $filename = 'versiones_cursos_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($versions) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

            fputcsv($handle, [
                'ID',
                'Curso',
                'Versión',
                'Nombre',
                'Precio',
                'Estado',
                'Módulos',
                'Grupos',
                'Estudiantes',
                'Fecha de Creación'
            ]);

            foreach ($versions as $version) {
                $studentsCount = DB::table('enrollments')
                    ->join('groups', 'enrollments.group_id', '=', 'groups.id')
                    ->where('groups.course_version_id', $version->id)
                    ->distinct('enrollments.user_id')
                    ->count('enrollments.user_id');

                fputcsv($handle, [
                    $version->id,
                    $version->course->name ?? 'N/A',
                    $version->version,
                    $version->name,
                    'S/. ' . number_format($version->price, 2),
                    $this->getStatusLabel($version->status),
                    $version->modules_count ?? 0,
                    $version->groups_count ?? 0,
                    $studentsCount,
                    Carbon::parse($version->created_at)->format('d/m/Y H:i'),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Get data for PDF export
     */
    public function exportPdf(): JsonResponse
    {
        $versions = CourseVersion::with(['course:id,name'])
            ->withCount(['modules as modules_count'])
            ->withCount(['groups as groups_count'])
            ->orderBy('name')
            ->get();

        $transformedVersions = $versions->map(function ($version) {
            $studentsCount = DB::table('enrollments')
                ->join('groups', 'enrollments.group_id', '=', 'groups.id')
                ->where('groups.course_version_id', $version->id)
                ->distinct('enrollments.user_id')
                ->count('enrollments.user_id');

            return [
                'id' => $version->id,
                'course_name' => $version->course->name ?? 'N/A',
                'version' => $version->version,
                'name' => $version->name,
                'price' => number_format($version->price, 2),
                'status' => $this->getStatusLabel($version->status),
                'modules_count' => $version->modules_count ?? 0,
                'groups_count' => $version->groups_count ?? 0,
                'students_count' => $studentsCount,
                'created_at' => Carbon::parse($version->created_at)->format('d/m/Y'),
            ];
        });

        return response()->json([
            'versions' => $transformedVersions,
            'stats' => $this->calculateStats(),
            'generated_at' => Carbon::now()->toIso8601String(),
        ]);
    }

    /**
     * Get status label in Spanish
     */
    private function getStatusLabel(string $status): string
    {
        return match($status) {
            'published' => 'Publicado',
            'draft' => 'Borrador',
            'archived' => 'Archivado',
            default => $status,
        };
    }
}