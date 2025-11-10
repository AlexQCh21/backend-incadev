<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use IncadevUns\CoreDomain\Models\Group;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'role:system_viewer']);
    }

    public function getGroups()
    {
        try {
            $groups = Group::all();

            return response()->json([
                'success' => true,
                'message' => 'Lista de grupos obtenida correctamente',
                'data' => $groups,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los grupos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
