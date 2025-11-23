<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use IncadevUns\CoreDomain\Models\Survey;
use IncadevUns\CoreDomain\Models\SurveyQuestion;
use IncadevUns\CoreDomain\Models\SurveyResponse;
use IncadevUns\CoreDomain\Models\ResponseDetail;
use Illuminate\Support\Facades\DB;

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
         * Agregar respuestas de ejemplo (survey_responses + response_details)
         ====================================================== */
        $users = [2, 3, 4]; // usuarios de ejemplo, sin tocar user_id = 1

        $allSurveys = [$survey1, $survey2, $survey3];

        foreach ($allSurveys as $survey) {
            $surveyQuestions = SurveyQuestion::where('survey_id', $survey->id)->get();
            foreach ($users as $userId) {
                $response = SurveyResponse::create([
                    'survey_id'     => $survey->id,
                    'user_id'       => $userId,
                    'rateable_type' => 'general', // puede ajustarse a 'course' si quieres
                    'rateable_id'   => 0,
                    'date'          => now()->format('Y-m-d'),
                ]);

                foreach ($surveyQuestions as $question) {
                    ResponseDetail::create([
                        'survey_response_id' => $response->id,
                        'survey_question_id' => $question->id,
                        'score'             => rand(3, 5), // ejemplo de puntaje 3-5
                    ]);
                }
            }
        }

        echo "✔ Encuestas, preguntas y respuestas de ejemplo sembradas correctamente.\n";
    }
}
