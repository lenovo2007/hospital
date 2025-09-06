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
        $mensaje = $users->total() > 0 ? 'Listado de usuarios.' : 'usuario no encontrado';
        return response()->json([
            'status' => true,
            'mensaje' => $mensaje,
            'data' => $users,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // GET /api/users/email/{email}
    public function showByEmail(Request $request, string $email)
    {
        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json([
                'status' => true,
                'mensaje' => 'usuario no encontrado',
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
                'status' => true,
                'mensaje' => 'usuario no encontrado',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $baseRules = [
            'tipo' => ['sometimes','required','string','max:255'],
            'rol' => ['sometimes','required','string','max:255'],
            'nombre' => ['sometimes','required','string','max:255'],
            'apellido' => ['sometimes','required','string','max:255'],
            'genero' => ['nullable','string','max:255'],
            'cedula' => ['sometimes','required','string','max:255', Rule::unique('users','cedula')->ignore($user->id)],
            'telefono' => ['nullable','string','max:255'],
            'direccion' => ['nullable','string','max:255'],
            'hospital_id' => ['nullable','integer','exists:hospitales,id'],
            'sede_id' => ['nullable','integer','exists:sedes,id'],
            'email' => ['sometimes','required','string','email','max:255', Rule::unique('users','email')->ignore($user->id)],
            'password' => ['nullable','string','min:8'],
            'status' => ['nullable','in:activo,inactivo'],
        ];
        $v = validator($request->all(), $baseRules, [
            'email.unique' => 'El correo electrónico ya ha sido registrado para otro usuario.',
            'cedula.unique' => 'La cédula ya ha sido registrada para otro usuario.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);
        $v->after(function ($validator) use ($request) {
            $pwd = $request->input('password');
            if (!empty($pwd)) {
                if (!preg_match('/[A-Z]/', $pwd)) {
                    $validator->errors()->add('password', 'La contraseña debe contener al menos una letra mayúscula.');
                }
                if (!preg_match('/[a-z]/', $pwd)) {
                    $validator->errors()->add('password', 'La contraseña debe contener al menos una letra minúscula.');
                }
                if (!preg_match('/[0-9]/', $pwd)) {
                    $validator->errors()->add('password', 'La contraseña debe contener al menos un número.');
                }
                if (!preg_match('/[\W_]/', $pwd)) {
                    $validator->errors()->add('password', 'La contraseña debe contener al menos un símbolo o carácter especial.');
                }
            }
        });
        $data = $v->validate();

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
        $user = User::with(['hospital','sede'])->where('cedula', $cedula)->first();
        if (!$user) {
            return response()->json([
                'status' => true,
                'mensaje' => 'usuario no encontrado',
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
                'status' => true,
                'mensaje' => 'usuario no encontrado',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $v = validator($request->all(), [
            'tipo' => ['sometimes','required','string','max:255'],
            'rol' => ['sometimes','required','string','max:255'],
            'nombre' => ['sometimes','required','string','max:255'],
            'apellido' => ['sometimes','required','string','max:255'],
            'genero' => ['nullable','string','max:255'],
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
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);
        $v->after(function ($validator) use ($request) {
            $pwd = $request->input('password');
            if (!empty($pwd)) {
                if (!preg_match('/[A-Z]/', $pwd)) { $validator->errors()->add('password', 'La contraseña debe contener al menos una letra mayúscula.'); }
                if (!preg_match('/[a-z]/', $pwd)) { $validator->errors()->add('password', 'La contraseña debe contener al menos una letra minúscula.'); }
                if (!preg_match('/[0-9]/', $pwd)) { $validator->errors()->add('password', 'La contraseña debe contener al menos un número.'); }
                if (!preg_match('/[\\W_]/', $pwd)) { $validator->errors()->add('password', 'La contraseña debe contener al menos un símbolo o carácter especial.'); }
            }
        });
        $data = $v->validate();

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
                'status' => true,
                'mensaje' => 'usuario no encontrado',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $v = validator($request->all(), [
            'password' => ['required','string','min:8'],
        ], [
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);
        $v->after(function ($validator) use ($request) {
            $pwd = $request->input('password');
            if (!empty($pwd)) {
                if (!preg_match('/[A-Z]/', $pwd)) { $validator->errors()->add('password', 'La contraseña debe contener al menos una letra mayúscula.'); }
                if (!preg_match('/[a-z]/', $pwd)) { $validator->errors()->add('password', 'La contraseña debe contener al menos una letra minúscula.'); }
                if (!preg_match('/[0-9]/', $pwd)) { $validator->errors()->add('password', 'La contraseña debe contener al menos un número.'); }
                if (!preg_match('/[\W_]/', $pwd)) { $validator->errors()->add('password', 'La contraseña debe contener al menos un símbolo o carácter especial.'); }
            }
        });
        $data = $v->validate();

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
                'status' => true,
                'mensaje' => 'usuario no encontrado',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $v = validator($request->all(), [
            'password' => ['required','string','min:8'],
        ], [
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);
        $v->after(function ($validator) use ($request) {
            $pwd = $request->input('password');
            if (!empty($pwd)) {
                if (!preg_match('/[A-Z]/', $pwd)) { $validator->errors()->add('password', 'La contraseña debe contener al menos una letra mayúscula.'); }
                if (!preg_match('/[a-z]/', $pwd)) { $validator->errors()->add('password', 'La contraseña debe contener al menos una letra minúscula.'); }
                if (!preg_match('/[0-9]/', $pwd)) { $validator->errors()->add('password', 'La contraseña debe contener al menos un número.'); }
                if (!preg_match('/[\\W_]/', $pwd)) { $validator->errors()->add('password', 'La contraseña debe contener al menos un símbolo o carácter especial.'); }
            }
        });
        $data = $v->validate();

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
        // Normalizar: convertir strings vacíos a null
        $payload = $request->all();
        array_walk($payload, function (&$v) {
            if (is_string($v) && trim($v) === '') { $v = null; }
        });

        // Reglas base
        $rules = [
            'tipo' => ['sometimes','nullable','string','max:255'],
            'rol' => ['sometimes','nullable','string','max:255'],
            'nombre' => ['sometimes','nullable','string','max:255'],
            'apellido' => ['sometimes','nullable','string','max:255'],
            'genero' => ['sometimes','nullable','string','max:255'],
            'cedula' => ['sometimes','nullable','string','max:255','unique:users,cedula'],
            'telefono' => ['sometimes','nullable','string','max:255'],
            'direccion' => ['sometimes','nullable','string','max:255'],
            'hospital_id' => ['sometimes','nullable','integer','exists:hospitales,id'],
            'sede_id' => ['sometimes','nullable','integer','exists:sedes,id'],
            'can_view' => ['sometimes','nullable','boolean'],
            'can_create' => ['sometimes','nullable','boolean'],
            'can_update' => ['sometimes','nullable','boolean'],
            'can_delete' => ['sometimes','nullable','boolean'],
            'email' => ['sometimes','nullable','string','email','max:255','unique:users,email'],
            'status' => ['sometimes','nullable','in:activo,inactivo'],
        ];
        // Password obligatorio con longitud mínima
        $rules['password'] = ['required','string','min:8'];

        $v = validator($payload, $rules, [
            'email.unique' => 'El correo electrónico ya ha sido registrado previamente.',
            'cedula.unique' => 'La cédula ya ha sido registrada previamente.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);
        $v->after(function ($validator) use ($payload) {
            $pwd = $payload['password'] ?? null;
            if (!empty($pwd)) {
                if (!preg_match('/[A-Z]/', $pwd)) { $validator->errors()->add('password', 'La contraseña debe contener al menos una letra mayúscula.'); }
                if (!preg_match('/[a-z]/', $pwd)) { $validator->errors()->add('password', 'La contraseña debe contener al menos una letra minúscula.'); }
                if (!preg_match('/[0-9]/', $pwd)) { $validator->errors()->add('password', 'La contraseña debe contener al menos un número.'); }
                if (!preg_match('/[\\W_]/', $pwd)) { $validator->errors()->add('password', 'La contraseña debe contener al menos un símbolo o carácter especial.'); }
            }
        });
        $data = $v->validate();

        // Valores por defecto si no vienen
        $data['rol'] = $data['rol'] ?? 'user';
        $data['tipo'] = $data['tipo'] ?? 'cliente';
        $data['status'] = $data['status'] ?? 'activo';

        // Hash de contraseña
        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);
        // Refrescar para garantizar casts y timestamps actualizados
        $user->refresh();

        $responseData = $user->toArray();

        return response()->json([
            'status' => true,
            'mensaje' => 'Usuario creado.',
            'data' => $responseData,
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
        $v = validator($request->all(), [
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
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);
        $v->after(function ($validator) use ($request) {
            $pwd = $request->input('password');
            if (!empty($pwd)) {
                if (!preg_match('/[A-Z]/', $pwd)) { $validator->errors()->add('password', 'La contraseña debe contener al menos una letra mayúscula.'); }
                if (!preg_match('/[a-z]/', $pwd)) { $validator->errors()->add('password', 'La contraseña debe contener al menos una letra minúscula.'); }
                if (!preg_match('/[0-9]/', $pwd)) { $validator->errors()->add('password', 'La contraseña debe contener al menos un número.'); }
                if (!preg_match('/[\\W_]/', $pwd)) { $validator->errors()->add('password', 'La contraseña debe contener al menos un símbolo o carácter especial.'); }
            }
        });
        $data = $v->validate();

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
                'status' => true,
                'mensaje' => 'usuario no encontrado',
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
        $v = validator($request->all(), [
            'token' => ['required','string'],
            'password' => ['required','string','min:8'],
        ], [
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);
        $v->after(function ($validator) use ($request) {
            $pwd = $request->input('password');
            if (!empty($pwd)) {
                if (!preg_match('/[A-Z]/', $pwd)) { $validator->errors()->add('password', 'La contraseña debe contener al menos una letra mayúscula.'); }
                if (!preg_match('/[a-z]/', $pwd)) { $validator->errors()->add('password', 'La contraseña debe contener al menos una letra minúscula.'); }
                if (!preg_match('/[0-9]/', $pwd)) { $validator->errors()->add('password', 'La contraseña debe contener al menos un número.'); }
                if (!preg_match('/[\W_]/', $pwd)) { $validator->errors()->add('password', 'La contraseña debe contener al menos un símbolo o carácter especial.'); }
            }
        });
        $data = $v->validate();

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
