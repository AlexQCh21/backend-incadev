<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Str;
use IncadevUns\CoreDomain\Models\Enrollment;
use IncadevUns\CoreDomain\Enums\PaymentStatus;
use IncadevUns\CoreDomain\Enums\EnrollmentAcademicStatus;
use IncadevUns\CoreDomain\Enums\PaymentVerificationStatus;

class PaymentsController extends Controller
{

    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $currentMonthStart = Carbon::now()->startOfMonth();
        $currentMonthEnd = Carbon::now()->endOfMonth();
        $previousMonthStart = (clone $currentMonthStart)->subMonth();
        $previousMonthEnd = (clone $currentMonthStart)->subDay();

        $monthlyIncome = (float) DB::table('enrollment_payments')
            ->whereNotNull('amount')
            ->where('status', PaymentVerificationStatus::Approved->value)
            ->whereBetween('operation_date', [$currentMonthStart, $currentMonthEnd])
            ->sum('amount');

        $previousIncome = (float) DB::table('enrollment_payments')
            ->whereNotNull('amount')
            ->where('status', PaymentVerificationStatus::Approved->value)
            ->whereBetween('operation_date', [$previousMonthStart, $previousMonthEnd])
            ->sum('amount');

        $monthlyVariation = $previousIncome > 0
            ? (($monthlyIncome - $previousIncome) / $previousIncome) * 100
            : null;

        $pendingSnapshot = DB::table('enrollment_payments')
            ->leftJoin('enrollments', 'enrollment_payments.enrollment_id', '=', 'enrollments.id')
            ->leftJoin('users', 'enrollments.user_id', '=', 'users.id')
            ->where('enrollment_payments.status', PaymentVerificationStatus::Pending->value)
            ->selectRaw('COALESCE(SUM(enrollment_payments.amount), 0) as total_amount, COUNT(DISTINCT users.id) as students_count')
            ->first();

        $paymentSnapshot = DB::table('enrollment_payments')
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved', [PaymentVerificationStatus::Approved->value])
            ->first();

        $collectionRate = ($paymentSnapshot && $paymentSnapshot->total > 0)
            ? round(($paymentSnapshot->approved / $paymentSnapshot->total) * 100, 1)
            : null;

        $stats = [
            'monthly_income' => $monthlyIncome,
            'monthly_variation' => $monthlyVariation,
            'pending_amount' => (float) ($pendingSnapshot->total_amount ?? 0),
            'pending_students' => (int) ($pendingSnapshot->students_count ?? 0),
            'collection_rate' => $collectionRate,
        ];

        $statusBreakdown = DB::table('enrollment_payments')
            ->selectRaw('LOWER(status) as status_key, COUNT(*) as total')
            ->groupBy(DB::raw('LOWER(status)'))
            ->pluck('total', 'status_key');

        $sort = strtolower($request->query('sort', 'date'));
        $direction = strtolower($request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortColumns = [
            'id' => 'enrollment_payments.id',
            'amount' => 'enrollment_payments.amount',
            'date' => 'enrollment_payments.operation_date'
        ];

        if (! array_key_exists($sort, $sortColumns)) {
            $sort = 'date';
        }

        $paymentsQuery = DB::table('enrollment_payments')
            ->leftJoin('enrollments', 'enrollment_payments.enrollment_id', '=', 'enrollments.id')
            ->leftJoin('users', 'enrollments.user_id', '=', 'users.id')
            ->select([
                'enrollment_payments.id',
                'enrollment_payments.enrollment_id',
                'enrollment_payments.operation_number',
                'enrollment_payments.agency_number',
                'enrollment_payments.operation_date',
                'enrollment_payments.amount',
                'enrollment_payments.evidence_path',
                'enrollment_payments.status',
                DB::raw("TRIM(COALESCE(users.name, '')) as student_name"),
            ]);

        if ($search !== '') {
            $searchLower = Str::lower($search);

            $paymentsQuery->where(function ($query) use ($search, $searchLower) {
                if (is_numeric($search)) {
                    $query->orWhere('enrollment_payments.id', (int) $search);
                }

                $paymentsQuery->orWhereRaw('LOWER(enrollment_payments.operation_number) LIKE ?', ["%{$searchLower}%"]);
                $paymentsQuery->orWhereRaw("LOWER(TRIM(COALESCE(users.name, ''))) LIKE ?", ["%{$searchLower}%"]);
            });
        }

        $statusFilter = $request->query('status');
        if ($statusFilter && in_array(strtolower($statusFilter), ['pending', 'approved', 'rejected'])) {
            $paymentsQuery->where('enrollment_payments.status', strtolower($statusFilter));
        }

        $paymentsQuery->orderBy($sortColumns[$sort], $direction);

        $payments = $paymentsQuery
            ->paginate(12)
            ->withQueryString();

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'stats' => $stats,
                'status_breakdown' => $statusBreakdown,
                'payments' => $payments->items(),
                'pagination' => [
                    'current_page' => $payments->currentPage(),
                    'per_page' => $payments->perPage(),
                    'total' => $payments->total(),
                    'last_page' => $payments->lastPage(),
                ],
            ]);
        }

        return view('pagos.payments', compact('payments', 'stats', 'statusBreakdown', 'search', 'sort', 'direction'));
    }

    public function create()
    {
        return view('pagos.create');
    }

    public function show($id)
    {
        $payment = DB::table('payments')->where('id', $id)->first();
        return view('pagos.show', compact('payment'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'invoice_id' => 'nullable|integer',
            'amount' => 'required|numeric',
            'payment_date' => 'required|date',
            'payment_method_id' => 'nullable|integer',
        ]);

        $id = DB::table('payments')->insertGetId(array_merge($data, ['status' => 'paid']));

        return redirect()->route('pagos.show', $id)->with('success', 'Pago registrado.');
    }

    public function report(Request $request)
    {
        $from = $request->input('from');
        $to = $request->input('to');

        $rows = DB::table('payments')
            ->whereBetween('payment_date', [$from ?? '1970-01-01', $to ?? now()])
            ->select('id', 'amount', 'payment_date', 'status')
            ->orderBy('payment_date')
            ->get();

        return back()->with('info', "Reporte generado con {$rows->count()} transacciones (simulado)");
    }

    public function approval(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $sort = strtolower($request->query('sort', 'date'));
        $direction = strtolower($request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortColumns = [
            'id' => 'enrollment_payments.id',
            'amount' => 'enrollment_payments.amount',
            'date' => 'enrollment_payments.operation_date'
        ];

        if (! array_key_exists($sort, $sortColumns)) {
            $sort = 'date';
        }

        $paymentsQuery = DB::table('enrollment_payments')
            ->leftJoin('enrollments', 'enrollment_payments.enrollment_id', '=', 'enrollments.id')
            ->leftJoin('users', 'enrollments.user_id', '=', 'users.id')
            ->where('enrollment_payments.status', PaymentVerificationStatus::Pending->value)
            ->select([
                'enrollment_payments.id',
                'enrollment_payments.enrollment_id',
                'enrollment_payments.operation_number',
                'enrollment_payments.agency_number',
                'enrollment_payments.operation_date',
                'enrollment_payments.amount',
                'enrollment_payments.evidence_path',
                'enrollment_payments.status',
                DB::raw("TRIM(COALESCE(users.name, '')) as student_name"),
            ]);

        if ($search !== '') {
            $searchLower = Str::lower($search);

            $paymentsQuery->where(function ($query) use ($search, $searchLower) {
                if (is_numeric($search)) {
                    $query->orWhere('enrollment_payments.id', (int) $search);
                }

                $query->orWhereRaw('LOWER(enrollment_payments.operation_number) LIKE ?', ["%{$searchLower}%"]);
                $query->orWhereRaw("LOWER(TRIM(COALESCE(users.name, ''))) LIKE ?", ["%{$searchLower}%"]);
            });
        }

        $paymentsQuery->orderBy($sortColumns[$sort], $direction);

        $payments = $paymentsQuery
            ->paginate(12)
            ->withQueryString();

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'payments' => $payments->items(),
                'pagination' => [
                    'current_page' => $payments->currentPage(),
                    'per_page' => $payments->perPage(),
                    'total' => $payments->total(),
                    'last_page' => $payments->lastPage(),
                ],
            ]);
        }

        return view('pagos.approval', compact('payments', 'search', 'sort', 'direction'));
    }

    public function exportCsv()
    {
        $records = DB::table('enrollment_payments')
            ->leftJoin('enrollments', 'enrollment_payments.enrollment_id', '=', 'enrollments.id')
            ->leftJoin('users', 'enrollments.user_id', '=', 'users.id')
            ->select([
                'enrollment_payments.id',
                'enrollment_payments.enrollment_id',
                'enrollment_payments.operation_number',
                'enrollment_payments.agency_number',
                'enrollment_payments.operation_date',
                'enrollment_payments.amount',
                'enrollment_payments.evidence_path',
                'enrollment_payments.status',
                DB::raw("TRIM(COALESCE(users.name, '')) as student_name"),
            ])
            ->orderByDesc('enrollment_payments.operation_date')
            ->get();

        $filename = 'pagos_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($records) {
            $handle = fopen('php://output', 'w');

            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, ['ID', 'Estudiante', 'N° Operación', 'Agencia', 'Monto', 'Fecha Operación', 'Estado']);

            foreach ($records as $row) {
                $statusMap = [
                    'approved' => 'Aprobado',
                    'pending' => 'Pendiente',
                    'rejected' => 'Rechazado',
                ];
                $status = strtolower($row->status ?? 'pending');
                $statusText = $statusMap[$status] ?? ucfirst($status);

                fputcsv($handle, [
                    $row->id,
                    trim($row->student_name) !== '' ? $row->student_name : 'Sin asignar',
                    $row->operation_number ?? 'Sin número',
                    $row->agency_number ?? 'Sin agencia',
                    number_format((float) ($row->amount ?? 0), 2, ',', '.'),
                    $row->operation_date ? Carbon::parse($row->operation_date)->format('d/m/Y') : 'Sin fecha',
                    $statusText,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf()
    {
        return redirect('/administrativo/pagos/export-pdf');
    }

    public function getExportData()
    {
        $records = DB::table('enrollment_payments')
            ->leftJoin('enrollments', 'enrollment_payments.enrollment_id', '=', 'enrollments.id')
            ->leftJoin('users', 'enrollments.user_id', '=', 'users.id')
            ->select([
                'enrollment_payments.id',
                'enrollment_payments.enrollment_id',
                'enrollment_payments.operation_number',
                'enrollment_payments.agency_number',
                'enrollment_payments.operation_date',
                'enrollment_payments.amount',
                'enrollment_payments.evidence_path',
                'enrollment_payments.status',
                DB::raw("TRIM(COALESCE(users.name, '')) as student_name"),
            ])
            ->orderByDesc('enrollment_payments.operation_date')
            ->get();

        return response()->json([
            'payments' => $records,
            'generated_at' => Carbon::now()->toIso8601String(),
        ]);
    }

    public function downloadInvoice(Request $request, $id)
    {
        $payment = DB::table('enrollment_payments')
            ->leftJoin('enrollments', 'enrollment_payments.enrollment_id', '=', 'enrollments.id')
            ->leftJoin('users', 'enrollments.user_id', '=', 'users.id')
            ->select([
                'enrollment_payments.id as payment_id',
                'enrollment_payments.amount',
                'enrollment_payments.operation_date as payment_date',
                'enrollment_payments.status as payment_status',
                'enrollment_payments.operation_number as invoice_number',
                'enrollment_payments.operation_date as issue_date',
                'enrollment_payments.amount as total_amount',
                'enrollment_payments.agency_number as payment_method',
                'users.name as fullname',
                'users.dni as document_number',
                'users.email',
            ])
            ->where('enrollment_payments.id', $id)
            ->first();

        if (! $payment) {
            return response()->json(['error' => 'No se encontró el pago solicitado.'], 404);
        }

        if (! $payment->invoice_number) {
            $payment->invoice_number = sprintf('P-%03d', $payment->payment_id);
        }

        $payment->invoice_status = ucfirst(strtolower($payment->payment_status ?? 'Pendiente'));

        $redirectUrl = sprintf('/administrativo/pagos/invoice?id=%d', $id);
        
        if ($request->query('stream', false)) {
            $redirectUrl .= '&stream=true';
        }

        return redirect($redirectUrl);
    }

    public function getInvoiceData($id)
    {
        $payment = DB::table('enrollment_payments')
            ->leftJoin('enrollments', 'enrollment_payments.enrollment_id', '=', 'enrollments.id')
            ->leftJoin('users', 'enrollments.user_id', '=', 'users.id')
            ->select([
                'enrollment_payments.id',
                'enrollment_payments.enrollment_id',
                'enrollment_payments.operation_number',
                'enrollment_payments.agency_number',
                'enrollment_payments.operation_date',
                'enrollment_payments.amount',
                'enrollment_payments.evidence_path',
                'enrollment_payments.status',
                'users.name as student_name',
                'users.dni as document_number',
                'users.email',
            ])
            ->where('enrollment_payments.id', $id)
            ->first();

        if (! $payment) {
            return response()->json(['error' => 'No se encontró el pago solicitado.'], 404);
        }

        return response()->json($payment);
    }

    public function checkEvidence($id)
    {
        $payment = DB::table('enrollment_payments')
            ->select('evidence_path')
            ->where('id', $id)
            ->first();

        if (! $payment) {
            return response()->json(['exists' => false, 'error' => 'Pago no encontrado'], 404);
        }

        if (! $payment->evidence_path) {
            return response()->json(['exists' => false, 'message' => 'No hay evidencia adjunta']);
        }

        $evidencePath = trim($payment->evidence_path);
        
        if (preg_match('/^https?:\/\//i', $evidencePath)) {
            return response()->json(['exists' => true, 'url' => $evidencePath, 'is_external' => true]);
        }

        $storagePath = storage_path('app/public/' . $evidencePath);
        $fileExists = file_exists($storagePath) && is_file($storagePath);

        return response()->json([
            'exists' => $fileExists,
            'url' => $fileExists ? url('api/pagos/' . $id . '/evidence') : null,
            'is_external' => false,
            'path' => $evidencePath
        ]);
    }

    public function getEvidence($id)
    {
        $payment = DB::table('enrollment_payments')
            ->select('evidence_path')
            ->where('id', $id)
            ->first();

        if (! $payment || ! $payment->evidence_path) {
            abort(404, 'Evidencia no encontrada');
        }

        $evidencePath = trim($payment->evidence_path);
        
        if (preg_match('/^https?:\/\//i', $evidencePath)) {
            return redirect($evidencePath);
        }

        $storagePath = storage_path('app/public/' . $evidencePath);
        
        if (! file_exists($storagePath) || ! is_file($storagePath)) {
            abort(404, 'Archivo no encontrado');
        }

        $mimeType = mime_content_type($storagePath);
        
        return response()->file($storagePath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=3600'
        ]);
    }

    //Aprobar pagos de estudiantes

    public function approve(Request $request, $id)
    {
        $payment = DB::table('enrollment_payments')->where('id', $id)->first();
        if (! $payment) {
            return response()->json(['error' => 'Pago no encontrado.'], 404);
        }

        if (strtolower($payment->status) === PaymentVerificationStatus::Approved->value) {
            return response()->json(['message' => 'Pago ya se encuentra aprobado.'], 200);
        }

        DB::beginTransaction();
        try {
            DB::table('enrollment_payments')->where('id', $id)->update([
                'status' => PaymentVerificationStatus::Approved->value,
                'updated_at' => Carbon::now(),
            ]);

            $enrollment = Enrollment::findOrFail($payment->enrollment_id);
            $enrollment->update([
                'payment_status' => PaymentStatus::Paid,
                'academic_status' => EnrollmentAcademicStatus::Active,
            ]);

            DB::commit();

            Log::info('Pago aprobado', ['payment_id' => $id, 'enrollment_id' => $payment->enrollment_id]);

            return response()->json(['message' => 'Pago aprobado correctamente.']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al aprobar pago', ['payment_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Error al aprobar el pago.'], 500);
        }
    }

    //Rechazar pagos de estudiantes

    public function reject(Request $request, $id)
    {
        $payment = DB::table('enrollment_payments')->where('id', $id)->first();
        if (! $payment) {
            return response()->json(['error' => 'Pago no encontrado.'], 404);
        }

        if (strtolower($payment->status) === PaymentVerificationStatus::Rejected->value) {
            return response()->json(['message' => 'Pago ya se encuentra rechazado.'], 200);
        }

        DB::beginTransaction();
        try {
            DB::table('enrollment_payments')->where('id', $id)->update([
                'status' => PaymentVerificationStatus::Rejected->value,
                'updated_at' => Carbon::now(),
            ]);

            $enrollment = Enrollment::findOrFail($payment->enrollment_id);
            $enrollment->update([
                'payment_status' => PaymentStatus::Cancelled,
                'academic_status' => EnrollmentAcademicStatus::Failed,
            ]);

            DB::commit();

            Log::info('Pago rechazado', ['payment_id' => $id, 'enrollment_id' => $payment->enrollment_id]);

            return response()->json(['message' => 'Pago rechazado correctamente.']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al rechazar pago', ['payment_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Error al rechazar el pago.'], 500);
        }
    }
}
