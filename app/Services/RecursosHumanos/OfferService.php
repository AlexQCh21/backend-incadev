<?php

namespace App\Services\RecursosHumanos;

use App\Repositories\RecursosHumanos\OfferRepository;
use App\DTOs\RecursosHumanos\OfferDTO;
use App\DTOs\RecursosHumanos\OfferFiltersDTO;
use App\DTOs\RecursosHumanos\CreateOfferDTO;
use App\DTOs\RecursosHumanos\UpdateOfferDTO;
use App\DTOs\RecursosHumanos\ApplicationDTO;
use App\DTOs\RecursosHumanos\CreateApplicationDTO;
use IncadevUns\CoreDomain\Models\Offer;
use IncadevUns\CoreDomain\Models\Application;
use IncadevUns\CoreDomain\Enums\OfferStatus;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class OfferService
{
    public function __construct(
        private OfferRepository $offerRepository
    ) {}

    public function getOffers(OfferFiltersDTO $filters): Collection
    {
        $offers = $this->offerRepository->getOffersWithFilters($filters);

        return $offers->map(function ($offer) {
            return $this->formatOfferData($offer);
        });
    }

    public function getOfferById(int $id): ?OfferDTO
    {
        $offer = $this->offerRepository->findOfferById($id);
        
        if (!$offer) {
            return null;
        }

        return $this->formatOfferData($offer);
    }

    public function createOffer(CreateOfferDTO $dto): OfferDTO
    {
        $offer = $this->offerRepository->createOffer([
            'title' => $dto->title,
            'description' => $dto->description,
            'requirements' => $dto->requirements,
            'closing_date' => $dto->closing_date,
            'available_positions' => $dto->available_positions,
            'status' => OfferStatus::Active,
        ]);

        return $this->formatOfferData($offer);
    }

    public function updateOffer(int $id, UpdateOfferDTO $dto): OfferDTO
    {
        $offer = $this->offerRepository->findOfferById($id);

        if (!$offer) {
            throw new \Exception('Oferta no encontrada');
        }

        $updateData = [
            'title' => $dto->title,
            'description' => $dto->description,
            'requirements' => $dto->requirements,
            'closing_date' => $dto->closing_date,
            'available_positions' => $dto->available_positions,
        ];

        if ($dto->status) {
            $updateData['status'] = OfferStatus::from($dto->status);
        }

        $updatedOffer = $this->offerRepository->updateOffer($offer, $updateData);
        return $this->formatOfferData($updatedOffer);
    }

    public function deleteOffer(int $id): array
    {
        $offer = $this->offerRepository->findOfferById($id);

        if (!$offer) {
            throw new \Exception('Oferta no encontrada');
        }

        $this->offerRepository->deleteOffer($offer);

        return [
            'message' => 'Oferta eliminada correctamente'
        ];
    }

    public function closeOffer(int $id): OfferDTO
    {
        $offer = $this->offerRepository->findOfferById($id);

        if (!$offer) {
            throw new \Exception('Oferta no encontrada');
        }

        $closedOffer = $this->offerRepository->closeOffer($offer);
        
        // Rechazar todas las aplicaciones pendientes
        $this->rejectPendingApplications($offer);

        return $this->formatOfferData($closedOffer);
    }

    public function createApplication(CreateApplicationDTO $dto): ApplicationDTO
    {
        $application = $this->offerRepository->createApplication([
            'offer_id' => $dto->offer_id,
            'name' => $dto->name,
            'email' => $dto->email,
            'phone' => $dto->phone,
            'dni' => $dto->dni,
            'cv_path' => $dto->cv_path,
        ]);

        return $this->formatApplicationData($application);
    }

    public function getApplicationsByOffer(int $offerId): Collection
    {
        $applications = $this->offerRepository->getApplicationsByOffer($offerId);

        return $applications->map(function ($application) {
            return $this->formatApplicationData($application);
        });
    }

    public function getOffersStats(): array
    {
        return [
            'total_active_offers' => $this->offerRepository->getActiveOffersCount(),
            'total_closed_offers' => $this->offerRepository->getClosedOffersCount(),
            'total_applications' => $this->offerRepository->getTotalApplicationsCount(),
            'pending_applications' => $this->offerRepository->getPendingApplicationsCount(),
        ];
    }

    private function formatOfferData(Offer $offer): OfferDTO
    {
        return OfferDTO::fromArray([
            'id' => $offer->id,
            'title' => $offer->title,
            'description' => $offer->description,
            'requirements' => $offer->requirements ?? [],
            'closing_date' => $offer->closing_date?->format('Y-m-d'),
            'available_positions' => $offer->available_positions ?? 1,
            'applications_count' => $offer->applications_count ?? 0,
            'pending_applications' => $offer->pending_applications ?? 0,
            'accepted_applications' => $offer->accepted_applications ?? 0,
            'status' => $offer->status->value,
            'created_at' => $offer->created_at->toISOString(),
            'updated_at' => $offer->updated_at->toISOString(),
        ]);
    }

    private function formatApplicationData(Application $application): ApplicationDTO
    {
        return ApplicationDTO::fromArray([
            'id' => $application->id,
            'offer_id' => $application->offer_id,
            'applicant_id' => $application->applicant_id,
            'applicant_name' => $application->applicant->name,
            'applicant_email' => $application->applicant->email,
            'applicant_phone' => $application->applicant->phone,
            'applicant_dni' => $application->applicant->dni,
            'cv_path' => $application->cv_path,
            'status' => $application->status->value,
            'created_at' => $application->created_at->toISOString(),
            'updated_at' => $application->updated_at->toISOString(),
            'offer' => $application->offer ? [
                'id' => $application->offer->id,
                'title' => $application->offer->title,
            ] : null,
            'applicant' => [
                'id' => $application->applicant->id,
                'name' => $application->applicant->name,
                'email' => $application->applicant->email,
                'phone' => $application->applicant->phone,
                'dni' => $application->applicant->dni,
            ],
        ]);
    }

    private function rejectPendingApplications(Offer $offer): void
    {
        $offer->applications()
            ->where('status', 'pending')
            ->update(['status' => 'rejected']);
    }
}