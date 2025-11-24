<?php

namespace App\Services\RecursosHumanos;

use IncadevUns\CoreDomain\Models\Applicant;
use IncadevUns\CoreDomain\Models\Application;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

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

    public function updateApplicationStatus(int $applicationId, string $status, string $role = null): Application
    {
        $application = Application::findOrFail($applicationId);
        $application->status = $status;
        $application->save();

        // ✅ NUEVO: Crear usuario automáticamente cuando se contrata
        if ($status === 'hired' && $role) {
            $this->createUserFromApplicant($application->applicant, $role);
        }

        return $application->load(['applicant', 'offer']);
    }

    /**
     * ✅ NUEVO: Crear usuario a partir de un postulante contratado
     */
    private function createUserFromApplicant(Applicant $applicant, string $role): User
    {
        // Verificar si ya existe un usuario con este email
        $existingUser = User::where('email', $applicant->email)->first();
        
        if ($existingUser) {
            // Si ya existe, solo asignar el rol adicional
            $existingUser->assignRole($role);
            return $existingUser;
        }

        // Generar password temporal
        $tempPassword = Str::random(12);

        // Crear nuevo usuario
        $user = User::create([
            'name' => $applicant->name,
            'email' => $applicant->email,
            'password' => Hash::make($tempPassword),
            'dni' => $applicant->dni,
            'fullname' => $applicant->name,
            'phone' => $applicant->phone,
            // 'avatar' se puede dejar null por defecto
        ]);

        // Asignar rol seleccionado
        $user->assignRole($role);

        // ✅ OPCIONAL: Log para debugging
        Log::info('Usuario creado automáticamente desde postulante', [
            'applicant_id' => $applicant->id,
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $role
        ]);

        return $user;
    }

    /**
     * ✅ NUEVO: Obtener lista de roles disponibles
     */
    public function getAvailableRoles(): array
    {
        return [
            'admin' => 'Administrador',
            'support' => 'Soporte',
            'infrastructure' => 'Infraestructura',
            'security' => 'Seguridad',
            'academic_analyst' => 'Analista Académico',
            'web' => 'Desarrollador Web',
            'survey_admin' => 'Administrador de Encuestas',
            'audit_manager' => 'Gerente de Auditoría',
            'auditor' => 'Auditor',
            'human_resources' => 'Recursos Humanos',
            'financial_manager' => 'Gerente Financiero',
            'system_viewer' => 'Visualizador del Sistema',
            'enrollment_manager' => 'Gerente de Matrículas',
            'data_analyst' => 'Analista de Datos',
            'marketing' => 'Marketing',
            'marketing_admin' => 'Administrador de Marketing',
            'teacher' => 'Docente',
            'student' => 'Estudiante',
            'tutor' => 'Tutor',
            'administrative_clerk' => 'Oficinista Administrativo',
            'planner_admin' => 'Administrador de Planificación',
            'planner' => 'Planificador',
            'continuous_improvement' => 'Mejora Continua',
            'alliances_manager' => 'Gerente de Alianzas',
            'documents_manager' => 'Gerente de Documentos',
            'conversation_manager' => 'Gerente de Conversaciones',
            'viewer' => 'Visualizador'
        ];
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