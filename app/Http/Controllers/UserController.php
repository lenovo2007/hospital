<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    // Listado de usuarios
    public function index(Request $request)
    {
        $status = $request->query('status', 'activo');
        if ($status === 'todos') { $status = 'all'; }
        if (!in_array($status, ['activo', 'inactivo', 'all'], true)) { $status = 'activo'; }
        $query = User::query();
        if ($status !== 'all') { $query->where('status', $status); }
        $users = $query->latest()->paginate(15);
        return response()->json([
            'status' => true,
            'mensaje' => 'Listado de usuarios.',
            'data' => $users,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // Mostrar formulario de creación (placeholder)
    public function create()
    {
        return response()->json([
            'status' => true,
            'mensaje' => 'Mostrar formulario de creación de usuario.',
            'data' => null,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // Guardar nuevo usuario
    public function store(Request $request)
    {
        $data = $request->validate([
            'tipo' => ['required','string','max:255'],
            'rol' => ['required','string','max:255'],
            'nombre' => ['required','string','max:255'],
            'apellido' => ['required','string','max:255'],
            'cedula' => ['required','string','max:255','unique:users,cedula'],
            'telefono' => ['nullable','string','max:255'],
            'direccion' => ['nullable','string','max:255'],
            'hospital_id' => ['nullable','integer','exists:hospitales,id'],
            'sede_id' => ['nullable','integer','exists:sedes,id'],
            'email' => ['required','string','email','max:255','unique:users,email'],
            'password' => ['required','string','min:8'],
            'status' => ['nullable','in:activo,inactivo'],
        ]);

        $data['password'] = Hash::make($data['password']);
        if (!isset($data['status'])) { $data['status'] = 'activo'; }

        $user = User::create($data);

        return response()->json([
            'status' => true,
            'mensaje' => 'Usuario creado.',
            'data' => $user,
        ], 201, [], JSON_UNESCAPED_UNICODE);
    }

    // Mostrar usuario
    public function show(User $user)
    {
        return response()->json([
            'status' => true,
            'mensaje' => 'Detalle de usuario.',
            'data' => $user,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // Mostrar formulario de edición (placeholder)
    public function edit(User $user)
    {
        return response()->json([
            'status' => true,
            'mensaje' => 'Mostrar formulario de edición.',
            'data' => $user,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // Actualizar usuario
    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'tipo' => ['sometimes','required','string','max:255'],
            'rol' => ['sometimes','required','string','max:255'],
            'nombre' => ['sometimes','required','string','max:255'],
            'apellido' => ['sometimes','required','string','max:255'],
            'cedula' => ['sometimes','required','string','max:255', Rule::unique('users','cedula')->ignore($user->id)],
            'telefono' => ['nullable','string','max:255'],
            'direccion' => ['nullable','string','max:255'],
            'hospital_id' => ['nullable','integer','exists:hospitales,id'],
            'sede_id' => ['nullable','integer','exists:sedes,id'],
            'email' => ['sometimes','required','string','email','max:255', Rule::unique('users','email')->ignore($user->id)],
            'password' => ['nullable','string','min:8'],
            'status' => ['nullable','in:activo,inactivo'],
        ]);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);
        $user->refresh();

        return response()->json([
            'status' => true,
            'mensaje' => 'Usuario actualizado.',
            'data' => $user,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // Eliminar usuario
    public function destroy(User $user)
    {
        $user->delete();
        return response()->json([
            'status' => true,
            'mensaje' => 'Usuario eliminado.',
            'data' => null,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
