<?php

namespace App\Http\Controllers\DocumentManagement;

use App\Http\Controllers\Controller;
use IncadevUns\CoreDomain\Models\InstituteDirector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InstituteDirectorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 9);
            $search = $request->input('search', '');

            $query = InstituteDirector::query();

            if ($search) {
                $query->where('name', 'like', "%{$search}%");
            }

            $directors = $query->orderBy('created_at', 'desc')
                              ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $directors->items(),
                'pagination' => [
                    'total' => $directors->total(),
                    'per_page' => $directors->perPage(),
                    'current_page' => $directors->currentPage(),
                    'last_page' => $directors->lastPage(),
                    'from' => $directors->firstItem(),
                    'to' => $directors->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los directores: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'signature' => 'required|url',
        ], [
            'name.required' => 'El nombre es obligatorio',
            'name.max' => 'El nombre no puede tener más de 255 caracteres',
            'signature.required' => 'La firma es obligatoria',
            'signature.url' => 'La firma debe ser una URL válida',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $director = InstituteDirector::create([
                'name' => $request->name,
                'signature' => $request->signature,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Director creado correctamente',
                'data' => $director,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el director: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $director = InstituteDirector::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $director,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Director no encontrado',
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'signature' => 'required|url',
        ], [
            'name.required' => 'El nombre es obligatorio',
            'name.max' => 'El nombre no puede tener más de 255 caracteres',
            'signature.required' => 'La firma es obligatoria',
            'signature.url' => 'La firma debe ser una URL válida',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $director = InstituteDirector::findOrFail($id);

            $director->update([
                'name' => $request->name,
                'signature' => $request->signature,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Director actualizado correctamente',
                'data' => $director,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el director: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $director = InstituteDirector::findOrFail($id);

            // Verificar si el director tiene certificados asociados
            if ($director->certificates()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el director porque tiene certificados asociados',
                ], 400);
            }

            $director->delete();

            return response()->json([
                'success' => true,
                'message' => 'Director eliminado correctamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el director: ' . $e->getMessage(),
            ], 500);
        }
    }
}
