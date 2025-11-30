<?php

namespace App\Http\Controllers\RecursosHumanos;

use App\Http\Controllers\Controller;
use App\Services\RecursosHumanos\ApplicantService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ApplicantController extends Controller
{
    public function __construct(
        private ApplicantService $applicantService
    ) {}

    /**
     * Obtener postulantes con filtros (para la vista principal de postulantes)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            Log::info('🎯 RRHH Applicants index called', $request->all());

            $validator = Validator::make($request->all(), [
                'search' => 'sometimes|string',
                'status' => 'sometimes|in:all,pending,under_review,shortlisted,rejected,hired,withdrawn',
                'offer_id' => 'sometimes|exists:offers,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Parámetros de filtro inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // ✅ MODIFICADO: Pasar array de filtros en lugar de DTO
            $filters = $request->only(['search', 'status', 'offer_id']);
            $applicants = $this->applicantService->getApplicants($filters);
            $stats = $this->applicantService->getApplicantsStats($filters);

            return response()->json([
                'success' => true,
                'applicants' => $applicants->map(fn($applicant) => $applicant->toArray()),
                'total_applicants' => $stats['total_applicants'],
                'total_applications' => $stats['total_applications'],
                'pending_applications' => $stats['pending_applications'],
                'accepted_applications' => $stats['accepted_applications'],
                'filters' => $filters,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error in RRHH Applicants index: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Error al cargar postulantes',
            ], 500);
        }
    }

    /**
     * Obtener un postulante específico
     */
    public function show($id): JsonResponse
    {
        try {
            $applicant = $this->applicantService->getApplicantById($id);

            if (!$applicant) {
                return response()->json([
                    'success' => false,
                    'error' => 'Postulante no encontrado',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $applicant->toArray(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error in RRHH Applicants show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al cargar postulante',
            ], 500);
        }
    }

    /**
     * Obtener aplicaciones de un postulante específico
     */
    public function getApplications($applicantId): JsonResponse
    {
        try {
            $applications = $this->applicantService->getApplicationsByApplicant($applicantId);

            return response()->json([
                'success' => true,
                'applications' => $applications->map(fn($app) => $app->toArray()),
                'total' => $applications->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error in RRHH Applicants getApplications: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al cargar aplicaciones',
            ], 500);
        }
    }

    /**
     * Obtener aplicaciones por oferta específica
     */
    public function getApplicationsByOffer($offerId): JsonResponse
    {
        try {
            $applications = $this->applicantService->getApplicationsByOffer($offerId);

            return response()->json([
                'success' => true,
                'applications' => $applications->map(fn($app) => $app->toArray()),
                'total' => $applications->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error in RRHH Applicants getApplicationsByOffer: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al cargar aplicaciones por oferta',
            ], 500);
        }
    }

    /**
     * Actualizar estado de una aplicación (aceptar/rechazar postulante)
     */
    public function updateApplicationStatus(Request $request, $applicationId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:pending,under_review,shortlisted,rejected,hired,withdrawn',
                'role' => 'sometimes|string|nullable' // ✅ NUEVO: Rol opcional
            ]);

            $role = $validated['role'] ?? null;

            // ✅ VALIDAR: Si se contrata, debe enviarse un rol
            if ($validated['status'] === 'hired' && !$role) {
                return response()->json([
                    'success' => false,
                    'error' => 'Se debe seleccionar un rol para contratar al postulante',
                ], 422);
            }

            $application = $this->applicantService->updateApplicationStatus(
                $applicationId, 
                $validated['status'],
                $role // ✅ Pasar el rol al servicio
            );

            $response = [
                'success' => true,
                'message' => 'Estado de aplicación actualizado',
                'data' => $application->toArray(),
            ];

            // ✅ NUEVO: Mensaje especial cuando se contrata
            if ($validated['status'] === 'hired') {
                $response['message'] = 'Postulante contratado y usuario creado automáticamente';
                $response['role_assigned'] = $role;
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Error in RRHH Applicants updateApplicationStatus: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de postulantes
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['offer_id', 'status']);
            $stats = $this->applicantService->getApplicantsStats($filters);

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Error in RRHH Applicants stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al cargar estadísticas',
            ], 500);
        }
    }

    /**
     * ✅ NUEVO: Obtener roles disponibles
     */
    public function getAvailableRoles(): JsonResponse
    {
        try {
            $roles = $this->applicantService->getAvailableRoles();
            
            return response()->json([
                'success' => true,
                'roles' => $roles
            ]);

        } catch (\Exception $e) {
            Log::error('Error in RRHH Applicants getAvailableRoles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al cargar roles disponibles',
            ], 500);
        }
    }
}