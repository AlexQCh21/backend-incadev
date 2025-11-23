<?php

namespace App\Http\Controllers\AcademicProcesses;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use IncadevUns\CoreDomain\Models\AcademicSettings;

class AcademicSettingsController extends Controller
{
    /**
     * Get current academic settings
     */
    public function index(): JsonResponse
    {
        try {
            Log::info('Obteniendo configuración académica');

            $settings = DB::table('academic_settings')->first();

            if (!$settings) {
                Log::info('No se encontró configuración académica, retornando valores por defecto');
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'id' => null,
                        'base_grade' => 20,
                        'min_passing_grade' => 11,
                        'absence_percentage' => 30.00,
                    ]
                ]);
            }

            Log::info('Configuración académica obtenida exitosamente', [
                'id' => $settings->id
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $settings->id,
                    'base_grade' => (int) $settings->base_grade,
                    'min_passing_grade' => (int) $settings->min_passing_grade,
                    'absence_percentage' => (float) $settings->absence_percentage,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener configuración académica', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la configuración académica',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Update academic settings
     * PUT /api/gestion-academica/academic-settings
     */
    public function update(Request $request): JsonResponse
    {
        Log::info('Iniciando actualización de configuración académica', [
            'request_data' => $request->all()
        ]);

        try {
            // Validación de entrada
            $validator = Validator::make($request->all(), [
                'base_grade' => 'required|integer|min:1|max:100',
                'min_passing_grade' => 'required|integer|min:1|max:100',
                'absence_percentage' => 'required|numeric|min:0|max:100',
            ], [
                'base_grade.required' => 'La nota base es requerida',
                'base_grade.integer' => 'La nota base debe ser un número entero',
                'base_grade.min' => 'La nota base debe ser mínimo 1',
                'base_grade.max' => 'La nota base debe ser máximo 100',
                'min_passing_grade.required' => 'La nota aprobatoria es requerida',
                'min_passing_grade.integer' => 'La nota aprobatoria debe ser un número entero',
                'min_passing_grade.min' => 'La nota aprobatoria debe ser mínimo 1',
                'min_passing_grade.max' => 'La nota aprobatoria debe ser máximo 100',
                'absence_percentage.required' => 'El porcentaje de inasistencias es requerido',
                'absence_percentage.numeric' => 'El porcentaje de inasistencias debe ser un número',
                'absence_percentage.min' => 'El porcentaje de inasistencias debe ser mínimo 0',
                'absence_percentage.max' => 'El porcentaje de inasistencias debe ser máximo 100',
            ]);

            if ($validator->fails()) {
                Log::warning('Validación fallida en actualización de configuración académica', [
                    'errors' => $validator->errors()->toArray()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            // Validar lógica de negocio
            if ($validated['min_passing_grade'] > $validated['base_grade']) {
                Log::warning('Validación de lógica de negocio fallida', [
                    'min_passing_grade' => $validated['min_passing_grade'],
                    'base_grade' => $validated['base_grade']
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'La nota mínima aprobatoria debe ser menor o igual a la nota base'
                ], 422);
            }

            DB::beginTransaction();

            try {
                // Obtener configuración existente
                $settings = DB::table('academic_settings')->first();

                if ($settings) {
                    // Actualizar registro existente
                    Log::info('Actualizando configuración académica existente', [
                        'id' => $settings->id,
                        'old_values' => [
                            'base_grade' => $settings->base_grade,
                            'min_passing_grade' => $settings->min_passing_grade,
                            'absence_percentage' => $settings->absence_percentage,
                        ],
                        'new_values' => $validated
                    ]);

                    $updated = DB::table('academic_settings')
                        ->where('id', $settings->id)
                        ->update([
                            'base_grade' => $validated['base_grade'],
                            'min_passing_grade' => $validated['min_passing_grade'],
                            'absence_percentage' => (float) $validated['absence_percentage'],
                            'updated_at' => now(),
                        ]);

                    if (!$updated) {
                        throw new \Exception('No se pudo actualizar la configuración académica');
                    }

                    $updatedSettings = DB::table('academic_settings')
                        ->where('id', $settings->id)
                        ->first();

                    Log::info('Configuración académica actualizada exitosamente', [
                        'id' => $settings->id
                    ]);

                } else {
                    // Crear nuevo registro si no existe
                    Log::info('Creando nueva configuración académica', [
                        'values' => $validated
                    ]);

                    $id = DB::table('academic_settings')
                        ->insertGetId([
                            'base_grade' => $validated['base_grade'],
                            'min_passing_grade' => $validated['min_passing_grade'],
                            'absence_percentage' => (float) $validated['absence_percentage'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                    if (!$id) {
                        throw new \Exception('No se pudo crear la configuración académica');
                    }

                    $updatedSettings = DB::table('academic_settings')
                        ->where('id', $id)
                        ->first();

                    Log::info('Nueva configuración académica creada exitosamente', [
                        'id' => $id
                    ]);
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Configuración académica actualizada exitosamente',
                    'data' => [
                        'id' => $updatedSettings->id,
                        'base_grade' => (int) $updatedSettings->base_grade,
                        'min_passing_grade' => (int) $updatedSettings->min_passing_grade,
                        'absence_percentage' => (float) $updatedSettings->absence_percentage,
                    ]
                ], 200);

            } catch (\Illuminate\Database\QueryException $e) {
                DB::rollBack();
                
                Log::error('Error de base de datos al actualizar configuración académica', [
                    'error' => $e->getMessage(),
                    'sql' => $e->getSql(),
                    'code' => $e->getCode()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error de base de datos al guardar la configuración',
                    'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
                ], 500);

            } catch (\Exception $e) {
                DB::rollBack();
                
                Log::error('Error inesperado en transacción de configuración académica', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error inesperado al actualizar la configuración académica',
                    'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error general en actualización de configuración académica', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la configuración académica',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }
}