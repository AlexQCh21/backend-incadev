<?php

namespace App\Repositories\RecursosHumanos;

use IncadevUns\CoreDomain\Models\Applicant;
use IncadevUns\CoreDomain\Models\Application;
use App\DTOs\RecursosHumanos\ApplicantFiltersDTO;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ApplicantRepository
{
    public function getApplicantsWithFilters(ApplicantFiltersDTO $filters): Collection
    {
        $query = Applicant::withCount(['applications']);

        // Aplicar filtro de búsqueda
        if ($filters->search) {
            $searchTerm = strtolower($filters->search);
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"])
                  ->orWhereRaw('LOWER(email) LIKE ?', ["%{$searchTerm}%"])
                  ->orWhereRaw('LOWER(dni) LIKE ?', ["%{$searchTerm}%"]);
            });
        }

        // Aplicar filtro de estado
        if ($filters->status !== 'all') {
            $query->whereHas('applications', function ($q) use ($filters) {
                $q->where('status', $filters->status);
            });
        }

        // Aplicar filtro de oferta
        if ($filters->offer_id) {
            $query->whereHas('applications', function ($q) use ($filters) {
                $q->where('offer_id', $filters->offer_id);
            });
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function findApplicantById(int $id): ?Applicant
    {
        return Applicant::withCount(['applications'])->find($id);
    }

    public function getApplicationsByApplicant(int $applicantId): Collection
    {
        return Application::with(['offer', 'applicant'])
            ->where('applicant_id', $applicantId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findApplicationById(int $id): ?Application
    {
        return Application::with(['offer', 'applicant'])->find($id);
    }

    public function updateApplicationStatus(Application $application, string $status): Application
    {
        $application->update(['status' => $status]);
        return $application->fresh(['offer', 'applicant']);
    }

    public function getApplicantsStats(): array
    {
        return [
            'total_applicants' => Applicant::count(),
            'total_applications' => Application::count(),
            'pending_applications' => Application::where('status', 'pending')->count(),
            'accepted_applications' => Application::where('status', 'accepted')->count(),
        ];
    }
}