<?php

namespace App\Http\Controllers\Gestion_Academica;

use App\Http\Controllers\Controller;
use App\Models\User;
use IncadevUns\CoreDomain\Models\Enrollment;
use IncadevUns\CoreDomain\Models\Certificate;
use IncadevUns\CoreDomain\Models\GroupTeacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademicHistoryController extends Controller
{
    /**
     * Obtener lista completa de estudiantes con resumen académico
     * GET /api/gestion-academica/historial-academico
     */
    public function index()
    {
        try {
            // Obtener todos los estudiantes
            $students = User::whereHas('roles', function ($q) {
                $q->where('name', 'student');
            })
                ->with([
                    'enrollments.group.courseVersion.course',
                    'enrollments.result',
                    'enrollments.payments',
                    'certificates'
                ])
                ->get()
                ->map(function ($user) {
                    return $this->formatStudentSummary($user);
                })
                ->filter();

            // Calcular estadísticas generales
            $stats = $this->calculateGeneralStats($students);

            return response()->json([
                'students' => $students->values(),
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            \Log::error('Error en AcademicHistoryController@index: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error al obtener historial académico',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener detalle completo de un estudiante
     * GET /api/gestion-academica/historial-academico/{id}
     */
    public function show($id)
    {
        try {
            $user = User::with([
                'enrollments.group.courseVersion.course',
                'enrollments.result',
                'enrollments.payments',
                'certificates'
            ])->findOrFail($id);

            $enrollments = $user->enrollments;
            
            // Calcular promedio solo de cursos con nota final aprobados
            $gradesSum = 0;
            $gradesCount = 0;
            
            foreach ($enrollments as $enrollment) {
                if ($enrollment->result && 
                    $enrollment->result->final_grade !== null &&
                    $enrollment->result->status === 'approved') {
                    $gradesSum += $enrollment->result->final_grade;
                    $gradesCount++;
                }
            }
            
            $averageGrade = $gradesCount > 0 ? $gradesSum / $gradesCount : 0;
            
            // Contar cursos por estado
            $completedCount = $enrollments->where('academic_status', 'completed')->count();
            $inProgressCount = $enrollments->where('academic_status', 'active')->count();
            
            $failedCount = $enrollments->filter(function($enrollment) {
                if ($enrollment->result && $enrollment->result->status === 'failed') {
                    return true;
                }
                return in_array($enrollment->academic_status, ['failed', 'abandoned', 'suspended']);
            })->count();
            
            $totalCount = $enrollments->count();
            
            // Ya que no hay créditos en BD, usar 0
            $totalCredits = 0;
            $earnedCredits = 0;

            $data = [
                'id' => $user->id,
                'code' => 'EST-' . str_pad($user->id, 8, '0', STR_PAD_LEFT),
                'fullname' => $user->fullname ?? $user->name,
                'dni' => $user->dni ?? '',
                'email' => $user->email,
                'phone' => $user->phone ?? '',
                'avatar' => $user->avatar,
                'registration_date' => $user->created_at->format('Y-m-d'),
                'academic_status' => $this->getAcademicStatus($user),
                'payment_status' => $this->getPaymentStatus($enrollments),
                'total_courses' => $totalCount,
                'completed_courses' => $completedCount,
                'in_progress_courses' => $inProgressCount,
                'failed_courses' => $failedCount,
                'average_grade' => round($averageGrade, 2),
                'total_credits' => $totalCredits,
                'earned_credits' => $earnedCredits,
                'completion_percentage' => $totalCount > 0 
                    ? round(($completedCount / $totalCount) * 100, 2)
                    : 0,
                'certificates_count' => $user->certificates->count()
            ];

            return response()->json($data);

        } catch (\Exception $e) {
            \Log::error('Error en AcademicHistoryController@show: ' . $e->getMessage(), [
                'user_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'error' => 'Error al obtener detalle del estudiante',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener historial de cursos de un estudiante
     * GET /api/gestion-academica/historial-academico/{id}/cursos
     */
    public function getCourses($userId)
    {
        try {
            $enrollments = Enrollment::where('user_id', $userId)
                ->with([
                    'group.courseVersion.course',
                    'group.courseVersion.modules',
                    'group.teachers', // Relación corregida
                    'result',
                    'grades.exam',
                    'attendances.classSession',
                    'payments'
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            $courses = $enrollments->map(function ($enrollment) {
                $group = $enrollment->group;

                // Validar que existan las relaciones necesarias
                if (!$group || !$group->courseVersion || !$group->courseVersion->course) {
                    return null;
                }

                $courseVersion = $group->courseVersion;
                $course = $courseVersion->course;
                $result = $enrollment->result;
                $attendances = $enrollment->attendances ?? collect();
                $payment = $enrollment->payments->first();

                // Obtener el instructor - relación corregida
                $instructor = 'No asignado';
                if ($group->groupTeachers && $group->groupTeachers->isNotEmpty()) {
                    $firstTeacher = $group->groupTeachers->first();
                    if ($firstTeacher && $firstTeacher->user) {
                        $instructor = $firstTeacher->user->fullname ?? $firstTeacher->user->name ?? 'No asignado';
                    }
                }

                // Calcular asistencias
                $totalClasses = $attendances->count();
                $attendedClasses = $attendances->where('status', 'present')->count();
                $attendancePercentage = $totalClasses > 0
                    ? round(($attendedClasses / $totalClasses) * 100, 2)
                    : 0;

                // Obtener calificaciones detalladas
                $detailedGrades = $enrollment->grades->map(function ($grade) {
                    return [
                        'component' => $grade->exam->title ?? 'Evaluación',
                        'grade' => $grade->grade !== null ? round($grade->grade, 2) : null,
                        'weight' => 33.33 // Valor por defecto
                    ];
                });

                // Calcular créditos (valor por defecto)
                $credits = $courseVersion->modules->count() * 3;

                // Determinar el estado
                $status = $enrollment->academic_status;
                if (is_object($status)) {
                    $status = $status->value; // Para enums
                }

                if ($result && $result->final_grade !== null) {
                    if ($result->status === 'approved' && $result->final_grade >= 11) {
                        $status = 'approved';
                    } elseif ($result->status === 'failed' || $result->final_grade < 11) {
                        $status = 'failed';
                    }
                }

                return [
                    'id' => $enrollment->id,
                    'course_name' => $course->name ?? 'Sin nombre',
                    'course_description' => $course->description ?? '',
                    'period' => ($group->start_date ?? '') . ' - ' . ($group->end_date ?? ''),
                    'instructor' => $instructor,
                    'final_grade' => $result && $result->final_grade !== null
                        ? round($result->final_grade, 2)
                        : null,
                    'credits' => $credits,
                    'attendance_percentage' => $attendancePercentage,
                    'status' => $status,
                    'certificate_id' => $enrollment->certificate?->id,
                    'detailed_grades' => $detailedGrades->values(),
                    'total_classes' => $totalClasses,
                    'attended_classes' => $attendedClasses,
                    'payment_amount' => $payment ? $payment->amount : null,
                    'payment_date' => $payment ? $payment->operation_date : null,
                    'payment_status' => $payment ? $payment->status : null,
                    'group_status' => $group->status ?? 'unknown'
                ];
            })->filter(); // Filtrar valores null

            return response()->json($courses->values());
        } catch (\Exception $e) {
            \Log::error('Error en AcademicHistoryController@getCourses: ' . $e->getMessage(), [
                'user_id' => $userId,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error al obtener historial de cursos',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener certificados de un estudiante
     * GET /api/gestion-academica/historial-academico/{id}/certificados
     */
    public function getCertificates($userId)
    {
        try {
            $certificates = Certificate::where('user_id', $userId)
                ->with(['group.courseVersion.course'])
                ->orderBy('issue_date', 'desc')
                ->get()
                ->map(function ($cert) {
                    $enrollment = Enrollment::where('user_id', $cert->user_id)
                        ->where('group_id', $cert->group_id)
                        ->with('result')
                        ->first();

                    return [
                        'id' => $cert->id,
                        'uuid' => $cert->uuid,
                        'course_name' => $cert->group->courseVersion->course->name ?? 'Sin nombre',
                        'issue_date' => $cert->issue_date,
                        'final_grade' => $enrollment?->result?->final_grade
                            ? round($enrollment->result->final_grade, 2)
                            : 0,
                        'verification_url' => url('/verify-certificate/' . $cert->uuid)
                    ];
                });

            return response()->json($certificates->values());
        } catch (\Exception $e) {
            \Log::error('Error en AcademicHistoryController@getCertificates: ' . $e->getMessage(), [
                'user_id' => $userId,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'error' => 'Error al obtener certificados',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar datos para CSV
     * GET /api/gestion-academica/historial-academico/exportar/csv
     */
    public function exportCSV()
    {
        try {
            $students = User::whereHas('roles', function ($q) {
                $q->where('name', 'student');
            })
                ->with([
                    'enrollments.group.courseVersion.course',
                    'enrollments.result',
                    'certificates'
                ])
                ->get()
                ->map(function ($user) {
                    return $this->formatStudentSummary($user);
                })
                ->filter();

            $filename = 'historial_academico_' . date('Y-m-d_His') . '.csv';

            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function () use ($students) {
                $file = fopen('php://output', 'w');

                // BOM para UTF-8
                fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

                // Encabezados
                fputcsv($file, [
                    'Código',
                    'Nombre Completo',
                    'Email',
                    'Total Cursos',
                    'Completados',
                    'En Progreso',
                    'Promedio',
                    'Créditos',
                    'Certificados',
                    'Estado Académico',
                    'Estado Pagos'
                ]);

                // Datos
                foreach ($students as $student) {
                    fputcsv($file, [
                        $student['code'],
                        $student['fullname'],
                        $student['email'],
                        $student['total_courses'],
                        $student['completed_courses'],
                        $student['in_progress_courses'],
                        $student['average_grade'],
                        $student['total_credits'],
                        $student['certificates_count'],
                        $student['academic_status'],
                        $student['payment_status']
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            \Log::error('Error en AcademicHistoryController@exportCSV: ' . $e->getMessage());

            return response()->json([
                'error' => 'Error al exportar CSV',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener datos para exportar PDF
     * GET /api/gestion-academica/historial-academico/exportar/datos
     */
    public function exportData()
    {
        try {
            $students = User::whereHas('roles', function ($q) {
                $q->where('name', 'student');
            })
                ->with([
                    'enrollments.group.courseVersion.course',
                    'enrollments.result',
                    'certificates'
                ])
                ->get()
                ->map(function ($user) {
                    return $this->formatStudentSummary($user);
                })
                ->filter();

            $stats = $this->calculateGeneralStats($students);

            return response()->json([
                'students' => $students->values(),
                'stats' => $stats,
                'export_date' => now()->format('Y-m-d H:i:s'),
                'total_records' => $students->count()
            ]);
        } catch (\Exception $e) {
            \Log::error('Error en AcademicHistoryController@exportData: ' . $e->getMessage());

            return response()->json([
                'error' => 'Error al obtener datos de exportación',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Formatear resumen de estudiante
     */
    private function formatStudentSummary($user)
    {
        try {
            $enrollments = $user->enrollments ?? collect();

            // Calcular promedio solo de cursos con nota final
            $gradesSum = 0;
            $gradesCount = 0;

            foreach ($enrollments as $enrollment) {
                if (
                    $enrollment->result &&
                    $enrollment->result->final_grade !== null &&
                    $enrollment->result->status === 'approved'
                ) {
                    $gradesSum += $enrollment->result->final_grade;
                    $gradesCount++;
                }
            }

            $averageGrade = $gradesCount > 0 ? $gradesSum / $gradesCount : 0;

            // Contar cursos por estado usando academic_status del enrollment
            $completedCount = $enrollments->where('academic_status', 'completed')->count();
            $inProgressCount = $enrollments->where('academic_status', 'active')->count();

            // Contar cursos fallidos/abandonados
            $failedCount = $enrollments->filter(function ($enrollment) {
                // Si tiene resultado reprobado o estados específicos
                if ($enrollment->result && $enrollment->result->status === 'failed') {
                    return true;
                }
                // O si el estado académico indica fallo
                return in_array($enrollment->academic_status, ['failed', 'abandoned', 'suspended']);
            })->count();

            $totalCount = $enrollments->count();

            // Calcular porcentaje de avance basado en cursos completados
            $completionPercentage = $totalCount > 0
                ? round(($completedCount / $totalCount) * 100, 2)
                : 0;

            // Ya que no hay campo credits en la BD, usar un valor estimado
            // o simplemente no calcularlo
            $totalCredits = 0;
            $earnedCredits = 0;

            return [
                'id' => $user->id,
                'code' => 'EST-' . str_pad($user->id, 8, '0', STR_PAD_LEFT),
                'fullname' => $user->fullname ?? $user->name,
                'dni' => $user->dni ?? '',
                'email' => $user->email,
                'phone' => $user->phone ?? '',
                'avatar' => $user->avatar,
                'registration_date' => $user->created_at->format('Y-m-d'),
                'total_courses' => $totalCount,
                'completed_courses' => $completedCount,
                'in_progress_courses' => $inProgressCount,
                'failed_courses' => $failedCount,
                'average_grade' => round($averageGrade, 2),
                'total_credits' => $totalCredits,
                'earned_credits' => $earnedCredits,
                'completion_percentage' => $completionPercentage,
                'certificates_count' => $user->certificates->count() ?? 0,
                'academic_status' => $this->getAcademicStatus($user),
                'payment_status' => $this->getPaymentStatus($enrollments)
            ];
        } catch (\Exception $e) {
            \Log::error('Error formateando estudiante: ' . $e->getMessage(), [
                'user_id' => $user->id ?? 'unknown'
            ]);
            return null;
        }
    }

    /**
     * Determinar el estado académico de un estudiante
     */
    private function getAcademicStatus($user)
    {
        $enrollments = $user->enrollments ?? collect();
        $activeEnrollments = $enrollments->where('academic_status', 'active');
        $completedCount = $enrollments->where('academic_status', 'completed')->count();
        $totalCount = $enrollments->count();

        // Si no tiene cursos
        if ($totalCount === 0) {
            return 'inactive';
        }

        // Si tiene cursos activos
        if ($activeEnrollments->isNotEmpty()) {
            return 'active';
        }

        // Si completó todos sus cursos
        if ($completedCount > 0 && $completedCount === $totalCount) {
            return 'graduated';
        }

        return 'inactive';
    }

    /**
     * Determinar el estado de pagos de un estudiante
     */
    private function getPaymentStatus($enrollments)
    {
        $pendingPayments = $enrollments->filter(function ($enrollment) {
            $payments = $enrollment->payments ?? collect();

            // Si no tiene pagos registrados
            if ($payments->isEmpty()) {
                return true;
            }

            // Si tiene pagos pendientes o rechazados
            return $payments->whereIn('status', ['pending', 'rejected'])->isNotEmpty();
        });

        return $pendingPayments->isEmpty() ? 'paid' : 'pending';
    }

    /**
     * Calcular estadísticas generales
     */
    private function calculateGeneralStats($students)
    {
        $totalStudents = $students->count();

        if ($totalStudents === 0) {
            return [
                'total_students' => 0,
                'active_students' => 0,
                'graduated_students' => 0,
                'average_completion_rate' => 0,
                'total_certificates_issued' => 0
            ];
        }

        $activeStudents = $students->where('academic_status', 'active')->count();
        $graduatedStudents = $students->where('academic_status', 'graduated')->count();

        // Calcular tasa promedio de finalización
        $completionRates = $students->map(function ($student) {
            if ($student['total_courses'] > 0) {
                return ($student['completed_courses'] / $student['total_courses']) * 100;
            }
            return 0;
        });

        $averageCompletionRate = $completionRates->avg();

        return [
            'total_students' => $totalStudents,
            'active_students' => $activeStudents,
            'graduated_students' => $graduatedStudents,
            'average_completion_rate' => round($averageCompletionRate, 2),
            'total_certificates_issued' => Certificate::count()
        ];
    }
}
