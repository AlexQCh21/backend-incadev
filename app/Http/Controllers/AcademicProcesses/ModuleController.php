<?php

namespace App\Http\Controllers\AcademicProcesses;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use IncadevUns\CoreDomain\Models\Course;
use IncadevUns\CoreDomain\Models\CourseVersion;
use IncadevUns\CoreDomain\Models\Module;

class ModuleController extends Controller
{
    /**
     * Obtener todos los cursos con sus versiones
     */
    public function getCourses()
    {
        try {
            $courses = Course::with(['versions' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }])->orderBy('name')->get();

            return response()->json([
                'success' => true,
                'data' => $courses
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los cursos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un curso específico con su versión y módulos
     */
    public function getCourseVersion($courseVersionId)
    {
        try {
            $courseVersion = CourseVersion::with([
                'course',
                'modules' => function ($query) {
                    $query->orderBy('sort');
                }
            ])->findOrFail($courseVersionId);

            return response()->json([
                'success' => true,
                'data' => $courseVersion
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la versión del curso',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Crear un nuevo módulo
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'course_version_id' => 'required|exists:course_versions,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sort' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Si no se proporciona sort, asignar el siguiente número
            if (!$request->has('sort')) {
                $maxSort = Module::where('course_version_id', $request->course_version_id)
                    ->max('sort');
                $request->merge(['sort' => ($maxSort ?? -1) + 1]);
            }

            $module = Module::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Módulo creado exitosamente',
                'data' => $module
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el módulo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un módulo existente
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'sort' => 'sometimes|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $module = Module::findOrFail($id);
            $module->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Módulo actualizado exitosamente',
                'data' => $module
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el módulo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un módulo
     */
    public function destroy($id)
    {
        try {
            $module = Module::findOrFail($id);
            
            // Verificar si tiene sesiones o exámenes asociados
            $hasClassSessions = $module->classSessions()->exists();
            $hasExams = $module->exams()->exists();

            if ($hasClassSessions || $hasExams) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el módulo porque tiene sesiones de clase o exámenes asociados'
                ], 400);
            }

            $module->delete();

            return response()->json([
                'success' => true,
                'message' => 'Módulo eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el módulo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reordenar módulos
     */
    public function reorder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'modules' => 'required|array',
            'modules.*.id' => 'required|exists:modules,id',
            'modules.*.sort' => 'required|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            foreach ($request->modules as $moduleData) {
                Module::where('id', $moduleData['id'])
                    ->update(['sort' => $moduleData['sort']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Módulos reordenados exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al reordenar los módulos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}