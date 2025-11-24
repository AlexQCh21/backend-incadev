<?php

namespace App\Http\Controllers\RecursosHumanos;

use App\Http\Controllers\Controller;
use App\Services\RecursosHumanos\OfferService;
use App\DTOs\RecursosHumanos\OfferFiltersDTO;
use App\DTOs\RecursosHumanos\CreateOfferDTO;
use App\DTOs\RecursosHumanos\UpdateOfferDTO;
use App\DTOs\RecursosHumanos\CreateApplicationDTO;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OfferController extends Controller
{
    public function __construct(
        private OfferService $offerService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            Log::info('🎯 RRHH Offers index called', $request->all());

            $validator = Validator::make($request->all(), [
                'search' => 'sometimes|string',
                'status' => 'sometimes|in:all,active,closed'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Parámetros de filtro inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $filters = OfferFiltersDTO::fromRequest($request->all());
            $offers = $this->offerService->getOffers($filters);
            $stats = $this->offerService->getOffersStats();

            return response()->json([
                'success' => true,
                'offers' => $offers->map(fn($offer) => $offer->toArray()),
                'total_active_offers' => $stats['total_active_offers'],
                'total_closed_offers' => $stats['total_closed_offers'],
                'total_applications' => $stats['total_applications'],
                'pending_applications' => $stats['pending_applications'],
                'filters' => $filters->toArray(),
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error in RRHH Offers index: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Error al cargar ofertas',
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $offer = $this->offerService->getOfferById($id);

            if (!$offer) {
                return response()->json([
                    'success' => false,
                    'error' => 'Oferta no encontrada',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $offer->toArray(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error in RRHH Offers show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al cargar oferta',
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'requirements' => 'nullable|array',
                'requirements.*' => 'string',
                'closing_date' => 'nullable|date',
                'available_positions' => 'required|integer|min:1',
            ]);

            $dto = CreateOfferDTO::fromRequest($validated);
            $offer = $this->offerService->createOffer($dto);

            return response()->json([
                'success' => true,
                'message' => 'Oferta creada correctamente',
                'data' => $offer->toArray(),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in RRHH Offers store: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'requirements' => 'nullable|array',
                'requirements.*' => 'string',
                'closing_date' => 'nullable|date',
                'available_positions' => 'required|integer|min:1',
                'status' => 'sometimes|in:active,closed'
            ]);

            $dto = UpdateOfferDTO::fromRequest($validated);
            $offer = $this->offerService->updateOffer($id, $dto);

            return response()->json([
                'success' => true,
                'message' => 'Oferta actualizada correctamente',
                'data' => $offer->toArray(),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in RRHH Offers update: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $result = $this->offerService->deleteOffer($id);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
            ]);

        } catch (\Exception $e) {
            Log::error('Error in RRHH Offers destroy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function close($id): JsonResponse
    {
        try {
            Log::info('🔵 Close offer endpoint called', [
                'method' => request()->method(),
                'offer_id' => $id,
                'full_url' => request()->fullUrl()
            ]);

            $offer = $this->offerService->closeOffer($id);

            return response()->json([
                'success' => true,
                'message' => 'Oferta cerrada correctamente',
                'data' => $offer->toArray(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error in RRHH Offers close: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener aplicaciones de una oferta específica
     * (Necesario para el modal de aplicaciones en el frontend)
     */
    public function getApplications($offerId): JsonResponse
    {
        try {
            $applications = $this->offerService->getApplicationsByOffer($offerId);

            return response()->json([
                'success' => true,
                'applications' => $applications->map(fn($app) => $app->toArray()),
                'total' => $applications->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error in RRHH Offers getApplications: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al cargar aplicaciones',
            ], 500);
        }
    }

    /**
     * Crear nueva aplicación (postulación)
     * (Necesario para que los usuarios puedan postular)
     */
    public function storeApplication(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'offer_id' => 'required|exists:offers,id',
                'name' => 'required|string|max:255',
                'email' => 'required|email',
                'phone' => 'nullable|string|max:20',
                'dni' => 'nullable|string|max:20',
                'cv_path' => 'required|string',
            ]);

            $dto = CreateApplicationDTO::fromRequest($validated);
            $application = $this->offerService->createApplication($dto);

            return response()->json([
                'success' => true,
                'message' => 'Aplicación enviada correctamente',
                'data' => $application->toArray(),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in storeApplication: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function stats(): JsonResponse
    {
        try {
            $stats = $this->offerService->getOffersStats();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Error in RRHH Offers stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al cargar estadísticas',
            ], 500);
        }
    }
}