<?php

namespace App\Http\Controllers\AcademicProcesses;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use IncadevUns\CoreDomain\Models\Group;
use IncadevUns\CoreDomain\Models\CourseVersion;
use IncadevUns\CoreDomain\Enums\GroupStatus;

class GroupController extends Controller
{
    /**
     * Obtener todos los grupos con información del curso
     */
    public function index(Request $request)
    {
        try {
            $query = Group::with([
                'courseVersion.course',
                'teachers',
                'enrollments'
            ])->orderBy('start_date', 'desc');

            // Filtros opcionales
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('course_version_id')) {
                $query->where('course_version_id', $request->course_version_id);
            }

            $groups = $query->get()->map(function ($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'course_name' => $group->courseVersion->course->name ?? 'N/A',
                    'course_version_name' => $group->courseVersion->name ?? 'N/A',
                    'course_version_id' => $group->course_version_id,
                    'start_date' => $group->start_date->format('Y-m-d'),
                    'end_date' => $group->end_date->format('Y-m-d'),
                    'status' => $group->status->value,
                    'teachers_count' => $group->teachers->count(),
                    'students_count' => $group->enrollments->count(),
                    'created_at' => $group->created_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $groups
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los grupos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener versiones de cursos para el selector
     */
    public function getCourseVersions()
    {
        try {
            $courseVersions = CourseVersion::with('course')
                ->where('status', 'published')
                ->get()
                ->map(function ($version) {
                    return [
                        'id' => $version->id,
                        'name' => $version->name,
                        'course_name' => $version->course->name ?? 'N/A',
                        'price' => $version->price,
                        'label' => ($version->course->name ?? 'N/A') . ' - ' . $version->name,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $courseVersions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las versiones de cursos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un grupo específico
     */
    public function show($id)
    {
        try {
            $group = Group::with([
                'courseVersion.course',
                'teachers',
                'enrollments.user'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'course_version_id' => $group->course_version_id,
                    'course_name' => $group->courseVersion->course->name ?? 'N/A',
                    'course_version_name' => $group->courseVersion->name ?? 'N/A',
                    'start_date' => $group->start_date->format('Y-m-d'),
                    'end_date' => $group->end_date->format('Y-m-d'),
                    'status' => $group->status->value,
                    'teachers' => $group->teachers,
                    'students' => $group->enrollments->map(fn($e) => $e->user),
                    'created_at' => $group->created_at,
                    'updated_at' => $group->updated_at,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el grupo',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Crear un nuevo grupo
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'course_version_id' => 'required|exists:course_versions,id',
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'nullable|in:' . implode(',', GroupStatus::values()),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Verificar que no exista un grupo con el mismo nombre para la misma versión
            $exists = Group::where('course_version_id', $request->course_version_id)
                ->where('name', $request->name)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe un grupo con ese nombre para este curso'
                ], 422);
            }

            // CORRECCIÓN: Usar GroupStatus::Pending como valor por defecto
            $group = Group::create([
                'course_version_id' => $request->course_version_id,
                'name' => $request->name,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'status' => $request->status ?? GroupStatus::Pending->value,
            ]);

            $group->load('courseVersion.course');

            return response()->json([
                'success' => true,
                'message' => 'Grupo creado exitosamente',
                'data' => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'course_name' => $group->courseVersion->course->name ?? 'N/A',
                    'course_version_name' => $group->courseVersion->name ?? 'N/A',
                    'start_date' => $group->start_date->format('Y-m-d'),
                    'end_date' => $group->end_date->format('Y-m-d'),
                    'status' => $group->status->value,
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un grupo existente
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'course_version_id' => 'sometimes|required|exists:course_versions,id',
            'name' => 'sometimes|required|string|max:255',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'status' => 'sometimes|required|in:' . implode(',', GroupStatus::values()),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $group = Group::findOrFail($id);

            // Verificar duplicados si se cambia el nombre o versión
            if ($request->has('name') || $request->has('course_version_id')) {
                $courseVersionId = $request->course_version_id ?? $group->course_version_id;
                $name = $request->name ?? $group->name;

                $exists = Group::where('course_version_id', $courseVersionId)
                    ->where('name', $name)
                    ->where('id', '!=', $id)
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ya existe un grupo con ese nombre para este curso'
                    ], 422);
                }
            }

            $group->update($request->all());
            $group->load('courseVersion.course');

            return response()->json([
                'success' => true,
                'message' => 'Grupo actualizado exitosamente',
                'data' => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'course_name' => $group->courseVersion->course->name ?? 'N/A',
                    'course_version_name' => $group->courseVersion->name ?? 'N/A',
                    'start_date' => $group->start_date->format('Y-m-d'),
                    'end_date' => $group->end_date->format('Y-m-d'),
                    'status' => $group->status->value,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un grupo
     */
    public function destroy($id)
    {
        try {
            $group = Group::findOrFail($id);

            // Verificar si tiene estudiantes matriculados
            $hasEnrollments = $group->enrollments()->exists();
            
            if ($hasEnrollments) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el grupo porque tiene estudiantes matriculados'
                ], 400);
            }

            // Verificar si tiene sesiones de clase
            $hasClassSessions = $group->classSessions()->exists();
            
            if ($hasClassSessions) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el grupo porque tiene sesiones de clase programadas'
                ], 400);
            }

            $group->delete();

            return response()->json([
                'success' => true,
                'message' => 'Grupo eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de un grupo
     */
    public function statistics($id)
    {
        try {
            $group = Group::with([
                'enrollments',
                'classSessions',
                'exams',
                'teachers'
            ])->findOrFail($id);

            $stats = [
                'total_students' => $group->enrollments->count(),
                'total_teachers' => $group->teachers->count(),
                'total_sessions' => $group->classSessions->count(),
                'total_exams' => $group->exams->count(),
                'completed_sessions' => $group->classSessions()
                    ->where('end_time', '<', now())
                    ->count(),
                'upcoming_sessions' => $group->classSessions()
                    ->where('start_time', '>', now())
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener opciones de estado para formularios
     */
    public function getStatusOptions()
    {
        try {
            $statusOptions = collect(GroupStatus::cases())->map(function ($status) {
                return [
                    'value' => $status->value,
                    'label' => $this->getStatusLabel($status),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $statusOptions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener opciones de estado',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper para obtener etiquetas legibles de los estados
     */
    private function getStatusLabel(GroupStatus $status): string
    {
        return match ($status) {
            GroupStatus::Pending => 'Pendiente',
            GroupStatus::Enrolling => 'En Matrícula',
            GroupStatus::Active => 'Activo',
            GroupStatus::Completed => 'Completado',
            GroupStatus::Cancelled => 'Cancelado',
        };
    }
}