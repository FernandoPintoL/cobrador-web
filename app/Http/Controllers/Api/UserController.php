<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class UserController extends BaseController
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        $query = User::with(['roles', 'permissions']);

        // Filtro por roles
        if ($request->has('roles')) {
            $roles = explode(',', $request->roles);
            $query->whereHas('roles', function ($q) use ($roles) {
                $q->whereIn('name', $roles);
            });
        }

        // Filtro por rol específico (para compatibilidad)
        if ($request->has('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        // Búsqueda por nombre o email
        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            });
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $users = $query->paginate($perPage);

        return $this->sendResponse($users);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        // Determinar si solo se está creando un cliente
        $requestedRoles = $request->get('roles', []);
        $isOnlyClient = count($requestedRoles) === 1 && in_array('client', $requestedRoles);
        
        // Validación dinámica basada en el rol
        $validationRules = [
            'name' => 'required|string|max:255',
            'password' => 'nullable|string|min:8',
            'phone' => 'nullable|string|max:20|unique:users,phone',
            'address' => 'nullable|string',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'roles' => 'required|array|min:1',
            'roles.*' => 'string|in:admin,manager,cobrador,client',
        ];
        
        // Si solo es cliente, el email es opcional
        if ($isOnlyClient) {
            $validationRules['email'] = 'nullable|string|email|max:255|unique:users,email';
        } else {
            $validationRules['email'] = 'required|string|email|max:255|unique:users,email';
        }
        
        $request->validate($validationRules);

        // Obtener el usuario autenticado
        $currentUser = Auth::user();
        
        // Validar permisos según el rol del usuario autenticado
        $requestedRoles = $request->roles;
        
        // Solo admins pueden crear otros admins
        if (in_array('admin', $requestedRoles) && !$currentUser->hasRole('admin')) {
            return $this->sendError('No autorizado', 'Solo los administradores pueden crear usuarios con rol de admin', 403);
        }
        
        // Los cobradores solo pueden crear clientes
        if ($currentUser->hasRole('cobrador') && !in_array('client', $requestedRoles)) {
            return $this->sendError('No autorizado', 'Los cobradores solo pueden crear usuarios con rol de cliente', 403);
        }
        
        // Los managers pueden crear cobradores y clientes, pero no admins
        if ($currentUser->hasRole('manager') && in_array('admin', $requestedRoles)) {
            return $this->sendError('No autorizado', 'Los managers no pueden crear usuarios con rol de admin', 403);
        }

        $userData = [
            'name' => $request->name,
            'phone' => $request->phone,
            'address' => $request->address,
        ];
        
        // Solo agregar email si se proporciona
        if ($request->filled('email')) {
            $userData['email'] = $request->email;
        }

        // Manejar la contraseña
        if ($request->has('password') && !empty($request->password)) {
            // Si se proporciona una contraseña, usarla
            $userData['password'] = Hash::make($request->password);
        } else {
            // Si no se proporciona contraseña, generar una temporal para clientes
            if (in_array('client', $requestedRoles)) {
                // Para clientes, generar una contraseña temporal que no se usa
                $userData['password'] = Hash::make('temp_password_' . time());
            } else {
                // Para otros roles, requerir contraseña
                return $this->sendError('Contraseña requerida', 'La contraseña es requerida para roles que no sean cliente', 400);
            }
        }

        // Manejar la subida de imagen de perfil
        if ($request->hasFile('profile_image')) {
            $image = $request->file('profile_image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $imagePath = $image->storeAs('profile-images', $imageName, 'public');
            $userData['profile_image'] = $imagePath;
        }

        $user = User::create($userData);

        // Asignar roles al usuario
        $user->assignRole($requestedRoles);
        
        $user->load('roles', 'permissions');

        $message = 'Usuario creado exitosamente';
        if (in_array('client', $requestedRoles)) {
            $warnings = [];
            if (!$request->has('password') || empty($request->password)) {
                $warnings[] = 'sin contraseña';
            }
            if (!$request->filled('email')) {
                $warnings[] = 'sin email';
            }
            if (!empty($warnings)) {
                $message .= ' (cliente ' . implode(' y ', $warnings) . ' - no puede acceder al sistema)';
            }
        }

        return $this->sendResponse($user, $message);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        $user->load('roles', 'permissions');
        return $this->sendResponse($user);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user)
    {
        // Determinar si el usuario tiene solo el rol de cliente
        $userRoles = $user->roles->pluck('name')->toArray();
        $isOnlyClient = count($userRoles) === 1 && in_array('client', $userRoles);
        
        // Validación dinámica basada en el rol actual del usuario
        $validationRules = [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20|unique:users,phone,' . $user->id,
            'address' => 'nullable|string',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'roles' => 'array',
            'roles.*' => 'exists:roles,name',
        ];
        
        // Si solo es cliente, el email es opcional
        if ($isOnlyClient) {
            $validationRules['email'] = 'nullable|string|email|max:255|unique:users,email,' . $user->id;
        } else {
            $validationRules['email'] = 'required|string|email|max:255|unique:users,email,' . $user->id;
        }
        
        $request->validate($validationRules);

        $userData = [
            'name' => $request->name,
            'phone' => $request->phone,
            'address' => $request->address,
        ];
        
        // Solo agregar email si se proporciona
        if ($request->filled('email')) {
            $userData['email'] = $request->email;
        }

        // Manejar la subida de imagen de perfil
        if ($request->hasFile('profile_image')) {
            // Eliminar la imagen anterior si existe
            if ($user->profile_image) {
                Storage::disk('public')->delete($user->profile_image);
            }
            
            $image = $request->file('profile_image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $imagePath = $image->storeAs('profile-images', $imageName, 'public');
            $userData['profile_image'] = $imagePath;
        }

        $user->update($userData);

        if ($request->has('roles')) {
            $user->syncRoles($request->roles);
        }

        $user->load('roles', 'permissions');

        return $this->sendResponse($user, 'Usuario actualizado exitosamente');
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user)
    {
        $user->delete();
        return $this->sendResponse([], 'Usuario eliminado exitosamente');
    }

    /**
     * Get all roles for user assignment.
     */
    public function getRoles()
    {
        $roles = Role::all();
        return $this->sendResponse($roles);
    }

    /**
     * Get users by specific roles.
     */
    public function getUsersByRoles(Request $request)
    {
        $request->validate([
            'roles' => 'required|string|in:client,manager,cobrador,admin',
        ]);

        $role = $request->roles;
        
        $users = User::with(['roles', 'permissions'])
            ->whereHas('roles', function ($query) use ($role) {
                $query->where('name', $role);
            })
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
            })
            ->orderBy('name', 'asc')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($users, "Usuarios con rol {$role} obtenidos exitosamente");
    }

    /**
     * Get users by multiple roles.
     */
    public function getUsersByMultipleRoles(Request $request)
    {
        $request->validate([
            'roles' => 'required|string',
        ]);

        $roles = explode(',', $request->roles);
        
        // Validar que todos los roles sean válidos
        $validRoles = ['client', 'manager', 'cobrador', 'admin'];
        foreach ($roles as $role) {
            if (!in_array(trim($role), $validRoles)) {
                return $this->sendError('Rol inválido', "El rol '{$role}' no es válido. Roles válidos: " . implode(', ', $validRoles), 400);
            }
        }
        
        $users = User::with(['roles', 'permissions'])
            ->whereHas('roles', function ($query) use ($roles) {
                $query->whereIn('name', array_map('trim', $roles));
            })
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
            })
            ->orderBy('name', 'asc')
            ->paginate($request->get('per_page', 15));

        $rolesList = implode(', ', array_map('trim', $roles));
        return $this->sendResponse($users, "Usuarios con roles {$rolesList} obtenidos exitosamente");
    }

    /**
     * Update user profile image.
     */
    public function updateProfileImage(Request $request, User $user)
    {
        $request->validate([
            'profile_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Eliminar la imagen anterior si existe
        if ($user->profile_image) {
            Storage::disk('public')->delete($user->profile_image);
        }

        // Subir nueva imagen
        $image = $request->file('profile_image');
        $imageName = time() . '_' . $image->getClientOriginalName();
        $imagePath = $image->storeAs('profile-images', $imageName, 'public');
        
        $user->update(['profile_image' => $imagePath]);
        $user->load('roles', 'permissions');

        return $this->sendResponse($user, 'Imagen de perfil actualizada exitosamente');
    }

    /**
     * Get clients assigned to a specific cobrador.
     */
    public function getClientsByCobrador(Request $request, User $cobrador)
    {
        // Verificar que el usuario sea un cobrador
        if (!$cobrador->hasRole('cobrador')) {
            return $this->sendError('Usuario no válido', 'El usuario especificado no es un cobrador', 400);
        }

        $clients = $cobrador->assignedClients()
            ->with(['roles', 'permissions'])
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
            })
            ->orderBy('name', 'asc')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($clients, "Clientes asignados al cobrador {$cobrador->name} obtenidos exitosamente");
    }

    /**
     * Assign clients to a cobrador.
     */
    public function assignClientsToCobrador(Request $request, User $cobrador)
    {
        $request->validate([
            'client_ids' => 'required|array|min:1',
            'client_ids.*' => 'integer|exists:users,id',
        ]);

        // Verificar que el usuario sea un cobrador
        if (!$cobrador->hasRole('cobrador')) {
            return $this->sendError('Usuario no válido', 'El usuario especificado no es un cobrador', 400);
        }

        // Obtener los clientes
        $clients = User::whereIn('id', $request->client_ids)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'client');
            })
            ->get();

        if ($clients->isEmpty()) {
            return $this->sendError('Clientes no válidos', 'No se encontraron clientes válidos para asignar', 400);
        }

        // Asignar clientes al cobrador
        foreach ($clients as $client) {
            $client->update(['assigned_cobrador_id' => $cobrador->id]);
        }

        return $this->sendResponse($clients, "Se asignaron {$clients->count()} clientes al cobrador {$cobrador->name} exitosamente");
    }

    /**
     * Remove client assignment from cobrador.
     */
    public function removeClientFromCobrador(Request $request, User $cobrador, User $client)
    {
        // Verificar que el usuario sea un cobrador
        if (!$cobrador->hasRole('cobrador')) {
            return $this->sendError('Usuario no válido', 'El usuario especificado no es un cobrador', 400);
        }

        // Verificar que el cliente esté asignado al cobrador
        if ($client->assigned_cobrador_id !== $cobrador->id) {
            return $this->sendError('Asignación no válida', 'El cliente no está asignado a este cobrador', 400);
        }

        // Remover asignación
        $client->update(['assigned_cobrador_id' => null]);

        return $this->sendResponse($client, "Cliente {$client->name} removido del cobrador {$cobrador->name} exitosamente");
    }

    /**
     * Get cobrador assigned to a client.
     */
    public function getCobradorByClient(User $client)
    {
        // Verificar que el usuario sea un cliente
        if (!$client->hasRole('client')) {
            return $this->sendError('Usuario no válido', 'El usuario especificado no es un cliente', 400);
        }

        $cobrador = $client->assignedCobrador;

        if (!$cobrador) {
            return $this->sendResponse(null, 'El cliente no tiene un cobrador asignado');
        }

        $cobrador->load('roles', 'permissions');

        return $this->sendResponse($cobrador, "Cobrador asignado al cliente {$client->name} obtenido exitosamente");
    }
} 