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
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:administrative_documents,name',
            'type' => 'required|string|in:Académico,Administrativo,Legal',
            'file' => 'required|file|mimes:pdf|max:10240', // Solo PDF, 10MB max
        ]);

        try {
            // Subir archivo
            $file = $request->file('file');
            $path = $file->store('documents', 'public');

            // Crear documento con versión inicial 1.0
            $document = AdministrativeDocument::create([
                'name' => $validated['name'],
                'type' => $validated['type'],
                'path' => $path,
                'version' => 1.0,
            ]);

            Log::info('Documento creado', ['document_id' => $document->id, 'name' => $document->name]);

            return response()->json([
                'message' => 'Documento subido exitosamente',
                'data' => $document
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al subir documento', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Error al subir el documento: ' . $e->getMessage()], 500);
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

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:administrative_documents,name,' . $id,
            'type' => 'sometimes|string|in:Académico,Administrativo,Legal',
            'file' => 'sometimes|file|mimes:pdf|max:10240', // Solo PDF
        ]);

        try {
            // Si se sube un nuevo archivo, eliminar el anterior y subir el nuevo
            if ($request->hasFile('file')) {
                // Eliminar archivo anterior
                if (Storage::disk('public')->exists($document->path)) {
                    Storage::disk('public')->delete($document->path);
                }

                // Subir nuevo archivo
                $file = $request->file('file');
                $path = $file->store('documents', 'public');
                $validated['path'] = $path;

                // Incrementar versión en 0.5
                $validated['version'] = $document->version + 0.5;
            }

            $document->update($validated);

            Log::info('Documento actualizado', ['document_id' => $document->id]);

            return response()->json([
                'message' => 'Documento actualizado exitosamente',
                'data' => $document
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar documento', ['document_id' => $id, 'error' => $e->getMessage()]);
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
        $document = AdministrativeDocument::findOrFail($id);

        if (!Storage::disk('public')->exists($document->path)) {
            return response()->json(['error' => 'Archivo no encontrado'], 404);
        }

        return Storage::disk('public')->download($document->path, $document->name . '.' . strtolower($document->type));
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
