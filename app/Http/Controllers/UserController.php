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
        $actor = $request->user();
        
        // Verificar si el usuario tiene permiso para ver la lista de usuarios
        if ((!$actor->can_crud_user || !$actor->can_view) && !$actor->is_root) {
            return response()->json([
                'status' => false,
                'mensaje' => 'No tienes permiso para ver la lista de usuarios',
                'data' => null,
            ], 403, [], JSON_UNESCAPED_UNICODE);
        }
        
        $status = $request->query('status', 'activo');
        if ($status === 'todos') { $status = 'all'; }
        if (!in_array($status, ['activo', 'inactivo', 'all'], true)) { $status = 'activo'; }
        
        $query = User::query();
        
        // Ocultar usuarios root para actores no root
        if (!$actor->is_root) {
            $query->where('is_root', false);
        }
        
        if ($status !== 'all') { 
            $query->where('status', $status); 
        }
        
        $users = $query->latest()->paginate(15);
        $mensaje = $users->total() > 0 ? 'Listado de usuarios.' : 'No se encontraron usuarios';
        
        return response()->json([
            'status' => true,
            'mensaje' => $mensaje,
            'data' => $users,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // GET /api/users/email/{email}
    public function showByEmail(Request $request, string $email)
    {
        $actor = $request->user();
        
        // Verificar si el usuario tiene permiso para ver usuarios
        if ((!$actor->can_crud_user || !$actor->can_view) && !$actor->is_root) {
            return response()->json([
                'status' => false,
                'mensaje' => 'No tienes permiso para ver este usuario',
                'data' => null,
            ], 403, [], JSON_UNESCAPED_UNICODE);
        }
        
        $query = User::query();
        
        // Ocultar usuarios root para actores no root
        if (!$actor->is_root) { 
            $query->where('is_root', false); 
        }
        
        $user = $query->where('email', $email)->first();
        
        if (!$user) {
            return response()->json([
                'status' => true,
                'mensaje' => 'Usuario no encontrado',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
        // Autorizar vista (oculta root a no-root)
        try { $this->authorize('view', $user); }
        catch (\Illuminate\Auth\Access\AuthorizationException $e) {
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
        $this->authorize('update', $user);

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
            'can_view' => ['nullable','boolean'],
            'can_create' => ['nullable','boolean'],
            'can_update' => ['nullable','boolean'],
            'can_delete' => ['nullable','boolean'],
            'is_root' => ['sometimes','boolean'],
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
        // Solo root puede modificar is_root
        if (!$request->user() || !$request->user()->is_root) {
            unset($data['is_root']);
        }

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
        $actor = $request->user();
        $user = User::with(['hospital','sede'])
            ->where('cedula', $cedula)
            ->first();
            
        // Si no se encuentra el usuario o es root y el actor no es root
        if (!$user || ($user->is_root && (!$actor || !$actor->is_root))) {
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
        \Log::info('Iniciando updateByCedula', ['cedula' => $cedula, 'request' => $request->all()]);
        
        $actor = $request->user();
        \Log::info('Usuario autenticado', ['actor' => $actor ? $actor->id : 'No autenticado']);
        
        try {
            $user = User::where('cedula', $cedula)->first();
            \Log::info('Usuario encontrado', ['user' => $user ? $user->toArray() : 'No encontrado']);
            
            // Si no se encuentra el usuario o es root y el actor no es root
            if (!$user || ($user->is_root && (!$actor || !$actor->is_root))) {
                \Log::warning('Acceso denegado o usuario no encontrado', [
                    'user_found' => (bool)$user,
                    'user_is_root' => $user ? $user->is_root : null,
                    'actor_is_root' => $actor ? $actor->is_root : false
                ]);
                
                return response()->json([
                    'status' => true,
                    'mensaje' => 'usuario no encontrado',
                    'data' => null,
                ], 200, [], JSON_UNESCAPED_UNICODE);
            }
            
            // Authorize the update action
            if ($request->user()->cannot('update', $user)) {
                return response()->json([
                    'status' => true,
                    'mensaje' => 'usuario no encontrado',
                    'data' => null,
                ], 200, [], JSON_UNESCAPED_UNICODE);
            }
            \Log::info('Autorización exitosa para actualizar usuario');

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
                'can_view' => ['nullable','boolean'],
                'can_create' => ['nullable','boolean'],
                'can_update' => ['nullable','boolean'],
                'can_delete' => ['nullable','boolean'],
                'is_root' => ['sometimes','boolean'],
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
                    if (!preg_match('/[\W_]/', $pwd)) { $validator->errors()->add('password', 'La contraseña debe contener al menos un símbolo o carácter especial.'); }
                }
            });
            
            $data = $v->validate();
            \Log::info('Datos validados', ['data' => $data]);

            if (!empty($data['password'])) { 
                $data['password'] = Hash::make($data['password']); 
            } else { 
                unset($data['password']); 
            }

            // Verificar si el campo is_root está presente y si el usuario actual es root
            if (isset($data['is_root']) && (!$actor || !$actor->is_root)) {
                unset($data['is_root']);
                \Log::warning('Intento de modificar campo is_root sin permisos');
            }

            $user->update($data);
            $user->refresh();
            
            \Log::info('Usuario actualizado exitosamente', ['user_id' => $user->id]);
            
            return response()->json([
                'status' => true,
                'mensaje' => 'Usuario actualizado por cédula.',
                'data' => $user,
            ], 200, [], JSON_UNESCAPED_UNICODE);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Error de validación', ['errors' => $e->errors()]);
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Error al actualizar usuario', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
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
        $this->authorize('update', $user);

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
        $this->authorize('update', $user);

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
        $actor = $request->user();
        
        // Verificar si el usuario tiene permiso para crear usuarios
        if ((!$actor->can_crud_user || !$actor->can_create) && !$actor->is_root) {
            return response()->json([
                'status' => false,
                'mensaje' => 'No tienes permiso para crear usuarios',
                'data' => null,
            ], 403, [], JSON_UNESCAPED_UNICODE);
        }
        
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
        $actor = request()->user();
        if ((!$actor || !$actor->is_root) && $user->is_root) {
            return response()->json([
                'status' => true,
                'mensaje' => 'usuario no encontrado',
                'data' => null,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
        $this->authorize('view', $user);
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
        $actor = $request->user();
        
        // Verificar si el usuario tiene permiso para actualizar usuarios
        if ((!$actor->can_crud_user || !$actor->can_update) && !$actor->is_root) {
            return response()->json([
                'status' => false,
                'mensaje' => 'No tienes permiso para actualizar usuarios',
                'data' => null,
            ], 403, [], JSON_UNESCAPED_UNICODE);
        }
        
        // Si el usuario objetivo es root y el actor no es root, denegar
        if ($user->is_root && !$actor->is_root) {
            return response()->json([
                'status' => false,
                'mensaje' => 'No tienes permiso para actualizar este usuario',
                'data' => null,
            ], 403, [], JSON_UNESCAPED_UNICODE);
        }
        $this->authorize('update', $user);
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
            'can_view' => ['nullable','boolean'],
            'can_create' => ['nullable','boolean'],
            'can_update' => ['nullable','boolean'],
            'can_delete' => ['nullable','boolean'],
            'is_root' => ['sometimes','boolean'],
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
        $actor = request()->user();
        
        // Verificar si el usuario tiene permiso para eliminar usuarios
        if ((!$actor->can_crud_user || !$actor->can_delete) && !$actor->is_root) {
            return response()->json([
                'status' => false,
                'mensaje' => 'No tienes permiso para eliminar usuarios',
                'data' => null,
            ], 403, [], JSON_UNESCAPED_UNICODE);
        }
        
        // Si el usuario objetivo es root y el actor no es root, denegar
        if ($user->is_root && !$actor->is_root) {
            return response()->json([
                'status' => false,
                'mensaje' => 'No tienes permiso para eliminar este usuario',
                'data' => null,
            ], 403, [], JSON_UNESCAPED_UNICODE);
        }
        $this->authorize('delete', $user);
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
