<?php

namespace App\Repositories\RecursosHumanos;

use IncadevUns\CoreDomain\Models\Offer;
use IncadevUns\CoreDomain\Models\Application;
use IncadevUns\CoreDomain\Models\Applicant;
use IncadevUns\CoreDomain\Enums\OfferStatus;
use App\DTOs\RecursosHumanos\OfferFiltersDTO;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class OfferRepository
{
    public function getOffersWithFilters(OfferFiltersDTO $filters): Collection
    {
        $query = Offer::withCount([
            'applications',
            'applications as pending_applications' => function ($query) {
                $query->where('status', 'pending');
            },
            'applications as accepted_applications' => function ($query) {
                $query->where('status', 'accepted');
            }
        ]);

        // Aplicar filtro de búsqueda
        if ($filters->search) {
            $searchTerm = strtolower($filters->search);
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(title) LIKE ?', ["%{$searchTerm}%"])
                  ->orWhereRaw('LOWER(description) LIKE ?', ["%{$searchTerm}%"]);
            });
        }

        // Aplicar filtro de estado - AHORA USANDO EL CAMPO STATUS
        if ($filters->status !== 'all') {
            if ($filters->status === 'active') {
                $query->where('status', OfferStatus::Active);
            } elseif ($filters->status === 'closed') {
                $query->where('status', OfferStatus::Closed);
            }
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function findOfferById(int $id): ?Offer
    {
        return Offer::withCount([
            'applications',
            'applications as pending_applications' => function ($query) {
                $query->where('status', 'pending');
            },
            'applications as accepted_applications' => function ($query) {
                $query->where('status', 'accepted');
            }
        ])->find($id);
    }

    public function createOffer(array $data): Offer
    {
        return Offer::create($data);
    }

    public function updateOffer(Offer $offer, array $data): Offer
    {
        $offer->update($data);
        return $offer->fresh();
    }

    public function deleteOffer(Offer $offer): bool
    {
        return $offer->delete();
    }

    public function closeOffer(Offer $offer): Offer
    {
        // AHORA ACTUALIZA EL STATUS A CLOSED
        $offer->update([
            'status' => OfferStatus::Closed,
            'closing_date' => Carbon::now() // Opcional: también actualizar la fecha de cierre
        ]);
        return $offer->fresh();
    }

    public function getApplicationsByOffer(int $offerId): Collection
    {
        return Application::with(['offer', 'applicant'])
            ->where('offer_id', $offerId)
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
        return $application->fresh();
    }

    public function createApplication(array $data): Application
    {
        // Buscar o crear applicant
        $applicant = Applicant::firstOrCreate(
            [
                'email' => $data['email'],
                'dni' => $data['dni'] ?? null
            ],
            [
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
            ]
        );

        // Crear application
        return Application::create([
            'offer_id' => $data['offer_id'],
            'applicant_id' => $applicant->id,
            'cv_path' => $data['cv_path'],
            'status' => 'pending',
        ]);
    }

    public function getActiveOffersCount(): int
    {
        // AHORA USA EL CAMPO STATUS
        return Offer::where('status', OfferStatus::Active)->count();
    }

    public function getClosedOffersCount(): int
    {
        return Offer::where('status', OfferStatus::Closed)->count();
    }

    public function getTotalApplicationsCount(): int
    {
        return Application::count();
    }

    public function getPendingApplicationsCount(): int
    {
        return Application::where('status', 'pending')->count();
    }
}