<?php

namespace App\Http\Controllers\AcademicProcesses;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use IncadevUns\CoreDomain\Enums\GroupStatus;

class TeacherGroupController extends Controller
{
    public function index(Request $request)
    {
        $stats = $this->getStats();

        $groupsQuery = DB::table('groups')
            ->leftJoin('course_versions', 'groups.course_version_id', '=', 'course_versions.id')
            ->leftJoin('courses', 'course_versions.course_id', '=', 'courses.id')
            ->select([
                'groups.id',
                'groups.course_version_id',
                'groups.name',
                'groups.start_date',
                'groups.end_date',
                'groups.status',
                'courses.name as course_name',
            ])
            ->orderByDesc('groups.id');

        $groups = $groupsQuery->get();

        foreach ($groups as $group) {
            $teachers = DB::table('group_teachers')
                ->join('users', 'group_teachers.user_id', '=', 'users.id')
                ->leftJoin('teacher_profiles', 'users.id', '=', 'teacher_profiles.user_id')
                ->where('group_teachers.group_id', $group->id)
                ->select([
                    'teacher_profiles.id',
                    'users.id as user_id',
                    'users.name as user_name',
                    'users.email as user_email',
                    'teacher_profiles.subject_areas',
                    'teacher_profiles.professional_summary',
                    'teacher_profiles.cv_path',
                ])
                ->get();

            foreach ($teachers as $teacher) {
                if ($teacher->subject_areas) {
                    $subjectAreas = json_decode($teacher->subject_areas, true);
                    $teacher->subject_area = is_array($subjectAreas) && count($subjectAreas) > 0 
                        ? $subjectAreas[0] 
                        : 'Sin área';
                } else {
                    $teacher->subject_area = 'Sin área';
                }
                unset($teacher->subject_areas);
            }

            $group->teachers = $teachers;
        }

        $teachers = DB::table('teacher_profiles')
            ->join('users', 'teacher_profiles.user_id', '=', 'users.id')
            ->select([
                'teacher_profiles.id',
                'users.id as user_id',
                'users.name as user_name',
                'users.email as user_email',
                'teacher_profiles.subject_areas',
                'teacher_profiles.professional_summary',
                'teacher_profiles.cv_path',
            ])
            ->get();

        foreach ($teachers as $teacher) {
            if ($teacher->subject_areas) {
                $subjectAreas = json_decode($teacher->subject_areas, true);
                $teacher->subject_area = is_array($subjectAreas) && count($subjectAreas) > 0 
                    ? implode(', ', $subjectAreas)
                    : 'Sin área';
            } else {
                $teacher->subject_area = 'Sin área';
            }
            unset($teacher->subject_areas);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'stats' => $stats,
                'groups' => $groups,
                'teachers' => $teachers,
            ]);
        }

        return view('academic-processes.teacher-groups', compact('groups', 'teachers', 'stats'));
    }

    public function assign(Request $request)
    {
        $validated = $request->validate([
            'group_id' => 'required|integer|exists:groups,id',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $teacherProfile = DB::table('teacher_profiles')
            ->where('user_id', $validated['user_id'])
            ->first();

        if (!$teacherProfile) {
            return response()->json([
                'error' => 'El usuario seleccionado no tiene un perfil de docente.'
            ], 400);
        }

        $exists = DB::table('group_teachers')
            ->where('group_id', $validated['group_id'])
            ->where('user_id', $validated['user_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'El docente ya está asignado a este grupo.'
            ], 200);
        }

        try {
            DB::table('group_teachers')->insert([
                'group_id' => $validated['group_id'],
                'user_id' => $validated['user_id'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            Log::info('Docente asignado a grupo', [
                'group_id' => $validated['group_id'],
                'user_id' => $validated['user_id']
            ]);

            return response()->json([
                'message' => 'Docente asignado correctamente al grupo.'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al asignar docente a grupo', [
                'group_id' => $validated['group_id'],
                'user_id' => $validated['user_id'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error al asignar el docente al grupo.'
            ], 500);
        }
    }

    public function remove(Request $request)
    {
        $validated = $request->validate([
            'group_id' => 'required|integer|exists:groups,id',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $exists = DB::table('group_teachers')
            ->where('group_id', $validated['group_id'])
            ->where('user_id', $validated['user_id'])
            ->exists();

        if (!$exists) {
            return response()->json([
                'error' => 'El docente no está asignado a este grupo.'
            ], 404);
        }

        try {
            DB::table('group_teachers')
                ->where('group_id', $validated['group_id'])
                ->where('user_id', $validated['user_id'])
                ->delete();

            Log::info('Docente removido de grupo', [
                'group_id' => $validated['group_id'],
                'user_id' => $validated['user_id']
            ]);

            return response()->json([
                'message' => 'Docente removido correctamente del grupo.'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al remover docente de grupo', [
                'group_id' => $validated['group_id'],
                'user_id' => $validated['user_id'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error al remover el docente del grupo.'
            ], 500);
        }
    }

    private function getStats()
    {
        $totalGroups = DB::table('groups')->count();

        $activeGroups = DB::table('groups')
            ->where('status', GroupStatus::Active->value)
            ->count();

        $groupsWithTeachers = DB::table('groups')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('group_teachers')
                    ->whereColumn('group_teachers.group_id', 'groups.id');
            })
            ->count();

        $totalTeachers = DB::table('teacher_profiles')->count();

        return [
            'total_groups' => $totalGroups,
            'active_groups' => $activeGroups,
            'groups_with_teachers' => $groupsWithTeachers,
            'total_teachers' => $totalTeachers,
        ];
    }
}
