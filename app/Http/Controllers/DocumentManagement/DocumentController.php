<?php

namespace App\Http\Controllers\DocumentManagement;

use App\Http\Controllers\Controller;
use App\Models\AdministrativeDocument;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DocumentController extends Controller
{
    /**
     * Display a listing of documents with filters and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));
        $type = $request->input('type', 'all');
        $perPage = (int) $request->get('per_page', 15);

        // Query base
        $query = AdministrativeDocument::query();

        // Filtro de búsqueda
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%");
            });
        }

        // Filtro por tipo
        if ($type !== 'all') {
            $query->where('type', $type);
        }

        // Ordenar por más reciente
        $query->orderByDesc('created_at');

        // Paginar
        $documents = $query->paginate($perPage);

        // Enriquecer datos con información adicional
        $documents->getCollection()->transform(function ($doc) {
            // Obtener tamaño del archivo
            if (Storage::disk('public')->exists($doc->path)) {
                $doc->size = Storage::disk('public')->size($doc->path);
                $doc->size_formatted = $this->formatBytes($doc->size);
            } else {
                $doc->size = 0;
                $doc->size_formatted = 'N/A';
            }

            // Formatear fecha
            $doc->date_formatted = Carbon::parse($doc->created_at)->format('Y-m-d');

            return $doc;
        });

        return response()->json([
            'data' => $documents->items(),
            'current_page' => $documents->currentPage(),
            'last_page' => $documents->lastPage(),
            'per_page' => $documents->perPage(),
            'total' => $documents->total(),
            'from' => $documents->firstItem(),
            'to' => $documents->lastItem(),
        ]);
    }

    /**
     * Get statistics
     */
    public function statistics(): JsonResponse
    {
        $totalDocuments = AdministrativeDocument::count();

        // Contar por tipo de documento
        $academicCount = AdministrativeDocument::where('type', 'Académico')->count();
        $administrativeCount = AdministrativeDocument::where('type', 'Administrativo')->count();
        $legalCount = AdministrativeDocument::where('type', 'Legal')->count();

        // Documentos subidos este mes (created_at)
        $uploadedThisMonth = AdministrativeDocument::whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();

        // Documentos actualizados este mes (updated_at != created_at)
        $updatedThisMonth = AdministrativeDocument::whereMonth('updated_at', Carbon::now()->month)
            ->whereYear('updated_at', Carbon::now()->year)
            ->whereColumn('updated_at', '!=', 'created_at')
            ->count();

        return response()->json([
            'total_documents' => $totalDocuments,
            'academic_count' => $academicCount,
            'administrative_count' => $administrativeCount,
            'legal_count' => $legalCount,
            'uploaded_this_month' => $uploadedThisMonth,
            'updated_this_month' => $updatedThisMonth,
        ]);
    }

    /**
     * Store a newly created document
     */
    /**
 * Store a newly created document
 */
    public function store(Request $request): JsonResponse
    {
        Log::info('📥 Iniciando subida de documento', [
            'request_data' => $request->all(),
        ]);

        try {
            // Validar según si viene file o drive_url
            $rules = [
                'name' => 'required|string|max:255|unique:administrative_documents,name',
                'type' => 'required|string|in:Académico,Administrativo,Legal',
            ];

            // Si viene drive_url, validar URL; si viene file, validar archivo
            if ($request->has('drive_url')) {
                $rules['drive_url'] = 'required|url';
            } else {
                $rules['file'] = 'required|file|mimes:pdf|max:10240';
            }

            $validated = $request->validate($rules);

            Log::info('✅ Validación exitosa', ['validated' => $validated]);

            // Determinar el path según el tipo de subida
            if ($request->has('drive_url')) {
                // Subida a Google Drive
                $path = $validated['drive_url']; // ⬅️ Guardar la URL completa en path
            } else {
                // Subida tradicional al servidor
                $file = $request->file('file');
                $path = $file->store('documents', 'public');
            }

            // Crear documento
            $document = AdministrativeDocument::create([
                'name' => $validated['name'],
                'type' => $validated['type'],
                'path' => $path, // ⬅️ Puede ser URL de Drive o path local
                'version' => 1.0,
            ]);

            Log::info('✅ Documento creado en BD', [
                'document_id' => $document->id,
                'name' => $document->name,
                'path' => $document->path
            ]);

            return response()->json([
                'message' => 'Documento subido exitosamente',
                'data' => $document
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('❌ Error de validación', [
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'error' => 'Error de validación',
                'messages' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('❌ Error al subir documento', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'error' => 'Error al subir el documento',
                'message' => config('app.debug') ? $e->getMessage() : 'Error del servidor'
            ], 500);
        }
    }
    /**
     * Display the specified document
     */
    public function show(string $id): JsonResponse
    {
        $document = AdministrativeDocument::findOrFail($id);

        // Enriquecer con información del archivo
        if (Storage::disk('public')->exists($document->path)) {
            $document->size = Storage::disk('public')->size($document->path);
            $document->size_formatted = $this->formatBytes($document->size);
            $document->url = Storage::disk('public')->url($document->path);
        }

        return response()->json($document);
    }

    /**
     * Update the specified document (upload new version)
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $document = AdministrativeDocument::findOrFail($id);

        $rules = [
            'name' => 'sometimes|string|max:255|unique:administrative_documents,name,' . $id,
            'type' => 'sometimes|string|in:Académico,Administrativo,Legal',
        ];

        // Validar según lo que viene
        if ($request->has('drive_url')) {
            $rules['drive_url'] = 'sometimes|url';
        } else {
            $rules['file'] = 'sometimes|file|mimes:pdf|max:10240';
        }

        $validated = $request->validate($rules);

        try {
            // Si se sube nuevo archivo o URL
            if ($request->has('drive_url')) {
                // Nueva versión desde Drive
                $validated['path'] = $request->drive_url;
                $validated['version'] = $document->version + 0.5;

            } elseif ($request->hasFile('file')) {
                // Nueva versión local - Eliminar archivo anterior si no es URL
                if (!filter_var($document->path, FILTER_VALIDATE_URL) &&
                    Storage::disk('public')->exists($document->path)) {
                    Storage::disk('public')->delete($document->path);
                }

                $file = $request->file('file');
                $path = $file->store('documents', 'public');
                $validated['path'] = $path;
                $validated['version'] = $document->version + 0.5;
            }

            $document->update($validated);

            Log::info('Documento actualizado', ['document_id' => $document->id]);

            return response()->json([
                'message' => 'Documento actualizado exitosamente',
                'data' => $document
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar documento', [
                'document_id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Error al actualizar el documento'], 500);
        }
    }

    /**
     * Remove the specified document
     */
    public function destroy(string $id): JsonResponse
    {
        $document = AdministrativeDocument::findOrFail($id);

        try {
            // Eliminar archivo físico
            if (Storage::disk('public')->exists($document->path)) {
                Storage::disk('public')->delete($document->path);
            }

            // Eliminar registro
            $document->delete();

            Log::info('Documento eliminado', ['document_id' => $id]);

            return response()->json(['message' => 'Documento eliminado exitosamente']);
        } catch (\Exception $e) {
            Log::error('Error al eliminar documento', ['document_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Error al eliminar el documento'], 500);
        }
    }

    /**
 * Download document
 */
    public function download(string $id)
    {
        try {
            $document = AdministrativeDocument::findOrFail($id);

            // ⬇️ DETECTAR si es URL de Drive o path local
            if (filter_var($document->path, FILTER_VALIDATE_URL)) {
                // Es una URL de Google Drive → Redirigir
                Log::info('✅ Redirigiendo a Google Drive', [
                    'document_id' => $id,
                    'url' => $document->path
                ]);

                return redirect($document->path);
            }

            // Es un archivo local → Descarga tradicional
            $strategies = [
                ['disk' => 'public', 'path' => $document->path],
                ['disk' => 'public', 'path' => str_replace('documents/', '', $document->path)],
                ['disk' => 'public', 'path' => 'documents/' . $document->path],
            ];

            foreach ($strategies as $index => $strategy) {
                $disk = $strategy['disk'];
                $path = $strategy['path'];

                if (Storage::disk($disk)->exists($path)) {
                    Log::info('✅ Archivo local encontrado', [
                        'strategy' => $index,
                        'path' => $path
                    ]);

                    $extension = pathinfo($path, PATHINFO_EXTENSION);
                    $filename = $document->name . '.' . $extension;

                    return response()->download(
                        Storage::disk($disk)->path($path),
                        $filename,
                        [
                            'Content-Type' => Storage::disk($disk)->mimeType($path),
                            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                        ]
                    );
                }
            }

            // Si no se encontró en ningún lado
            Log::error('❌ Archivo no encontrado', [
                'document_id' => $id,
                'path_in_db' => $document->path,
            ]);

            return response()->json([
                'error' => 'Archivo no encontrado',
                'message' => 'El archivo no existe en el servidor ni en Drive',
                'path_stored' => $document->path,
            ], 404);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Documento no encontrado',
                'message' => 'No existe un documento con ese ID'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error inesperado al descargar', [
                'document_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error del servidor',
                'message' => config('app.debug') ? $e->getMessage() : 'Error al procesar la descarga'
            ], 500);
        }
    }

    /**
     * Get export data for PDF/CSV
     */
    public function getExportData(): JsonResponse
    {
        $documents = AdministrativeDocument::orderByDesc('created_at')->get();

        // Enriquecer datos
        $documents->transform(function ($doc) {
            if (Storage::disk('public')->exists($doc->path)) {
                $doc->size = Storage::disk('public')->size($doc->path);
                $doc->size_formatted = $this->formatBytes($doc->size);
            } else {
                $doc->size = 0;
                $doc->size_formatted = 'N/A';
            }
            return $doc;
        });

        // Estadísticas
        $stats = [
            'total_documents' => $documents->count(),
            'academic_count' => $documents->where('type', 'Académico')->count(),
            'administrative_count' => $documents->where('type', 'Administrativo')->count(),
            'legal_count' => $documents->where('type', 'Legal')->count(),
            'uploaded_this_month' => AdministrativeDocument::whereMonth('created_at', Carbon::now()->month)
                ->whereYear('created_at', Carbon::now()->year)
                ->count(),
            'updated_this_month' => AdministrativeDocument::whereMonth('updated_at', Carbon::now()->month)
                ->whereYear('updated_at', Carbon::now()->year)
                ->whereColumn('updated_at', '!=', 'created_at')
                ->count(),
        ];

        return response()->json([
            'documents' => $documents,
            'stats' => $stats,
            'generated_at' => Carbon::now()->toIso8601String(),
        ]);
    }

    /**
     * Export documents to CSV
     */
    public function exportCsv()
    {
        $documents = AdministrativeDocument::orderByDesc('created_at')->get();

        $filename = 'documentos_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($documents) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM para UTF-8

            fputcsv($handle, ['ID', 'Nombre', 'Tipo', 'Tamaño', 'Fecha Subida']);

            foreach ($documents as $doc) {
                $size = 0;
                if (Storage::disk('public')->exists($doc->path)) {
                    $size = Storage::disk('public')->size($doc->path);
                }

                fputcsv($handle, [
                    $doc->id,
                    $doc->name,
                    $doc->type,
                    $this->formatBytes($size),
                    Carbon::parse($doc->created_at)->format('d/m/Y H:i'),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Helper: Format bytes to human readable
     */
    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
