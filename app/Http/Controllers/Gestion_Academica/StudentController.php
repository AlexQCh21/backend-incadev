<?php

namespace App\Http\Controllers\Gestion_Academica;

use App\Http\Controllers\Controller;
use App\Models\User;
use IncadevUns\CoreDomain\Models\StudentProfile;
use IncadevUns\CoreDomain\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class StudentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        // Verificar permisos (comentado temporalmente para pruebas)
        // if (!auth()->user()->hasRole(['admin', 'enrollment_manager'])) {
        //     return response()->json(['error' => 'Unauthorized'], 403);
        // }

        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status', 'all');
        $perPage = (int) $request->get('per_page', 15);

        // Obtener el role_id del rol 'student' (según tu BD es el ID 18)
        $studentRoleId = Role::where('name', 'student')->value('id');

        // Obtener SOLO los user_ids que tienen rol student
        $studentUserIds = DB::table('model_has_roles')
            ->where('role_id', $studentRoleId)
            ->where('model_type', 'App\\Models\\User')
            ->pluck('model_id')
            ->toArray();

        // Consulta base solo para estudiantes
        $query = User::query()
            ->whereIn('users.id', $studentUserIds)
            ->leftJoin('student_profiles', 'users.id', '=', 'student_profiles.user_id')
            ->select([
                'users.id',
                'users.name',
                'users.fullname',
                'users.dni',
                'users.email',
                'users.phone',
                'users.avatar',
                'users.created_at',
                'student_profiles.interests',
                'student_profiles.learning_goal',
            ]);

        // Filtro de búsqueda
        if ($search !== '') {
            $searchLower = Str::lower($search);
            $query->where(function ($q) use ($search, $searchLower) {
                $q->whereRaw('LOWER(users.name) LIKE ?', ["%{$searchLower}%"])
                    ->orWhereRaw('LOWER(users.fullname) LIKE ?', ["%{$searchLower}%"])
                    ->orWhereRaw('LOWER(users.email) LIKE ?', ["%{$searchLower}%"])
                    ->orWhere('users.dni', 'like', "%{$search}%");
            });
        }

        // Filtro por estado académico
        if ($status !== 'all' && in_array($status, ['active', 'inactive'])) {
            $query->whereExists(function ($subQuery) use ($status) {
                $subQuery->select(DB::raw(1))
                    ->from('enrollments')
                    ->whereColumn('enrollments.user_id', 'users.id')
                    ->where('enrollments.academic_status', $status);
            });
        }

        // Ordenar por ID descendente por defecto
        $query->orderByDesc('users.id');

        // Obtener resultados paginados
        $students = $query->paginate($perPage);

        // Enriquecer datos con enrollments
        $studentIds = collect($students->items())->pluck('id')->toArray();

        if (!empty($studentIds)) {
            $enrollmentsData = DB::table('enrollments')
                ->join('groups', 'enrollments.group_id', '=', 'groups.id')
                ->join('course_versions', 'groups.course_version_id', '=', 'course_versions.id')
                ->join('courses', 'course_versions.course_id', '=', 'courses.id')
                ->whereIn('enrollments.user_id', $studentIds)
                ->select([
                    'enrollments.id',
                    'enrollments.user_id',
                    'enrollments.academic_status',
                    'enrollments.payment_status',
                    'courses.name as course_name',
                ])
                ->get()
                ->groupBy('user_id');

            // Agregar enrollments a cada estudiante
            foreach ($students->items() as $student) {
                $student->enrollments = $enrollmentsData->get($student->id, collect())->map(function ($enrollment) {
                    return [
                        'id' => $enrollment->id,
                        'academic_status' => $enrollment->academic_status,
                        'payment_status' => $enrollment->payment_status,
                        'group' => [
                            'course_version' => [
                                'course' => [
                                    'name' => $enrollment->course_name
                                ]
                            ]
                        ]
                    ];
                })->toArray();

                // Parsear interests si es JSON
                if ($student->interests && is_string($student->interests)) {
                    $student->interests = json_decode($student->interests, true);
                }

                // Agregar student_profile
                $student->student_profile = [
                    'interests' => $student->interests,
                    'learning_goal' => $student->learning_goal
                ];

                // Limpiar campos temporales
                unset($student->interests);
                unset($student->learning_goal);
            }
        }

        return response()->json([
            'data' => $students->items(),
            'current_page' => $students->currentPage(),
            'last_page' => $students->lastPage(),
            'per_page' => $students->perPage(),
            'total' => $students->total(),
            'from' => $students->firstItem(),
            'to' => $students->lastItem(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        /*if (!auth()->user()->hasRole(['admin', 'enrollment_manager'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }*/

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'dni' => 'required|string|size:8|unique:users,dni',
            'fullname' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'password' => 'required|string|min:8',
            'interests' => 'nullable|array',
            'learning_goal' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Crear usuario
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'dni' => $validated['dni'],
                'fullname' => $validated['fullname'],
                'phone' => $validated['phone'] ?? null,
                'password' => bcrypt($validated['password']),
            ]);

            // Asignar rol de estudiante
            $studentRole = Role::where('name', 'student')->first();
            if (!$studentRole) {
                throw new \Exception('Rol de estudiante no encontrado');
            }
            $user->assignRole($studentRole);

            // Crear perfil de estudiante
            StudentProfile::create([
                'user_id' => $user->id,
                'interests' => $validated['interests'] ?? null,
                'learning_goal' => $validated['learning_goal'] ?? null,
            ]);

            DB::commit();
            Log::info('Estudiante creado', ['user_id' => $user->id, 'email' => $user->email]);

            return response()->json([
                'message' => 'Estudiante creado exitosamente',
                'data' => $user
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear estudiante', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Error al crear el estudiante: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        /*   if (!auth()->user()->hasRole(['admin', 'enrollment_manager'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }*/

        $studentRoleId = Role::where('name', 'student')->value('id');

        $student = User::query()
            ->join('model_has_roles', function ($join) use ($studentRoleId) {
                $join->on('users.id', '=', 'model_has_roles.model_id')
                    ->where('model_has_roles.model_type', '=', 'App\\Models\\User')
                    ->where('model_has_roles.role_id', '=', $studentRoleId);
            })
            ->leftJoin('student_profiles', 'users.id', '=', 'student_profiles.user_id')
            ->where('users.id', $id)
            ->select([
                'users.id',
                'users.name',
                'users.fullname',
                'users.dni',
                'users.email',
                'users.phone',
                'users.avatar',
                'users.created_at',
                'student_profiles.interests',
                'student_profiles.learning_goal',
            ])
            ->first();

        if (!$student) {
            return response()->json(['error' => 'Estudiante no encontrado'], 404);
        }

        // Obtener enrollments con pagos
        $enrollments = DB::table('enrollments')
            ->join('groups', 'enrollments.group_id', '=', 'groups.id')
            ->join('course_versions', 'groups.course_version_id', '=', 'course_versions.id')
            ->join('courses', 'course_versions.course_id', '=', 'courses.id')
            ->leftJoin('enrollment_payments', 'enrollments.id', '=', 'enrollment_payments.enrollment_id')
            ->where('enrollments.user_id', $id)
            ->select([
                'enrollments.id',
                'enrollments.academic_status',
                'enrollments.payment_status',
                'enrollments.created_at as enrollment_date',
                'courses.name as course_name',
                'courses.id as course_id',
                'enrollment_payments.id as payment_id',
                'enrollment_payments.amount as payment_amount',
                'enrollment_payments.operation_date as payment_date',
                'enrollment_payments.status as payment_verification_status',
            ])
            ->get();

        // Parsear interests
        if ($student->interests && is_string($student->interests)) {
            $student->interests = json_decode($student->interests, true);
        }

        $student->student_profile = [
            'interests' => $student->interests,
            'learning_goal' => $student->learning_goal
        ];

        $student->enrollments = $enrollments;

        return response()->json($student);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        /*if (!auth()->user()->hasRole(['admin', 'enrollment_manager'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }*/

        $student = User::whereHas('roles', function ($q) {
            $q->where('name', 'student');
        })->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'dni' => 'sometimes|string|size:8|unique:users,dni,' . $id,
            'fullname' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:255',
            'interests' => 'nullable|array',
            'learning_goal' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Actualizar usuario
            $student->update([
                'name' => $validated['name'] ?? $student->name,
                'email' => $validated['email'] ?? $student->email,
                'dni' => $validated['dni'] ?? $student->dni,
                'fullname' => $validated['fullname'] ?? $student->fullname,
                'phone' => $validated['phone'] ?? $student->phone,
            ]);

            // Actualizar perfil de estudiante
            if ($student->studentProfile) {
                $student->studentProfile->update([
                    'interests' => $validated['interests'] ?? $student->studentProfile->interests,
                    'learning_goal' => $validated['learning_goal'] ?? $student->studentProfile->learning_goal,
                ]);
            } else {
                // Crear perfil si no existe
                StudentProfile::create([
                    'user_id' => $student->id,
                    'interests' => $validated['interests'] ?? null,
                    'learning_goal' => $validated['learning_goal'] ?? null,
                ]);
            }

            DB::commit();
            Log::info('Estudiante actualizado', ['user_id' => $student->id]);

            return response()->json(['message' => 'Estudiante actualizado exitosamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar estudiante', ['user_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Error al actualizar el estudiante'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        /*if (!auth()->user()->hasRole(['admin'])) {
            return response()->json(['error' => 'Solo los administradores pueden eliminar estudiantes'], 403);
        }*/

        $student = User::whereHas('roles', function ($q) {
            $q->where('name', 'student');
        })->findOrFail($id);

        DB::beginTransaction();
        try {
            // Eliminar en cascada
            $student->studentProfile()->delete();
            $student->enrollments()->delete();
            $student->delete();

            DB::commit();
            Log::info('Estudiante eliminado', ['user_id' => $id]);

            return response()->json(['message' => 'Estudiante eliminado exitosamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar estudiante', ['user_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Error al eliminar el estudiante'], 500);
        }
    }

    /**
     * Obtener estadísticas de estudiantes
     */
    public function statistics(): JsonResponse
    {
        // Verificar permisos (comentado temporalmente para pruebas)
        // if (!auth()->user()->hasRole(['admin', 'enrollment_manager'])) {
        //     return response()->json(['error' => 'Unauthorized'], 403);
        // }

        $studentRoleId = Role::where('name', 'student')->value('id');

        // Total de estudiantes con rol student
        $totalStudents = DB::table('model_has_roles')
            ->where('role_id', $studentRoleId)
            ->where('model_type', 'App\\Models\\User')
            ->count();

        // Estudiantes con matrículas activas
        $activeStudents = DB::table('enrollments')
            ->where('academic_status', 'active')
            ->distinct('user_id')
            ->count('user_id');

        // Estudiantes con pagos pendientes
        $pendingPayments = DB::table('enrollments')
            ->where('payment_status', 'pending')
            ->distinct('user_id')
            ->count('user_id');

        return response()->json([
            'total_students' => $totalStudents,
            'active_students' => $activeStudents,
            'pending_payments' => $pendingPayments,
        ]);
    }

    /**
     * Obtener matrículas de un estudiante
     */
    public function enrollments(string $id): JsonResponse
    {
        /*if (!auth()->user()->hasRole(['admin', 'enrollment_manager'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }*/

        $enrollments = DB::table('enrollments')
            ->join('groups', 'enrollments.group_id', '=', 'groups.id')
            ->join('course_versions', 'groups.course_version_id', '=', 'course_versions.id')
            ->join('courses', 'course_versions.course_id', '=', 'courses.id')
            ->leftJoin('enrollment_payments', 'enrollments.id', '=', 'enrollment_payments.enrollment_id')
            ->where('enrollments.user_id', $id)
            ->select([
                'enrollments.id',
                'enrollments.academic_status',
                'enrollments.payment_status',
                'enrollments.created_at as enrollment_date',
                'courses.name as course_name',
                'courses.id as course_id',
                'enrollment_payments.id as payment_id',
                'enrollment_payments.amount as payment_amount',
                'enrollment_payments.operation_date as payment_date',
                'enrollment_payments.status as payment_verification_status',
            ])
            ->get();

        return response()->json($enrollments);
    }

    /**
     * Exportar estudiantes a CSV
     */
    public function exportCsv()
    {
        /*if (!auth()->user()->hasRole(['admin', 'enrollment_manager'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }*/

        $studentRoleId = Role::where('name', 'student')->value('id');

        $students = User::query()
            ->join('model_has_roles', function ($join) use ($studentRoleId) {
                $join->on('users.id', '=', 'model_has_roles.model_id')
                    ->where('model_has_roles.model_type', '=', 'App\\Models\\User')
                    ->where('model_has_roles.role_id', '=', $studentRoleId);
            })
            ->select([
                'users.id',
                'users.fullname',
                'users.dni',
                'users.email',
                'users.phone',
                'users.created_at',
            ])
            ->orderByDesc('users.id')
            ->get();

        $filename = 'estudiantes_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($students) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM para UTF-8

            fputcsv($handle, ['ID', 'Nombre Completo', 'DNI', 'Email', 'Teléfono', 'Fecha Registro']);

            foreach ($students as $student) {
                fputcsv($handle, [
                    $student->id,
                    $student->fullname,
                    $student->dni,
                    $student->email,
                    $student->phone ?? 'Sin teléfono',
                    Carbon::parse($student->created_at)->format('d/m/Y'),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Redirigir a la vista de exportación PDF
     */
    public function exportPdf()
    {
        return redirect('/administrativo/estudiantes/export-pdf');
    }

    /**
     * Obtener datos para exportación (usado por el frontend)
     */
    public function getExportData()
    {
        $studentRoleId = Role::where('name', 'student')->value('id');

        $students = User::query()
            ->join('model_has_roles', function ($join) use ($studentRoleId) {
                $join->on('users.id', '=', 'model_has_roles.model_id')
                    ->where('model_has_roles.model_type', '=', 'App\\Models\\User')
                    ->where('model_has_roles.role_id', '=', $studentRoleId);
            })
            ->leftJoin('student_profiles', 'users.id', '=', 'student_profiles.user_id')
            ->select([
                'users.id',
                'users.fullname',
                'users.dni',
                'users.email',
                'users.phone',
                'users.created_at',
                'student_profiles.interests',
                'student_profiles.learning_goal',
            ])
            ->orderByDesc('users.id')
            ->get();

        // Obtener enrollments para cada estudiante
        $studentIds = $students->pluck('id')->toArray();

        $enrollmentsData = [];
        if (!empty($studentIds)) {
            $enrollmentsData = DB::table('enrollments')
                ->join('groups', 'enrollments.group_id', '=', 'groups.id')
                ->join('course_versions', 'groups.course_version_id', '=', 'course_versions.id')
                ->join('courses', 'course_versions.course_id', '=', 'courses.id')
                ->whereIn('enrollments.user_id', $studentIds)
                ->select([
                    'enrollments.user_id',
                    'enrollments.academic_status',
                    'courses.name as course_name',
                ])
                ->get()
                ->groupBy('user_id');
        }

        // Agregar información de enrollments a cada estudiante
        $students = $students->map(function ($student) use ($enrollmentsData) {
            $userEnrollments = $enrollmentsData->get($student->id, collect());

            $student->total_enrollments = $userEnrollments->count();
            $student->active_enrollments = $userEnrollments->where('academic_status', 'active')->count();
            $student->status = $student->active_enrollments > 0 ? 'active' : 'inactive';

            return $student;
        });

        // Calcular estadísticas
        $totalStudents = $students->count();
        $activeStudents = $students->where('status', 'active')->count();
        $inactiveStudents = $students->where('status', 'inactive')->count();

        return response()->json([
            'students' => $students,
            'stats' => [
                'total_students' => $totalStudents,
                'active_students' => $activeStudents,
                'inactive_students' => $inactiveStudents,
            ],
            'generated_at' => Carbon::now()->toIso8601String(),
        ]);
    }
}
