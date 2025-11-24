<?php

namespace App\Services\RecursosHumanos;

use IncadevUns\CoreDomain\Models\Applicant;
use IncadevUns\CoreDomain\Models\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ApplicantService
{
    public function getApplicants(array $filters = []): Collection
    {
        $query = Applicant::withCount(['applications']);

        // Aplicar filtro de búsqueda
        if (!empty($filters['search'])) {
            $searchTerm = strtolower($filters['search']);
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"])
                  ->orWhereRaw('LOWER(email) LIKE ?', ["%{$searchTerm}%"])
                  ->orWhereRaw('LOWER(dni) LIKE ?', ["%{$searchTerm}%"])
                  ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$searchTerm}%"]);
            });
        }

        // ✅ Filtrar por oferta específica
        if (!empty($filters['offer_id'])) {
            $query->whereHas('applications', function ($q) use ($filters) {
                $q->where('offer_id', $filters['offer_id']);
            });
        }

        // ✅ NUEVO: Filtrar por estado de aplicación
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->whereHas('applications', function ($q) use ($filters) {
                $q->where('status', $filters['status']);
            });
        }

        return $query->get();
    }

    public function getApplicantById(int $id): ?Applicant
    {
        return Applicant::with(['applications', 'applications.offer'])->find($id);
    }

    public function getApplicationsByApplicant(int $applicantId): Collection
    {
        return Application::with(['offer'])
            ->where('applicant_id', $applicantId)
            ->get();
    }

    // ✅ NUEVO: Obtener aplicaciones por oferta
    public function getApplicationsByOffer(int $offerId): Collection
    {
        return Application::with(['applicant', 'offer'])
            ->where('offer_id', $offerId)
            ->get();
    }

    public function updateApplicationStatus(int $applicationId, string $status): Application
    {
        $application = Application::findOrFail($applicationId);
        $application->status = $status;
        $application->save();

        return $application->load(['applicant', 'offer']);
    }

    public function getApplicantsStats(array $filters = []): array
    {
        $baseQuery = Applicant::query();
        $applicationsQuery = Application::query();
        
        // ✅ Aplicar filtro de oferta a las estadísticas
        if (!empty($filters['offer_id'])) {
            $baseQuery->whereHas('applications', function ($q) use ($filters) {
                $q->where('offer_id', $filters['offer_id']);
            });
            
            $applicationsQuery->where('offer_id', $filters['offer_id']);
        }

        // ✅ NUEVO: Aplicar filtro de estado a las estadísticas
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $baseQuery->whereHas('applications', function ($q) use ($filters) {
                $q->where('status', $filters['status']);
            });
            
            $applicationsQuery->where('status', $filters['status']);
        }

        $totalApplicants = $baseQuery->count();
        $totalApplications = $applicationsQuery->count();
        $pendingApplications = clone $applicationsQuery;
        $acceptedApplications = clone $applicationsQuery;

        return [
            'total_applicants' => $totalApplicants,
            'total_applications' => $totalApplications,
            'pending_applications' => $pendingApplications->where('status', 'pending')->count(),
            'accepted_applications' => $acceptedApplications->where('status', 'accepted')->count(),
        ];
    }
}