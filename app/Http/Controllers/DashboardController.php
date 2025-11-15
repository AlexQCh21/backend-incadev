<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use IncadevUns\CoreDomain\Models\Course;
use IncadevUns\CoreDomain\Models\CourseVersion;
use IncadevUns\CoreDomain\Models\Enrollment;
use IncadevUns\CoreDomain\Models\EnrollmentPayment;
use IncadevUns\CoreDomain\Models\Group;
use IncadevUns\CoreDomain\Models\Contract;
use IncadevUns\CoreDomain\Models\PayrollExpense;
use App\Models\User;
use IncadevUns\CoreDomain\Enums\CourseVersionStatus;
use IncadevUns\CoreDomain\Enums\PaymentStatus;
use IncadevUns\CoreDomain\Enums\EnrollmentAcademicStatus;
use IncadevUns\CoreDomain\Enums\GroupStatus;
use IncadevUns\CoreDomain\Enums\PaymentVerificationStatus;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function getDashboardData(): JsonResponse
    {
        try {
            $data = [
                'courses' => $this->getCoursesData(),
                'enrollments' => $this->getEnrollmentsData(),
                'students' => $this->getStudentsData(),
                'teachers' => $this->getTeachersData(),
                'payrollExpenses' => $this->getPayrollExpensesData(),
                'payments' => $this->getPaymentsData(),
                'monthlyData' => $this->getMonthlyData(),
                'coursesDistribution' => $this->getCoursesDistribution(),
                'stats' => $this->getDashboardStats()
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al cargar datos del dashboard',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function getCoursesData(): array
    {
        return CourseVersion::with('course')
            ->where('status', CourseVersionStatus::Published)
            ->get()
            ->map(function ($version) {
                $enrollmentsCount = Enrollment::whereHas('group', function ($query) use ($version) {
                    $query->where('course_version_id', $version->id)
                          ->whereIn('status', [GroupStatus::Enrolling, GroupStatus::Active]);
                })->count();

                return [
                    'id' => $version->id,
                    'name' => $version->course->name,
                    'version' => $version->version ?? 'v1.0',
                    'price' => (float) $version->price,
                    'status' => strtolower($version->status->value),
                    'enrollments' => $enrollmentsCount
                ];
            })->toArray();
    }

    private function getEnrollmentsData(): array
    {
        return Enrollment::with(['user', 'group.courseVersion.course'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($enrollment) {
                return [
                    'id' => $enrollment->id,
                    'student' => $enrollment->user->name,
                    'course' => $enrollment->group->courseVersion->course->name,
                    'payment_status' => strtolower($enrollment->payment_status->value),
                    'academic_status' => strtolower($enrollment->academic_status->value),
                    'amount' => $this->getEnrollmentAmount($enrollment)
                ];
            })->toArray();
    }

    private function getEnrollmentAmount(Enrollment $enrollment): float
    {
        $coursePrice = $enrollment->group->courseVersion->price;
        $paidAmount = $enrollment->payments()
            ->where('status', PaymentVerificationStatus::Approved)
            ->sum('amount');

        return $paidAmount > 0 ? (float) $paidAmount : (float) $coursePrice;
    }

    private function getStudentsData(): array
    {
        return User::role('student')
            ->withCount(['enrollments' => function ($query) {
                $query->where('academic_status', EnrollmentAcademicStatus::Active);
            }])
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($user) {
                $activeEnrollments = $user->enrollments()
                    ->where('academic_status', EnrollmentAcademicStatus::Active)
                    ->count();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'courses' => $activeEnrollments,
                    'status' => $activeEnrollments > 0 ? 'active' : 'inactive'
                ];
            })->toArray();
    }

    private function getTeachersData(): array
    {
        return User::role('teacher')
            ->withCount(['groups as courses_count' => function ($query) {
                $query->whereIn('status', [GroupStatus::Enrolling, GroupStatus::Active]);
            }])
            ->withCount(['groups as students_count' => function ($query) {
                $query->join('enrollments', 'groups.id', '=', 'enrollments.group_id')
                    ->where('enrollments.academic_status', EnrollmentAcademicStatus::Active);
            }])
            ->latest()
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'speciality' => $this->getTeacherSpeciality($user),
                    'courses' => $user->courses_count,
                    'students' => $user->students_count
                ];
            })->toArray();
    }

    private function getTeacherSpeciality(User $user): string
    {
        $mostCommonCourse = $user->groups()
            ->with('courseVersion.course')
            ->whereIn('status', [GroupStatus::Enrolling, GroupStatus::Active])
            ->get()
            ->groupBy('courseVersion.course.name')
            ->sortByDesc(function ($groups) {
                return $groups->count();
            })
            ->keys()
            ->first();

        return $mostCommonCourse ?? 'General';
    }

    private function getPayrollExpensesData(): array
    {
        $currentMonth = now()->startOfMonth();

        return PayrollExpense::with(['contract.user'])
            ->where('date', '>=', $currentMonth)
            ->get()
            ->map(function ($expense) {
                return [
                    'id' => $expense->id,
                    'teacher' => $expense->contract->user->name,
                    'amount' => (float) $expense->amount,
                    'date' => $expense->date->toDateString(),
                    'type' => strtolower($expense->contract->payment_type->value ?? 'monthly')
                ];
            })->toArray();
    }

    private function getPaymentsData(): array
    {
        return EnrollmentPayment::with(['enrollment.user', 'enrollment.group.courseVersion.course'])
            ->whereIn('status', [PaymentVerificationStatus::Approved, PaymentVerificationStatus::Pending])
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'student' => $payment->enrollment->user->name,
                    'course' => $payment->enrollment->group->courseVersion->course->name,
                    'amount' => (float) $payment->amount,
                    'date' => $payment->operation_date->toDateString(),
                    'status' => strtolower($payment->status->value)
                ];
            })->toArray();
    }

    private function getMonthlyData(): array
    {
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();

            // Ingresos (pagos aprobados)
            $income = EnrollmentPayment::where('status', PaymentVerificationStatus::Approved)
                ->whereBetween('operation_date', [$monthStart, $monthEnd])
                ->sum('amount');

            // Egresos (nómina)
            $expenses = PayrollExpense::whereBetween('date', [$monthStart, $monthEnd])
                ->sum('amount');

            // Estudiantes activos en el mes
            $students = Enrollment::where('academic_status', EnrollmentAcademicStatus::Active)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->distinct('user_id')
                ->count('user_id');

            $months[] = [
                'month' => $date->format('M'),
                'ingresos' => (float) $income,
                'egresos' => (float) $expenses,
                'estudiantes' => $students
            ];
        }

        return $months;
    }

    private function getCoursesDistribution(): array
    {
        return CourseVersion::with(['course', 'groups.enrollments'])
            ->where('status', CourseVersionStatus::Published)
            ->get()
            ->map(function ($version) {
                $studentsCount = $version->groups->flatMap(function ($group) {
                    return $group->enrollments->where('academic_status', EnrollmentAcademicStatus::Active);
                })->unique('user_id')->count();

                return [
                    'name' => $version->course->name,
                    'value' => $studentsCount,
                    'students' => $studentsCount
                ];
            })->filter(function ($course) {
                return $course['students'] > 0;
            })->values()->toArray();
    }

    private function getDashboardStats(): array
    {
        $totalStudents = User::role('student')->count();
        $activeStudents = Enrollment::where('academic_status', EnrollmentAcademicStatus::Active)
            ->distinct('user_id')
            ->count('user_id');
        
        $totalTeachers = User::role('teacher')->count();
        $activeCourses = CourseVersion::where('status', CourseVersionStatus::Published)->count();
        
        $totalEnrollments = Enrollment::count();
        $paidEnrollments = Enrollment::where('payment_status', PaymentStatus::Paid)->count();
        $pendingPayments = Enrollment::whereIn('payment_status', [PaymentStatus::Pending, PaymentStatus::PartiallyPaid])->count();
        
        $totalRevenue = EnrollmentPayment::where('status', PaymentVerificationStatus::Approved)->sum('amount');
        $totalPayroll = PayrollExpense::where('date', '>=', now()->startOfMonth())->sum('amount');
        $netIncome = $totalRevenue - $totalPayroll;

        return [
            'totalStudents' => $totalStudents,
            'activeStudents' => $activeStudents,
            'totalTeachers' => $totalTeachers,
            'activeCourses' => $activeCourses,
            'totalEnrollments' => $totalEnrollments,
            'paidEnrollments' => $paidEnrollments,
            'pendingPayments' => $pendingPayments,
            'totalRevenue' => (float) $totalRevenue,
            'totalPayroll' => (float) $totalPayroll,
            'netIncome' => (float) $netIncome
        ];
    }

    public function exportDashboardData(): JsonResponse
    {
        try {
            $data = $this->getDashboardData();
            
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al exportar datos',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}