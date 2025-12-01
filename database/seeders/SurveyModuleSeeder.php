<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use IncadevUns\CoreDomain\Models\Survey;
use IncadevUns\CoreDomain\Models\SurveyQuestion;
use IncadevUns\CoreDomain\Models\SurveyResponse;
use IncadevUns\CoreDomain\Models\ResponseDetail;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SurveyModuleSeeder extends Seeder
{
    public function run(): void
    {
        /** ======================================================
         * 1️⃣ ENCUESTA: SATISFACCIÓN ESTUDIANTIL
         ====================================================== */
        $survey1 = Survey::create([
            'title'       => 'Encuesta de Satisfacción Estudiantil del Curso 2025',
            'description' => 'Evaluación de la satisfacción del participante al culminar el curso de capacitación.',
        ]);

        $questions1 = [
            'El nivel de satisfacción con la calidad de la enseñanza recibida en este curso es:',
            'La claridad con la que se desarrollaron los contenidos del curso fue:',
            'El apoyo brindado por los recursos académicos del curso (materiales, plataforma virtual, actividades) fue:',
            'El ambiente general de aprendizaje durante el desarrollo del curso (interacción, organización, dinámica) fue:',
            'En términos generales, mi nivel de satisfacción con este curso es:',
        ];

        foreach ($questions1 as $index => $text) {
            SurveyQuestion::create([
                'survey_id' => $survey1->id,
                'question'  => $text,
                'order'     => $index + 1,
            ]);
        }

        /** ======================================================
         * 2️⃣ ENCUESTA: SEGUIMIENTO DEL DOCENTE
         ====================================================== */
        $survey2 = Survey::create([
            'title'       => 'Encuesta de Seguimiento del Docente del Curso 2025',
            'description' => 'Evaluación del desempeño del docente al culminar el curso de capacitación.',
        ]);

        $questions2 = [
            'La forma en que el docente explicó los temas del curso fue:',
            'La disposición del docente para atender consultas y resolver dudas durante el curso fue:',
            'La variedad y pertinencia de las estrategias didácticas utilizadas por el docente (ejemplos, casos, prácticas) fue:',
            'La oportunidad y utilidad de la retroalimentación brindada por el docente sobre mis actividades y evaluaciones fue:',
            'Mi nivel de satisfacción global con el desempeño del docente en este curso es:',
        ];

        foreach ($questions2 as $index => $text) {
            SurveyQuestion::create([
                'survey_id' => $survey2->id,
                'question'  => $text,
                'order'     => $index + 1,
            ]);
        }

        /** ======================================================
         * 3️⃣ ENCUESTA: SEGUIMIENTO DEL EGRESADO
         ====================================================== */
        $survey3 = Survey::create([
            'title'       => 'Encuesta de Seguimiento del Egresado del Curso 2025',
            'description' => 'Monitoreo de impacto social y laboral luego de culminar el curso de capacitación, para medir la empleabilidad de los egresados y su contribución al desarrollo profesional.',
        ]);

        $questions3 = [
            'Desde que culminé este curso de capacitación, las oportunidades de empleo o mejora laboral que he tenido han sido:',
            'La relación entre las actividades que realizo actualmente en mi trabajo y los contenidos abordados en este curso es:',
            'El grado en que los conocimientos y habilidades adquiridos en este curso han mejorado mi desempeño profesional es:',
            'Las posibilidades de crecimiento y proyección profesional que tengo actualmente gracias a lo aprendido en este curso son:',
            'El impacto que lo aprendido en este curso tiene en mi entorno laboral y/o social es:',
        ];

        foreach ($questions3 as $index => $text) {
            SurveyQuestion::create([
                'survey_id' => $survey3->id,
                'question'  => $text,
                'order'     => $index + 1,
            ]);
        }

        /** ======================================================
         * 📊 GENERAR RESPUESTAS DE EJEMPLO - NOVIEMBRE 2025
         ====================================================== */

        // Definir usuarios de ejemplo (ajusta según tu tabla de usuarios)
        $users = range(2, 36); // 35 usuarios de ejemplo (del 2 al 36, sin contar el admin)

        $allSurveys = [
            ['survey' => $survey1, 'name' => 'Satisfacción Estudiantil'],
            ['survey' => $survey2, 'name' => 'Seguimiento Docente'],
            ['survey' => $survey3, 'name' => 'Seguimiento Egresado']
        ];

        foreach ($allSurveys as $surveyData) {
            $survey = $surveyData['survey'];
            $surveyName = $surveyData['name'];

            echo "Generando respuestas para: {$surveyName}...\n";

            $surveyQuestions = SurveyQuestion::where('survey_id', $survey->id)->get();

            // Generar respuestas SOLO de noviembre 2025
            foreach ($users as $userId) {
                // Fecha aleatoria entre el 1 y 30 de noviembre 2025
                $dayOfMonth = rand(1, 30);
                $responseDate = Carbon::create(2025, 11, $dayOfMonth);

                $response = SurveyResponse::create([
                    'survey_id'     => $survey->id,
                    'user_id'       => $userId,
                    'rateable_type' => 'course',
                    'rateable_id'   => rand(1, 10), // IDs de cursos de ejemplo
                    'date'          => $responseDate->format('Y-m-d'),
                ]);

                // Generar respuestas con distribución realista
                foreach ($surveyQuestions as $question) {
                    ResponseDetail::create([
                        'survey_response_id' => $response->id,
                        'survey_question_id' => $question->id,
                        'score'              => $this->getRealisticScore(),
                    ]);
                }
            }

            echo "✔ {$surveyName}: " . count($users) . " respuestas generadas (Noviembre 2025).\n";
        }

        // GENERAR TAMBIÉN DATOS DEL MES ANTERIOR (OCTUBRE 2025) PARA COMPARACIÓN
        echo "\nGenerando datos de comparación (Octubre 2025)...\n";

        foreach ($allSurveys as $surveyData) {
            $survey = $surveyData['survey'];
            $surveyName = $surveyData['name'];

            $surveyQuestions = SurveyQuestion::where('survey_id', $survey->id)->get();

            // Generar respuestas de OCTUBRE 2025 (para calcular tendencias)
            foreach (range(2, 25) as $userId) { // 24 usuarios en octubre (menos que en noviembre)
                $dayOfMonth = rand(1, 31);
                $responseDate = Carbon::create(2025, 10, $dayOfMonth);

                $response = SurveyResponse::create([
                    'survey_id'     => $survey->id,
                    'user_id'       => $userId,
                    'rateable_type' => 'course',
                    'rateable_id'   => rand(1, 10),
                    'date'          => $responseDate->format('Y-m-d'),
                ]);

                foreach ($surveyQuestions as $question) {
                    ResponseDetail::create([
                        'survey_response_id' => $response->id,
                        'survey_question_id' => $question->id,
                        'score'              => $this->getRealisticScore(true), // Ligeramente peor para ver tendencia positiva
                    ]);
                }
            }

            echo "✔ {$surveyName}: 24 respuestas de Octubre 2025 generadas.\n";
        }

        echo "\n✅ RESUMEN:\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "📋 3 Encuestas creadas\n";
        echo "❓ 5 Preguntas por encuesta (15 total)\n";
        echo "📅 NOVIEMBRE 2025: 35 usuarios × 3 encuestas = 105 respuestas\n";
        echo "📅 OCTUBRE 2025: 24 usuarios × 3 encuestas = 72 respuestas (para comparación)\n";
        echo "📊 TOTAL: 177 respuestas generadas\n";
        echo "💾 885 detalles de respuesta guardados\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    }

    /**
     * Ya no se necesita esta función
     */
    // Función getRandomDate() eliminada

    /**
     * Genera puntajes con distribución realista
     * - 50% excelente (5)
     * - 35% bueno (4)
     * - 12% regular (3)
     * - 3% bajo (1-2)
     *
     * @param bool $slightlyWorse Para octubre, hacer las respuestas un poco peores
     */
    private function getRealisticScore(bool $slightlyWorse = false): int
    {
        $rand = rand(1, 100);

        // Si es octubre (mes anterior), reducir un poco la calidad para ver mejora en noviembre
        if ($slightlyWorse) {
            if ($rand <= 40) { // 40% en vez de 50%
                return 5; // Excelente
            } elseif ($rand <= 75) { // 35%
                return 4; // Bueno
            } elseif ($rand <= 92) { // 17% en vez de 12%
                return 3; // Regular
            } else {
                return rand(1, 2); // 8% en vez de 3%
            }
        }

        // Distribución normal para noviembre
        if ($rand <= 50) {
            return 5; // Excelente
        } elseif ($rand <= 85) {
            return 4; // Bueno
        } elseif ($rand <= 97) {
            return 3; // Regular
        } else {
            return rand(1, 2); // Bajo
        }
    }
}
