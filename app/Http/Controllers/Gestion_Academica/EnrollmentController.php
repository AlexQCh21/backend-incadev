<?php

namespace App\Http\Controllers\Gestion_Academica;

use App\Http\Controllers\Controller;
use App\Models\User;
use IncadevUns\CoreDomain\Models\Enrollment;
use IncadevUns\CoreDomain\Models\EnrollmentPayment;
use IncadevUns\CoreDomain\Models\Group;
use IncadevUns\CoreDomain\Models\CourseVersion;
use IncadevUns\CoreDomain\Models\Course;
use IncadevUns\CoreDomain\Enums\PaymentStatus;
use IncadevUns\CoreDomain\Enums\EnrollmentAcademicStatus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class EnrollmentController extends Controller
{
    /**
     * Display a listing of the resource with filters and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));
        $paymentStatus = $request->input('payment_status', 'all');
        $academicStatus = $request->input('academic_status', 'all');
        $groupId = $request->input('group_id');
        $courseId = $request->input('course_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $perPage = (int) $request->get('per_page', 15);

        // Consulta base con todas las relaciones necesarias
        $query = Enrollment::query()
            ->with([
                'user:id,name,fullname,dni,email,phone,avatar',
                'group.courseVersion.course:id,name,description',
                'group.courseVersion:id,course_id,version,name,price',
                'group:id,course_version_id,name,start_date,end_date,status'
            ])
            ->select([
                'enrollments.id',
                'enrollments.group_id',
                'enrollments.user_id',
                'enrollments.payment_status',
                'enrollments.academic_status',
                'enrollments.created_at',
                'enrollments.updated_at',
            ]);

        // Filtro de búsqueda
        if ($search !== '') {
            $searchLower = Str::lower($search);
            $query->where(function ($q) use ($search, $searchLower) {
                $q->whereHas('user', function ($userQuery) use ($searchLower) {
                    $userQuery->whereRaw('LOWER(name) LIKE ?', ["%{$searchLower}%"])
                        ->orWhereRaw('LOWER(fullname) LIKE ?', ["%{$searchLower}%"])
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%{$searchLower}%"])
                        ->orWhere('dni', 'like', "%{$search}%");
                })
                ->orWhereHas('group.courseVersion.course', function ($courseQuery) use ($searchLower) {
                    $courseQuery->whereRaw('LOWER(name) LIKE ?', ["%{$searchLower}%"]);
                })
                ->orWhereHas('group', function ($groupQuery) use ($searchLower) {
                    $groupQuery->whereRaw('LOWER(name) LIKE ?', ["%{$searchLower}%"]);
                });
            });
        }

        // Filtro por estado de pago (convertir a Enum si no es 'all')
        if ($paymentStatus !== 'all') {
            $paymentStatusEnum = PaymentStatus::tryFrom($paymentStatus);
            if ($paymentStatusEnum) {
                $query->where('payment_status', $paymentStatusEnum);
            }
        }

        // Filtro por estado académico (convertir a Enum si no es 'all')
        if ($academicStatus !== 'all') {
            $academicStatusEnum = EnrollmentAcademicStatus::tryFrom($academicStatus);
            if ($academicStatusEnum) {
                $query->where('academic_status', $academicStatusEnum);
            }
        }

        // Filtro por grupo
        if ($groupId) {
            $query->where('group_id', $groupId);
        }

        // Filtro por curso
        if ($courseId) {
            $query->whereHas('group.courseVersion', function ($q) use ($courseId) {
                $q->where('course_id', $courseId);
            });
        }

        // Filtro por rango de fechas
        if ($dateFrom) {
            $query->whereDate('enrollments.created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('enrollments.created_at', '<=', $dateTo);
        }

        // Ordenar por ID descendente
        $query->orderByDesc('enrollments.id');

        // Obtener resultados paginados
        $enrollments = $query->paginate($perPage);

        // Obtener IDs de enrollments para cargar los últimos pagos
        $enrollmentIds = collect($enrollments->items())->pluck('id')->toArray();

        $lastPayments = [];
        if (!empty($enrollmentIds)) {
            $lastPayments = DB::table('enrollment_payments')
                ->select([
                    'enrollment_payments.enrollment_id',
                    'enrollment_payments.id',
                    'enrollment_payments.amount',
                    'enrollment_payments.operation_number',
                    'enrollment_payments.operation_date',
                    'enrollment_payments.status',
                ])
                ->whereIn('enrollment_id', $enrollmentIds)
                ->whereIn('enrollment_payments.id', function ($query) use ($enrollmentIds) {
                    $query->select(DB::raw('MAX(id)'))
                        ->from('enrollment_payments')
                        ->whereIn('enrollment_id', $enrollmentIds)
                        ->groupBy('enrollment_id');
                })
                ->get()
                ->keyBy('enrollment_id');
        }

        // Transformar datos para el frontend
        $transformedEnrollments = collect($enrollments->items())->map(function ($enrollment) use ($lastPayments) {
            $lastPayment = $lastPayments->get($enrollment->id);

            return [
                'id' => $enrollment->id,
                'group_id' => $enrollment->group_id,
                'user_id' => $enrollment->user_id,
                'payment_status' => $enrollment->payment_status->value, // Convertir enum a string
                'academic_status' => $enrollment->academic_status->value, // Convertir enum a string
                'created_at' => $enrollment->created_at->toIso8601String(),
                'updated_at' => $enrollment->updated_at->toIso8601String(),
                
                'student' => [
                    'id' => $enrollment->user->id,
                    'name' => $enrollment->user->name,
                    'dni' => $enrollment->user->dni,
                    'fullname' => $enrollment->user->fullname,
                    'email' => $enrollment->user->email,
                    'phone' => $enrollment->user->phone,
                    'avatar' => $enrollment->user->avatar,
                ],
                
                'group' => [
                    'id' => $enrollment->group->id,
                    'name' => $enrollment->group->name,
                    'start_date' => $enrollment->group->start_date,
                    'end_date' => $enrollment->group->end_date,
                    'status' => $enrollment->group->status,
                    'course_version' => [
                        'id' => $enrollment->group->courseVersion->id,
                        'version' => $enrollment->group->courseVersion->version,
                        'name' => $enrollment->group->courseVersion->name,
                        'price' => (float) $enrollment->group->courseVersion->price,
                        'course' => [
                            'id' => $enrollment->group->courseVersion->course->id,
                            'name' => $enrollment->group->courseVersion->course->name,
                            'description' => $enrollment->group->courseVersion->course->description,
                        ],
                    ],
                ],
                
                'last_payment' => $lastPayment ? [
                    'id' => $lastPayment->id,
                    'amount' => (float) $lastPayment->amount,
                    'operation_number' => $lastPayment->operation_number,
                    'operation_date' => $lastPayment->operation_date,
                    'status' => $lastPayment->status,
                ] : null,
            ];
        });

        return response()->json([
            'enrollments' => $transformedEnrollments,
            'stats' => $this->calculateStats(),
            'pagination' => [
                'current_page' => $enrollments->currentPage(),
                'last_page' => $enrollments->lastPage(),
                'per_page' => $enrollments->perPage(),
                'total' => $enrollments->total(),
                'from' => $enrollments->firstItem(),
                'to' => $enrollments->lastItem(),
            ]
        ]);
    }

    /**
     * Store a newly created enrollment
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'group_id' => 'required|exists:groups,id',
            'payment_status' => 'nullable|in:pending,paid,partial,overdue',
            'academic_status' => 'nullable|in:pending,active,inactive,completed,failed',
        ]);

        DB::beginTransaction();
        try {
            $existingEnrollment = Enrollment::where('user_id', $validated['user_id'])
                ->where('group_id', $validated['group_id'])
                ->first();

            if ($existingEnrollment) {
                return response()->json([
                    'error' => 'El estudiante ya está matriculado en este grupo'
                ], 422);
            }

            $enrollment = Enrollment::create([
                'user_id' => $validated['user_id'],
                'group_id' => $validated['group_id'],
                'payment_status' => isset($validated['payment_status']) 
                    ? PaymentStatus::from($validated['payment_status']) 
                    : PaymentStatus::Pending,
                'academic_status' => isset($validated['academic_status']) 
                    ? EnrollmentAcademicStatus::from($validated['academic_status']) 
                    : EnrollmentAcademicStatus::Pending,
            ]);

            DB::commit();
            Log::info('Matrícula creada', ['enrollment_id' => $enrollment->id]);

            $enrollment->load([
                'user:id,name,fullname,dni,email,phone,avatar',
                'group.courseVersion.course'
            ]);

            return response()->json([
                'message' => 'Matrícula creada exitosamente',
                'data' => $enrollment
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear matrícula', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Error al crear la matrícula: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified enrollment
     */
    public function show(string $id): JsonResponse
    {
        $enrollment = Enrollment::with([
            'user:id,name,fullname,dni,email,phone,avatar',
            'group.courseVersion.course',
            'group.courseVersion',
            'group'
        ])->find($id);

        if (!$enrollment) {
            return response()->json(['error' => 'Matrícula no encontrada'], 404);
        }

        $lastPayment = EnrollmentPayment::where('enrollment_id', $id)
            ->orderByDesc('id')
            ->first();

        $enrollmentData = [
            'id' => $enrollment->id,
            'group_id' => $enrollment->group_id,
            'user_id' => $enrollment->user_id,
            'payment_status' => $enrollment->payment_status->value, // Convertir enum
            'academic_status' => $enrollment->academic_status->value, // Convertir enum
            'created_at' => $enrollment->created_at->toIso8601String(),
            'updated_at' => $enrollment->updated_at->toIso8601String(),
            
            'student' => [
                'id' => $enrollment->user->id,
                'name' => $enrollment->user->name,
                'dni' => $enrollment->user->dni,
                'fullname' => $enrollment->user->fullname,
                'email' => $enrollment->user->email,
                'phone' => $enrollment->user->phone,
                'avatar' => $enrollment->user->avatar,
            ],
            
            'group' => [
                'id' => $enrollment->group->id,
                'name' => $enrollment->group->name,
                'start_date' => $enrollment->group->start_date,
                'end_date' => $enrollment->group->end_date,
                'status' => $enrollment->group->status,
                'course_version' => [
                    'id' => $enrollment->group->courseVersion->id,
                    'version' => $enrollment->group->courseVersion->version,
                    'name' => $enrollment->group->courseVersion->name,
                    'price' => (float) $enrollment->group->courseVersion->price,
                    'course' => [
                        'id' => $enrollment->group->courseVersion->course->id,
                        'name' => $enrollment->group->courseVersion->course->name,
                        'description' => $enrollment->group->courseVersion->course->description,
                    ],
                ],
            ],
            
            'last_payment' => $lastPayment ? [
                'id' => $lastPayment->id,
                'amount' => (float) $lastPayment->amount,
                'operation_number' => $lastPayment->operation_number,
                'operation_date' => $lastPayment->operation_date,
                'status' => $lastPayment->status,
            ] : null,
        ];

        return response()->json(['data' => $enrollmentData]);
    }

    /**
     * Update the specified enrollment
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $enrollment = Enrollment::findOrFail($id);

        $validated = $request->validate([
            'payment_status' => 'sometimes|in:pending,paid,partial,overdue',
            'academic_status' => 'sometimes|in:pending,active,inactive,completed,failed',
        ]);

        DB::beginTransaction();
        try {
            $updateData = [];
            
            if (isset($validated['payment_status'])) {
                $updateData['payment_status'] = PaymentStatus::from($validated['payment_status']);
            }
            
            if (isset($validated['academic_status'])) {
                $updateData['academic_status'] = EnrollmentAcademicStatus::from($validated['academic_status']);
            }

            $enrollment->update($updateData);

            DB::commit();
            Log::info('Matrícula actualizada', ['enrollment_id' => $enrollment->id]);

            return response()->json([
                'success' => true,
                'message' => 'Matrícula actualizada exitosamente',
                'data' => $enrollment
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar matrícula', ['enrollment_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Error al actualizar la matrícula'], 500);
        }
    }

    /**
     * Update payment status
     */
    public function updatePaymentStatus(Request $request, string $id): JsonResponse
    {
        $enrollment = Enrollment::findOrFail($id);

        $validated = $request->validate([
            'payment_status' => 'required|in:pending,paid,partial,overdue',
        ]);

        DB::beginTransaction();
        try {
            $enrollment->update([
                'payment_status' => PaymentStatus::from($validated['payment_status'])
            ]);

            DB::commit();
            Log::info('Estado de pago actualizado', [
                'enrollment_id' => $enrollment->id,
                'new_status' => $validated['payment_status']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Estado de pago actualizado exitosamente',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar estado de pago', [
                'enrollment_id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Error al actualizar el estado de pago'], 500);
        }
    }

    /**
     * Update academic status
     */
    public function updateAcademicStatus(Request $request, string $id): JsonResponse
    {
        $enrollment = Enrollment::findOrFail($id);

        $validated = $request->validate([
            'academic_status' => 'required|in:pending,active,inactive,completed,failed',
        ]);

        DB::beginTransaction();
        try {
            $enrollment->update([
                'academic_status' => EnrollmentAcademicStatus::from($validated['academic_status'])
            ]);

            DB::commit();
            Log::info('Estado académico actualizado', [
                'enrollment_id' => $enrollment->id,
                'new_status' => $validated['academic_status']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Estado académico actualizado exitosamente',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar estado académico', [
                'enrollment_id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Error al actualizar el estado académico'], 500);
        }
    }

    /**
     * Remove the specified enrollment
     */
    public function destroy(string $id): JsonResponse
    {
        $enrollment = Enrollment::findOrFail($id);

        DB::beginTransaction();
        try {
            EnrollmentPayment::where('enrollment_id', $id)->delete();
            $enrollment->delete();

            DB::commit();
            Log::info('Matrícula eliminada', ['enrollment_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Matrícula eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar matrícula', ['enrollment_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Error al eliminar la matrícula'], 500);
        }
    }

    /**
     * Get enrollment statistics
     */
    public function statistics(): JsonResponse
    {
        return response()->json($this->calculateStats());
    }

    /**
     * Calculate statistics for enrollments
     */
    private function calculateStats(): array
    {
        $totalEnrollments = Enrollment::count();
        
        // Contar por cada estado usando el enum
        $pendingEnrollments = Enrollment::where('academic_status', EnrollmentAcademicStatus::Pending)->count();
        $activeEnrollments = Enrollment::where('academic_status', EnrollmentAcademicStatus::Active)->count();
        $completedEnrollments = Enrollment::where('academic_status', EnrollmentAcademicStatus::Completed)->count();
        
        $pendingPayments = Enrollment::where('payment_status', PaymentStatus::Pending)
            ->orWhere('payment_status', PaymentStatus::Overdue)
            ->count();

        // Calcular ingresos
        $totalRevenue = DB::table('enrollment_payments')
            ->where('status', 'approved')
            ->sum('amount');

        $pendingRevenue = DB::table('enrollments')
            ->join('groups', 'enrollments.group_id', '=', 'groups.id')
            ->join('course_versions', 'groups.course_version_id', '=', 'course_versions.id')
            ->where(function ($query) {
                $query->where('enrollments.payment_status', PaymentStatus::Pending->value)
                      ->orWhere('enrollments.payment_status', PaymentStatus::Overdue->value);
            })
            ->sum('course_versions.price');

        // Calcular tasa de completación
        $completionRate = $totalEnrollments > 0 
            ? ($completedEnrollments / $totalEnrollments) * 100 
            : 0;

        return [
            'total_enrollments' => $totalEnrollments,
            'pending_enrollments' => $pendingEnrollments,
            'active_enrollments' => $activeEnrollments,
            'completed_enrollments' => $completedEnrollments,
            'pending_payments' => $pendingPayments,
            'total_revenue' => (float) $totalRevenue,
            'pending_revenue' => (float) $pendingRevenue,
            'completion_rate' => round($completionRate, 1),
        ];
    }

    /**
     * Export enrollments to CSV
     */
    public function exportCsv(Request $request)
    {
        $enrollments = Enrollment::with([
            'user:id,name,fullname,dni,email',
            'group.courseVersion.course:id,name',
            'group.courseVersion:id,course_id,price',
            'group:id,course_version_id,name'
        ])->get();

        $filename = 'matriculas_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($enrollments) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM para UTF-8

            fputcsv($handle, [
                'ID',
                'Estudiante',
                'DNI',
                'Email',
                'Curso',
                'Grupo',
                'Precio',
                'Estado Pago',
                'Estado Académico',
                'Fecha Matrícula'
            ]);

            foreach ($enrollments as $enrollment) {
                fputcsv($handle, [
                    $enrollment->id,
                    $enrollment->user->fullname ?? $enrollment->user->name,
                    $enrollment->user->dni ?? 'Sin DNI',
                    $enrollment->user->email,
                    $enrollment->group->courseVersion->course->name,
                    $enrollment->group->name,
                    '$' . number_format($enrollment->group->courseVersion->price, 2),
                    ucfirst($enrollment->payment_status->value), // Usar ->value para obtener el string
                    ucfirst($enrollment->academic_status->value), // Usar ->value para obtener el string
                    Carbon::parse($enrollment->created_at)->format('d/m/Y H:i'),
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
        $enrollments = Enrollment::with([
            'user:id,name,fullname,dni,email,phone',
            'group.courseVersion.course:id,name',
            'group.courseVersion:id,course_id,version,price',
            'group:id,course_version_id,name,start_date,end_date'
        ])->orderByDesc('id')->get();

        $transformedEnrollments = $enrollments->map(function ($enrollment) {
            return [
                'id' => $enrollment->id,
                'student_name' => $enrollment->user->fullname ?? $enrollment->user->name,
                'student_dni' => $enrollment->user->dni,
                'student_email' => $enrollment->user->email,
                'course_name' => $enrollment->group->courseVersion->course->name,
                'group_name' => $enrollment->group->name,
                'price' => $enrollment->group->courseVersion->price,
                'payment_status' => $enrollment->payment_status->value, // Convertir a string
                'academic_status' => $enrollment->academic_status->value, // Convertir a string
                'enrollment_date' => Carbon::parse($enrollment->created_at)->format('d/m/Y'),
            ];
        });

        return response()->json([
            'enrollments' => $transformedEnrollments,
            'stats' => $this->calculateStats(),
            'generated_at' => Carbon::now()->toIso8601String(),
        ]);
    }

    /**
     * Redirect to PDF export view
     */
    public function exportPdf()
    {
        return redirect('/administrativo/matriculas/export-pdf');
    }
}