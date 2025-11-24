<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use IncadevUns\CoreDomain\Models\Course;
use IncadevUns\CoreDomain\Models\CourseVersion;
use IncadevUns\CoreDomain\Models\Group;
use IncadevUns\CoreDomain\Models\Enrollment;
use IncadevUns\CoreDomain\Models\EnrollmentPayment;
use IncadevUns\CoreDomain\Enums\GroupStatus;
use IncadevUns\CoreDomain\Enums\EnrollmentAcademicStatus;
use IncadevUns\CoreDomain\Enums\PaymentStatus;
use IncadevUns\CoreDomain\Enums\PaymentVerificationStatus;
use IncadevUns\CoreDomain\Models\Module;
use IncadevUns\CoreDomain\Enums\CourseVersionStatus;

class AdministrativeSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            
            $course1 = Course::firstOrCreate(
                ['name' => 'Inteligencia Artificial y Data Science'],
                ['description' => 'Curso de IA']
            );

            $course2 = Course::firstOrCreate(
                ['name' => 'Gestión de Proyectos de Transformación Digital'],
                ['description' => 'Aprende a liderar la innovación y gestión institucional en la era digital.']
            );

            $course3 = Course::firstOrCreate(
                ['name' => 'Desarrollo Web y Cloud Computing'],
                ['description' => 'Aprende a construir y desplegar aplicaciones modernas en la nube.']
            );

            $version1 = CourseVersion::firstOrCreate(
                ['name' => 'IA-DS-2025-01'],
                ['course_id' => $course1->id, 'version' => '2025-01', 'price' => 350.00, 'status' => CourseVersionStatus::Published]
            );

            $versionGPTD = CourseVersion::firstOrCreate(
                ['name' => 'GP-TD-2025-01'],
                ['course_id' => $course2->id, 'version' => '2025-01', 'price' => 300.00, 'status' => CourseVersionStatus::Published]
            );

            $versionDWCC = CourseVersion::firstOrCreate(
                ['name' => 'DW-CC-2025-01'],
                ['course_id' => $course3->id, 'version' => '2025-01', 'price' => 320.00, 'status' => CourseVersionStatus::Published]
            );

            Module::firstOrCreate(['course_version_id' => $version1->id, 'sort' => 1], ['title' => 'Fundamentos de IA', 'description' => 'Conceptos clave y aplicaciones de la IA.']);
            Module::firstOrCreate(['course_version_id' => $version1->id, 'sort' => 2], ['title' => 'IA Generativa y Contenidos', 'description' => 'Uso de IA para creación de contenidos y marketing.']);
            Module::firstOrCreate(['course_version_id' => $version1->id, 'sort' => 3], ['title' => 'Analítica de Datos con IA', 'description' => 'Análisis de datos para la toma de decisiones.']);

            Module::firstOrCreate(['course_version_id' => $versionGPTD->id, 'sort' => 1], ['title' => 'Planificación y Estrategia', 'description' => 'Definición de objetivos y planificación institucional.']);
            Module::firstOrCreate(['course_version_id' => $versionGPTD->id, 'sort' => 2], ['title' => 'Metodologías Ágiles', 'description' => 'Gestión de innovación y mejora continua.']);
            Module::firstOrCreate(['course_version_id' => $versionGPTD->id, 'sort' => 3], ['title' => 'Liderazgo y Comunicación Digital', 'description' => 'Gestión de equipos y colaboración digital.']);

            Module::firstOrCreate(['course_version_id' => $versionDWCC->id, 'sort' => 1], ['title' => 'Fundamentos del Desarrollo Web', 'description' => 'HTML, CSS, JavaScript y frameworks modernos.']);
            Module::firstOrCreate(['course_version_id' => $versionDWCC->id, 'sort' => 2], ['title' => 'Introducción a Cloud Computing (AWS)', 'description' => 'Servicios clave de AWS: EC2, S3, RDS.']);
            Module::firstOrCreate(['course_version_id' => $versionDWCC->id, 'sort' => 3], ['title' => 'Despliegue y DevOps Básico', 'description' => 'Contenedores (Docker) y despliegue continuo.']);

            
            $userModelClass = config('auth.providers.users.model', 'App\\Models\\User');
            $students = [];

            $students[] = $userModelClass::firstOrCreate(
                ['dni' => '12345678'],
                [
                    'name' => 'Juan Pérez García',
                    'email' => 'juan.perez@example.com',
                    'password' => Hash::make('password123'),
                    'phone' => '987654321',
                ]
            );

            $students[] = $userModelClass::firstOrCreate(
                ['dni' => '23456789'],
                [
                    'name' => 'María López Rodríguez',
                    'email' => 'maria.lopez@example.com',
                    'password' => Hash::make('password123'),
                    'phone' => '987654322',
                ]
            );

            $students[] = $userModelClass::firstOrCreate(
                ['dni' => '34567890'],
                [
                    'name' => 'Carlos Sánchez Torres',
                    'email' => 'carlos.sanchez@example.com',
                    'password' => Hash::make('password123'),
                    'phone' => '987654323',
                ]
            );

            $students[] = $userModelClass::firstOrCreate(
                ['dni' => '45678901'],
                [
                    'name' => 'Ana Martínez Silva',
                    'email' => 'ana.martinez@example.com',
                    'password' => Hash::make('password123'),
                    'phone' => '987654324',
                ]
            );

            $students[] = $userModelClass::firstOrCreate(
                ['dni' => '56789012'],
                [
                    'name' => 'Luis González Ramírez',
                    'email' => 'luis.gonzalez@example.com',
                    'password' => Hash::make('password123'),
                    'phone' => '987654325',
                ]
            );

            
            $group1 = Group::firstOrCreate(
                ['name' => 'Grupo A - IA-DS', 'course_version_id' => $version1->id],
                ['start_date' => Carbon::now()->addDays(10), 'end_date' => Carbon::now()->addMonths(3), 'status' => GroupStatus::Active]
            );

            $group2 = Group::firstOrCreate(
                ['name' => 'Grupo B - GP-TD', 'course_version_id' => $versionGPTD->id],
                ['start_date' => Carbon::now()->addDays(15), 'end_date' => Carbon::now()->addMonths(3), 'status' => GroupStatus::Active]
            );

            $group3 = Group::firstOrCreate(
                ['name' => 'Grupo C - DW-CC', 'course_version_id' => $versionDWCC->id],
                ['start_date' => Carbon::now()->addDays(20), 'end_date' => Carbon::now()->addMonths(3), 'status' => GroupStatus::Enrolling]
            );

            $group4 = Group::firstOrCreate(
                ['name' => 'Grupo D - IA-DS', 'course_version_id' => $version1->id],
                ['start_date' => Carbon::now()->subMonths(6), 'end_date' => Carbon::now()->subMonths(3), 'status' => GroupStatus::Completed]
            );

            
            $enrollments = [];

            $enrollments[] = Enrollment::firstOrCreate(
                ['group_id' => $group1->id, 'user_id' => $students[0]->id],
                ['payment_status' => PaymentStatus::Paid, 'academic_status' => EnrollmentAcademicStatus::Active, 'created_at' => Carbon::now()->subDays(5)]
            );

            $enrollments[] = Enrollment::firstOrCreate(
                ['group_id' => $group1->id, 'user_id' => $students[1]->id],
                ['payment_status' => PaymentStatus::Pending, 'academic_status' => EnrollmentAcademicStatus::Pending, 'created_at' => Carbon::now()->subDays(3)]
            );

            $enrollments[] = Enrollment::firstOrCreate(
                ['group_id' => $group2->id, 'user_id' => $students[2]->id],
                ['payment_status' => PaymentStatus::Paid, 'academic_status' => EnrollmentAcademicStatus::Active, 'created_at' => Carbon::now()->subDays(7)]
            );

            $enrollments[] = Enrollment::firstOrCreate(
                ['group_id' => $group2->id, 'user_id' => $students[3]->id],
                ['payment_status' => PaymentStatus::Paid, 'academic_status' => EnrollmentAcademicStatus::Active, 'created_at' => Carbon::now()->subDays(4)]
            );

            $enrollments[] = Enrollment::firstOrCreate(
                ['group_id' => $group3->id, 'user_id' => $students[4]->id],
                ['payment_status' => PaymentStatus::Pending, 'academic_status' => EnrollmentAcademicStatus::Pending, 'created_at' => Carbon::now()->subDays(2)]
            );

            
            EnrollmentPayment::firstOrCreate([
                'operation_number' => 'OP-2025-0001',
            ],[
                'enrollment_id' => $enrollments[0]->id,
                'agency_number' => 'BCP-001',
                'operation_date' => Carbon::now()->subDays(5),
                'amount' => 350.00,
                'evidence_path' => 'payments/evidence_001.jpg',
                'status' => PaymentVerificationStatus::Approved,
                'created_at' => Carbon::now()->subDays(5),
                'updated_at' => Carbon::now()->subDays(4),
            ]);

            EnrollmentPayment::firstOrCreate([
                'operation_number' => 'OP-2025-0002',
            ],[
                'enrollment_id' => $enrollments[1]->id,
                'agency_number' => 'BBVA-002',
                'operation_date' => Carbon::now()->subDays(3),
                'amount' => 350.00,
                'evidence_path' => 'payments/evidence_002.jpg',
                'status' => PaymentVerificationStatus::Pending,
                'created_at' => Carbon::now()->subDays(3),
                'updated_at' => Carbon::now()->subDays(3),
            ]);

            EnrollmentPayment::firstOrCreate([
                'operation_number' => 'OP-2025-0003',
            ],[
                'enrollment_id' => $enrollments[2]->id,
                'agency_number' => 'Interbank-003',
                'operation_date' => Carbon::now()->subDays(7),
                'amount' => 300.00,
                'evidence_path' => 'payments/evidence_003.jpg',
                'status' => PaymentVerificationStatus::Approved,
                'created_at' => Carbon::now()->subDays(7),
                'updated_at' => Carbon::now()->subDays(6),
            ]);

            EnrollmentPayment::firstOrCreate([
                'operation_number' => 'OP-2025-0004',
            ],[
                'enrollment_id' => $enrollments[3]->id,
                'agency_number' => 'BCP-004',
                'operation_date' => Carbon::now()->subMonth()->subDays(2),
                'amount' => 300.00,
                'evidence_path' => 'payments/evidence_004.jpg',
                'status' => PaymentVerificationStatus::Approved,
                'created_at' => Carbon::now()->subMonth()->subDays(2),
                'updated_at' => Carbon::now()->subMonth()->subDay(),
            ]);

            EnrollmentPayment::firstOrCreate([
                'operation_number' => 'OP-2025-0005',
            ],[
                'enrollment_id' => $enrollments[4]->id,
                'agency_number' => 'ScotiaBank-005',
                'operation_date' => Carbon::now()->subDays(2),
                'amount' => 320.00,
                'evidence_path' => 'payments/evidence_005.jpg',
                'status' => PaymentVerificationStatus::Pending,
                'created_at' => Carbon::now()->subDays(2),
                'updated_at' => Carbon::now()->subDays(2),
            ]);

            EnrollmentPayment::firstOrCreate([
                'operation_number' => 'OP-2025-0006',
            ],[
                'enrollment_id' => $enrollments[0]->id,
                'agency_number' => 'BCP-006',
                'operation_date' => Carbon::now()->subDay(),
                'amount' => 150.00,
                'evidence_path' => 'payments/evidence_006.jpg',
                'status' => PaymentVerificationStatus::Approved,
                'created_at' => Carbon::now()->subDay(),
                'updated_at' => Carbon::now(),
            ]);

            // DOCENTES Y PERFILES
            $teachers = [];

            $teachers[] = $userModelClass::firstOrCreate(
                ['dni' => '70000001'],
                [
                    'name' => 'Dr. Roberto Mendoza Silva',
                    'email' => 'roberto.mendoza@incadev.edu',
                    'password' => Hash::make('password123'),
                    'phone' => '987654330',
                ]
            );

            $teachers[] = $userModelClass::firstOrCreate(
                ['dni' => '70000002'],
                [
                    'name' => 'Mg. Patricia Flores Vargas',
                    'email' => 'patricia.flores@incadev.edu',
                    'password' => Hash::make('password123'),
                    'phone' => '987654331',
                ]
            );

            $teachers[] = $userModelClass::firstOrCreate(
                ['dni' => '70000003'],
                [
                    'name' => 'Ing. Carlos Ramírez López',
                    'email' => 'carlos.ramirez@incadev.edu',
                    'password' => Hash::make('password123'),
                    'phone' => '987654332',
                ]
            );

            $teachers[] = $userModelClass::firstOrCreate(
                ['dni' => '70000004'],
                [
                    'name' => 'Dra. Ana María Torres',
                    'email' => 'ana.torres@incadev.edu',
                    'password' => Hash::make('password123'),
                    'phone' => '987654333',
                ]
            );

            $teachers[] = $userModelClass::firstOrCreate(
                ['dni' => '70000005'],
                [
                    'name' => 'Mg. Jorge Luis Castillo',
                    'email' => 'jorge.castillo@incadev.edu',
                    'password' => Hash::make('password123'),
                    'phone' => '987654334',
                ]
            );

            // Crear perfiles de docentes
            DB::table('teacher_profiles')->insertOrIgnore([
                [
                    'user_id' => $teachers[0]->id,
                    'subject_areas' => json_encode(['Inteligencia Artificial', 'Machine Learning', 'Data Science']),
                    'professional_summary' => 'Doctor en Ciencias de la Computación con 15 años de experiencia en IA y ML. Especialista en modelos de deep learning y procesamiento de lenguaje natural.',
                    'cv_path' => 'cvs/roberto_mendoza.pdf',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ],
                [
                    'user_id' => $teachers[1]->id,
                    'subject_areas' => json_encode(['Gestión de Proyectos', 'Transformación Digital', 'Metodologías Ágiles']),
                    'professional_summary' => 'Magíster en Gestión de Proyectos con certificación PMP. 10 años liderando proyectos de transformación digital en empresas multinacionales.',
                    'cv_path' => 'cvs/patricia_flores.pdf',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ],
                [
                    'user_id' => $teachers[2]->id,
                    'subject_areas' => json_encode(['Desarrollo Web', 'Cloud Computing', 'DevOps']),
                    'professional_summary' => 'Ingeniero de Software especializado en arquitecturas cloud y desarrollo full-stack. Certificado AWS Solutions Architect.',
                    'cv_path' => 'cvs/carlos_ramirez.pdf',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ],
                [
                    'user_id' => $teachers[3]->id,
                    'subject_areas' => json_encode(['Data Science', 'Business Intelligence', 'Analítica Avanzada']),
                    'professional_summary' => 'Doctora en Estadística con especialización en análisis de datos. Experta en visualización de datos y toma de decisiones basada en datos.',
                    'cv_path' => 'cvs/ana_torres.pdf',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ],
                [
                    'user_id' => $teachers[4]->id,
                    'subject_areas' => json_encode(['Liderazgo Digital', 'Innovación', 'Gestión del Cambio']),
                    'professional_summary' => 'Magíster en Administración con especialización en transformación digital. Consultor en innovación y gestión del cambio organizacional.',
                    'cv_path' => 'cvs/jorge_castillo.pdf',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ],
            ]);

            // Asignar docentes a grupos
            DB::table('group_teachers')->insertOrIgnore([
                [
                    'group_id' => $group1->id,
                    'user_id' => $teachers[0]->id,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ],
                [
                    'group_id' => $group1->id,
                    'user_id' => $teachers[3]->id,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ],
                [
                    'group_id' => $group2->id,
                    'user_id' => $teachers[1]->id,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ],
                [
                    'group_id' => $group2->id,
                    'user_id' => $teachers[4]->id,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ],
                [
                    'group_id' => $group3->id,
                    'user_id' => $teachers[2]->id,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ],
                [
                    'group_id' => $group4->id,
                    'user_id' => $teachers[0]->id,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ],
                [
                    'group_id' => $group4->id,
                    'user_id' => $teachers[3]->id,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ],
            ]);

        });
    }
}
