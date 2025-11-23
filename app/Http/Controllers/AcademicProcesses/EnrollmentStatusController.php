<?php

namespace App\Http\Controllers\AcademicProcesses;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use IncadevUns\CoreDomain\Models\Enrollment;
use IncadevUns\CoreDomain\Enums\PaymentStatus;
use IncadevUns\CoreDomain\Enums\EnrollmentAcademicStatus;
use IncadevUns\CoreDomain\Enums\EnrollmentResultStatus;


class EnrollmentStatusController extends Controller
{
    private function getPaymentStatuses(): array
    {
        return PaymentStatus::values();
    }

    private function getAcademicStatuses(): array
    {
        return EnrollmentAcademicStatus::values();
    }

    private function getResultStatuses(): array
    {
        return EnrollmentResultStatus::values();
    }
    public function index(Request $request)
    {
        $stats = $this->getStats();

        $enrollmentsQuery = DB::table('enrollments')
            ->leftJoin('users', 'enrollments.user_id', '=', 'users.id')
            ->leftJoin('groups', 'enrollments.group_id', '=', 'groups.id')
            ->leftJoin('course_versions', 'groups.course_version_id', '=', 'course_versions.id')
            ->leftJoin('courses', 'course_versions.course_id', '=', 'courses.id')
            ->select([
                'enrollments.id',
                'enrollments.group_id',
                'enrollments.user_id',
                'enrollments.payment_status',
                'enrollments.academic_status',
                'users.name as student_name',
                'users.email as student_email',
                'groups.name as group_name',
                'courses.name as course_name',
                'enrollments.created_at',
            ])
            ->orderByDesc('enrollments.id');

        $enrollments = $enrollmentsQuery->get();

        foreach ($enrollments as $enrollment) {
            $enrollmentResult = DB::table('enrollment_results')
                ->where('enrollment_id', $enrollment->id)
                ->select([
                    'id',
                    'enrollment_id',
                    'final_grade',
                    'attendance_percentage',
                    'status',
                ])
                ->first();

            $enrollment->enrollment_result = $enrollmentResult;
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'stats' => $stats,
                'enrollments' => $enrollments,
            ]);
        }

        return view('academic-processes.enrollments', compact('enrollments', 'stats'));
    }

    public function show(Request $request, $id)
    {
        $enrollment = DB::table('enrollments')
            ->leftJoin('users', 'enrollments.user_id', '=', 'users.id')
            ->leftJoin('groups', 'enrollments.group_id', '=', 'groups.id')
            ->leftJoin('course_versions', 'groups.course_version_id', '=', 'course_versions.id')
            ->leftJoin('courses', 'course_versions.course_id', '=', 'courses.id')
            ->where('enrollments.id', $id)
            ->select([
                'enrollments.id',
                'enrollments.group_id',
                'enrollments.user_id',
                'enrollments.payment_status',
                'enrollments.academic_status',
                'enrollments.created_at',
                'enrollments.updated_at',
                'users.name as student_name',
                'users.email as student_email',
                'groups.name as group_name',
                'groups.start_date',
                'groups.end_date',
                'courses.name as course_name',
            ])
            ->first();

        if (!$enrollment) {
            return response()->json([
                'error' => 'Matrícula no encontrada.'
            ], 404);
        }

        $enrollmentResult = DB::table('enrollment_results')
            ->where('enrollment_id', $enrollment->id)
            ->select([
                'id',
                'enrollment_id',
                'final_grade',
                'attendance_percentage',
                'status',
                'created_at',
                'updated_at',
            ])
            ->first();

        $enrollment->enrollment_result = $enrollmentResult;

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'enrollment' => $enrollment,
            ]);
        }

        return view('academic-processes.enrollment-detail', compact('enrollment'));
    }

    public function updatePaymentStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'payment_status' => 'required|string|in:' . implode(',', $this->getPaymentStatuses()),
        ]);

        $enrollment = DB::table('enrollments')
            ->where('id', $id)
            ->first();

        if (!$enrollment) {
            return response()->json([
                'error' => 'Matrícula no encontrada.'
            ], 404);
        }

        try {
            DB::table('enrollments')
                ->where('id', $id)
                ->update([
                    'payment_status' => $validated['payment_status'],
                    'updated_at' => Carbon::now(),
                ]);

            Log::info('Estado de pago de matrícula actualizado', [
                'enrollment_id' => $id,
                'payment_status' => $validated['payment_status']
            ]);

            return response()->json([
                'message' => 'Estado de pago actualizado correctamente.'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar estado de pago de matrícula', [
                'enrollment_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error al actualizar el estado de pago.'
            ], 500);
        }
    }

    public function updateAcademicStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'academic_status' => 'required|string|in:' . implode(',', $this->getAcademicStatuses()),
        ]);

        $enrollment = DB::table('enrollments')
            ->where('id', $id)
            ->first();

        if (!$enrollment) {
            return response()->json([
                'error' => 'Matrícula no encontrada.'
            ], 404);
        }

        try {
            DB::table('enrollments')
                ->where('id', $id)
                ->update([
                    'academic_status' => $validated['academic_status'],
                    'updated_at' => Carbon::now(),
                ]);

            Log::info('Estado académico de matrícula actualizado', [
                'enrollment_id' => $id,
                'academic_status' => $validated['academic_status']
            ]);

            return response()->json([
                'message' => 'Estado académico actualizado correctamente.'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar estado académico de matrícula', [
                'enrollment_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error al actualizar el estado académico.'
            ], 500);
        }
    }

    public function updateEnrollmentResult(Request $request, $id)
    {
        $validated = $request->validate([
            'final_grade' => 'nullable|numeric|min:0|max:20',
            'attendance_percentage' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|string|in:' . implode(',', $this->getResultStatuses()),
        ]);

        $enrollment = DB::table('enrollments')
            ->where('id', $id)
            ->first();

        if (!$enrollment) {
            return response()->json([
                'error' => 'Matrícula no encontrada.'
            ], 404);
        }

        try {
            $existingResult = DB::table('enrollment_results')
                ->where('enrollment_id', $id)
                ->first();

            if ($existingResult) {
                DB::table('enrollment_results')
                    ->where('enrollment_id', $id)
                    ->update([
                        'final_grade' => $validated['final_grade'] ?? $existingResult->final_grade,
                        'attendance_percentage' => $validated['attendance_percentage'] ?? $existingResult->attendance_percentage,
                        'status' => $validated['status'] ?? $existingResult->status,
                        'updated_at' => Carbon::now(),
                    ]);
            } else {
                DB::table('enrollment_results')->insert([
                    'enrollment_id' => $id,
                    'final_grade' => $validated['final_grade'] ?? null,
                    'attendance_percentage' => $validated['attendance_percentage'] ?? null,
                    'status' => $validated['status'] ?? EnrollmentResultStatus::Pending->value,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }

            Log::info('Resultado de matrícula actualizado', [
                'enrollment_id' => $id,
                'data' => $validated
            ]);

            return response()->json([
                'message' => 'Resultado de matrícula actualizado correctamente.'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar resultado de matrícula', [
                'enrollment_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error al actualizar el resultado de matrícula.'
            ], 500);
        }
    }

    private function getStats()
    {
        $totalEnrollments = DB::table('enrollments')->count();

        $activeEnrollments = DB::table('enrollments')
            ->where('academic_status', EnrollmentAcademicStatus::Active->value)
            ->count();

        $completedEnrollments = DB::table('enrollments')
            ->where('academic_status', EnrollmentAcademicStatus::Completed->value)
            ->count();

        $pendingPayment = DB::table('enrollments')
            ->where('payment_status', PaymentStatus::Pending->value)
            ->count();

        return [
            'total_enrollments' => $totalEnrollments,
            'active_enrollments' => $activeEnrollments,
            'completed_enrollments' => $completedEnrollments,
            'pending_payment' => $pendingPayment,
        ];
    }
}
