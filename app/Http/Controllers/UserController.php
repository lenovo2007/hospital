<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use App\Mail\PasswordResetTokenMail;

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

    // GET /api/users/email/{email}
    public function showByEmail(Request $request, string $email)
    {
        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Usuario no encontrado por ese email.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
        return response()->json([
            'status' => true,
            'mensaje' => 'Usuario encontrado por email.',
            'data' => $user,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // PUT /api/users/email/{email}
    public function updateByEmail(Request $request, string $email)
    {
        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Usuario no encontrado por ese email.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

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
        ], [
            'email.unique' => 'El correo electrónico ya ha sido registrado para otro usuario.',
            'cedula.unique' => 'La cédula ya ha sido registrada para otro usuario.',
        ]);

        if (!empty($data['password'])) { $data['password'] = Hash::make($data['password']); } else { unset($data['password']); }

        $user->update($data);
        $user->refresh();
        return response()->json([
            'status' => true,
            'mensaje' => 'Usuario actualizado por email.',
            'data' => $user,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // GET /api/users/cedula/{cedula}
    public function showByCedula(Request $request, string $cedula)
    {
        $user = User::where('cedula', $cedula)->first();
        if (!$user) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Usuario no encontrado por esa cédula.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
        return response()->json([
            'status' => true,
            'mensaje' => 'Usuario encontrado por cédula.',
            'data' => $user,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // PUT /api/users/cedula/{cedula}
    public function updateByCedula(Request $request, string $cedula)
    {
        $user = User::where('cedula', $cedula)->first();
        if (!$user) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Usuario no encontrado por esa cédula.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

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
        ], [
            'email.unique' => 'El correo electrónico ya ha sido registrado para otro usuario.',
            'cedula.unique' => 'La cédula ya ha sido registrada para otro usuario.',
        ]);

        if (!empty($data['password'])) { $data['password'] = Hash::make($data['password']); } else { unset($data['password']); }

        $user->update($data);
        $user->refresh();
        return response()->json([
            'status' => true,
            'mensaje' => 'Usuario actualizado por cédula.',
            'data' => $user,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // PUT /api/users/email/{email}/password
    public function passwordByEmail(Request $request, string $email)
    {
        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Usuario no encontrado por ese email.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $data = $request->validate([
            'password' => ['required','string','min:8'],
        ]);

        $user->password = Hash::make($data['password']);
        $user->save();
        $user->refresh();

        return response()->json([
            'status' => true,
            'mensaje' => 'Contraseña actualizada por email.',
            'data' => $user,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // PUT /api/users/cedula/{cedula}/password
    public function passwordByCedula(Request $request, string $cedula)
    {
        $user = User::where('cedula', $cedula)->first();
        if (!$user) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Usuario no encontrado por esa cédula.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $data = $request->validate([
            'password' => ['required','string','min:8'],
        ]);

        $user->password = Hash::make($data['password']);
        $user->save();
        $user->refresh();

        return response()->json([
            'status' => true,
            'mensaje' => 'Contraseña actualizada por cédula.',
            'data' => $user,
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
        ], [
            'email.unique' => 'El correo electrónico ya ha sido registrado previamente.',
            'cedula.unique' => 'La cédula ya ha sido registrada previamente.',
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
        ], [
            'email.unique' => 'El correo electrónico ya ha sido registrado para otro usuario.',
            'cedula.unique' => 'La cédula ya ha sido registrada para otro usuario.',
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

    // POST /api/users/password/forgot (público)
    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['nullable','string','email'],
            'cedula' => ['nullable','string'],
        ]);

        if (empty($data['email']) && empty($data['cedula'])) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Debe proporcionar email o cédula.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $query = User::query();
        if (!empty($data['email'])) { $query->where('email', $data['email']); }
        if (!empty($data['cedula'])) { $query->orWhere('cedula', $data['cedula']); }
        $user = $query->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Usuario no encontrado.',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $token = Str::random(60);
        $user->remember_token = $token;
        // Expiración de token en 60 minutos
        $user->password_reset_expires_at = now()->addMinutes(60);
        $user->save();

        // Envío de token por correo si el usuario posee email
        if (!empty($user->email)) {
            try {
                Mail::to($user->email)->queue(new PasswordResetTokenMail($user, $token));
            } catch (\Throwable $e) {
                // No interrumpir el flujo por fallos de correo; puede revisarse el log
            }
        }
        return response()->json([
            'status' => true,
            'mensaje' => 'Token de restablecimiento generado.',
            'data' => [
                'token' => $token,
                'user_id' => $user->id,
                'email' => $user->email,
                'cedula' => $user->cedula,
            ],
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // POST /api/users/password/reset (público)
    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => ['required','string'],
            'password' => ['required','string','min:8'],
        ]);

        $user = User::where('remember_token', $data['token'])->first();
        if (!$user) {
            return response()->json([
                'status' => false,
                'mensaje' => 'Token inválido.',
                'data' => null,
                'autenticacion' => 1,
            ]);
        }

        // Validar expiración del token
        if (!empty($user->password_reset_expires_at) && $user->password_reset_expires_at->isPast()) {
            return response()->json([
                'status' => false,
                'mensaje' => 'El token ha expirado. Solicita uno nuevo.',
                'data' => null,
                'autenticacion' => 1,
            ]);
        }

        $user->password = Hash::make($data['password']);
        $user->remember_token = null;
        $user->password_reset_expires_at = null;
        $user->save();

        return response()->json([
            'status' => true,
            'mensaje' => 'Contraseña restablecida correctamente.',
            'data' => $user,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
