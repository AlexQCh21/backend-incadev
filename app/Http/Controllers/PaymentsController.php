<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
            ->where('status', 'approved')
            ->whereBetween('operation_date', [$currentMonthStart, $currentMonthEnd])
            ->sum('amount');

        $previousIncome = (float) DB::table('enrollment_payments')
            ->whereNotNull('amount')
            ->where('status', 'approved')
            ->whereBetween('operation_date', [$previousMonthStart, $previousMonthEnd])
            ->sum('amount');

        $monthlyVariation = $previousIncome > 0
            ? (($monthlyIncome - $previousIncome) / $previousIncome) * 100
            : null;

        $pendingSnapshot = DB::table('enrollment_payments')
            ->leftJoin('enrollments', 'enrollment_payments.enrollment_id', '=', 'enrollments.id')
            ->leftJoin('users', 'enrollments.user_id', '=', 'users.id')
            ->where('enrollment_payments.status', 'pending')
            ->selectRaw('COALESCE(SUM(enrollment_payments.amount), 0) as total_amount, COUNT(DISTINCT users.id) as students_count')
            ->first();

        $paymentSnapshot = DB::table('enrollment_payments')
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved', ['approved'])
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
                'enrollment_payments.id as payment_id',
                'enrollment_payments.amount',
                'enrollment_payments.operation_date as payment_date',
                'enrollment_payments.status',
                'enrollment_payments.operation_number as invoice_number',
                'enrollment_payments.agency_number as payment_method',
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

    public function exportCsv()
    {
        $records = DB::table('enrollment_payments')
            ->leftJoin('enrollments', 'enrollment_payments.enrollment_id', '=', 'enrollments.id')
            ->leftJoin('users', 'enrollments.user_id', '=', 'users.id')
            ->select([
                'enrollment_payments.id as payment_id',
                'enrollment_payments.amount',
                'enrollment_payments.operation_date as payment_date',
                'enrollment_payments.status',
                'enrollment_payments.operation_number as invoice_number',
                'enrollment_payments.agency_number as payment_method',
                DB::raw("TRIM(COALESCE(users.name, '')) as student_name"),
            ])
            ->orderByDesc('enrollment_payments.operation_date')
            ->get();

        $filename = 'pagos_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($records) {
            $handle = fopen('php://output', 'w');

            // Include UTF-8 BOM so spreadsheet apps display accents correctly
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, ['ID Pago', 'Estudiante', 'Agencia', 'Monto', 'Fecha', 'Estado', 'N° Operación']);

            foreach ($records as $row) {
                fputcsv($handle, [
                    'P-' . str_pad($row->payment_id, 3, '0', STR_PAD_LEFT),
                    trim($row->student_name) !== '' ? $row->student_name : 'Sin asignar',
                    $row->payment_method ?? 'Sin agencia',
                    number_format((float) ($row->amount ?? 0), 2, ',', '.'),
                    $row->payment_date ? Carbon::parse($row->payment_date)->format('d/m/Y') : 'Sin fecha',
                    ucfirst(strtolower($row->status ?? 'pendiente')),
                    $row->invoice_number ?? 'Sin número',
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
                'enrollment_payments.id as payment_id',
                'enrollment_payments.amount',
                'enrollment_payments.operation_date as payment_date',
                'enrollment_payments.status',
                'enrollment_payments.operation_number as invoice_number',
                'enrollment_payments.agency_number as payment_method',
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
                'enrollment_payments.id as payment_id',
                'enrollment_payments.amount',
                'enrollment_payments.operation_date as payment_date',
                'enrollment_payments.status as payment_status',
                'enrollment_payments.operation_number as invoice_number',
                'enrollment_payments.operation_date as issue_date',
                'enrollment_payments.agency_number as payment_method',
                'users.name as student_name',
                'users.dni as document_number',
                'users.email',
            ])
            ->where('enrollment_payments.id', $id)
            ->first();

        if (! $payment) {
            return response()->json(['error' => 'No se encontró el pago solicitado.'], 404);
        }

        if (! $payment->invoice_number) {
            $payment->invoice_number = sprintf('OP-%04d', $payment->payment_id);
        }

        return response()->json($payment);
    }
}

