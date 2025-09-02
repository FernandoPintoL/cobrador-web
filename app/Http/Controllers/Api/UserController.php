<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\UserPhoto;
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

        // Búsqueda mejorada por múltiples campos
        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $search = $request->search;
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('ci', 'like', "%{$search}%")
                    ->orWhere('client_category', 'like', "%{$search}%");
            });
        }

        // Filtros específicos por campo individual
        if ($request->has('name')) {
            $query->where('name', 'like', "%{$request->name}%");
        }

        if ($request->has('email')) {
            $query->where('email', 'like', "%{$request->email}%");
        }

        if ($request->has('phone')) {
            $query->where('phone', 'like', "%{$request->phone}%");
        }

        if ($request->has('ci')) {
            $query->where('ci', 'like', "%{$request->ci}%");
        }

        if ($request->has('client_category')) {
            $query->where('client_category', $request->client_category);
        }

        if ($request->has('user_id')) {
            $query->where('id', $request->user_id);
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
        // return $request->all(); Debugging line to check request data
        // Determinar si solo se está creando un cliente
        $requestedRoles = $request->get('roles', []);
        $isOnlyClient = count($requestedRoles) === 1 && in_array('client', $requestedRoles);

        // Validación dinámica basada en el rol
        $validationRules = [
            'name' => 'required|string|max:255',
            'ci' => 'required|string|max:20|unique:users,ci', // Validación para el CI
            'password' => 'nullable|string|min:8',
            'address' => 'nullable|string',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'roles' => 'required|array|min:1',
            'roles.*' => 'string|in:admin,manager,cobrador,client',
            'client_category' => 'nullable|string|exists:client_categories,code', // Validación usando tabla client_categories
            'location' => 'nullable|array',
            'location.type' => 'nullable|string|in:Point',
            'location.coordinates' => 'nullable|array|size:2',
            'location.coordinates.0' => 'nullable|numeric|between:-180,180', // longitude
            'location.coordinates.1' => 'nullable|numeric|between:-90,90',   // latitude
        ];

        // Si es cliente, la categoría es requerida, por defecto será 'B' (Cliente Normal)
        if ($isOnlyClient) {
            $validationRules['client_category'] = 'nullable|string|exists:client_categories,code';
        }

        // Determinar si está creando un cobrador
        $isCreatingCobrador = in_array('cobrador', $requestedRoles);

        // Si solo es cliente, el email y phone son opcionales
        if ($isOnlyClient) {
            $validationRules['email'] = 'nullable|string|email|max:255|unique:users,email';
            $validationRules['phone'] = 'nullable|string|max:20|unique:users,phone';
        } elseif ($isCreatingCobrador) {
            // Para cobradores: email o phone debe ser proporcionado (para login)
            $validationRules['email'] = 'nullable|string|email|max:255|unique:users,email';
            $validationRules['phone'] = 'nullable|string|max:20|unique:users,phone';
        } else {
            // Para otros roles (admin, manager), email es requerido
            $validationRules['email'] = 'required|string|email|max:255|unique:users,email';
            $validationRules['phone'] = 'nullable|string|max:20|unique:users,phone';
        }

        $request->validate($validationRules);

        // Validación adicional para cobradores: al menos email o phone debe estar presente
        if ($isCreatingCobrador) {
            if (! $request->filled('email') && ! $request->filled('phone')) {
                return $this->sendError('Campos requeridos', 'Para cobradores, se requiere al menos email o teléfono para el inicio de sesión.', 400);
            }
        }

        // Obtener el usuario autenticado
        $currentUser = Auth::user();

        // Validar permisos según el rol del usuario autenticado
        $requestedRoles = $request->roles;

        // Solo admins pueden crear otros admins
        if (in_array('admin', $requestedRoles) && ! $currentUser->hasRole('admin')) {
            return $this->sendError('No autorizado', 'Solo los administradores pueden crear usuarios con rol de admin', 403);
        }

        // Los cobradores solo pueden crear clientes
        if ($currentUser->hasRole('cobrador') && ! in_array('client', $requestedRoles)) {
            return $this->sendError('No autorizado', 'Los cobradores solo pueden crear usuarios con rol de cliente', 403);
        }

        // Los managers pueden crear cobradores y clientes, pero no admins
        if ($currentUser->hasRole('manager') && in_array('admin', $requestedRoles)) {
            return $this->sendError('No autorizado', 'Los managers no pueden crear usuarios con rol de admin', 403);
        }

        $userData = [
            'name' => $request->name,
            'ci' => $request->ci, // ¡AQUÍ ESTABA EL PROBLEMA! Agregamos el CI
            'address' => $request->address,
        ];

        // Agregar categoría de cliente - si es cliente y no se especifica, usar 'B' por defecto
        if ($isOnlyClient) {
            $userData['client_category'] = $request->get('client_category', 'A'); // Cliente VIP por defecto
        }

        // Procesar ubicación GeoJSON si se proporciona
        if ($request->has('location') && $request->location) {
            $location = $request->location;
            if (isset($location['type']) && $location['type'] === 'Point' &&
                isset($location['coordinates']) && is_array($location['coordinates']) &&
                count($location['coordinates']) === 2) {

                $userData['longitude'] = $location['coordinates'][0]; // longitude es el primer elemento
                $userData['latitude'] = $location['coordinates'][1];  // latitude es el segundo elemento
            }
        }

        if ($currentUser->hasRole('manager') && ($isCreatingCobrador || $isOnlyClient)) {
            $userData['assigned_manager_id'] = $currentUser->id; // Asignar el ID del manager
        }

        if ($currentUser->hasRole('cobrador') && $isOnlyClient) {
            $userData['assigned_cobrador_id'] = $currentUser->id; // Asignar el ID del cobrador
        }

        // Solo agregar email si se proporciona
        if ($request->filled('email')) {
            $userData['email'] = $request->email;
        }

        // Agregar teléfono si se proporciona
        if ($request->filled('phone')) {
            $userData['phone'] = $request->phone;
        }

        // Manejar la contraseña
        if ($request->has('password') && ! empty($request->password)) {
            // Si se proporciona una contraseña, usarla
            $userData['password'] = Hash::make($request->password);
        } else {
            // Si no se proporciona contraseña, generar una temporal para clientes
            if (in_array('client', $requestedRoles)) {
                // Para clientes, generar una contraseña temporal que no se usa
                $userData['password'] = Hash::make('temp_password_'.time());
            } else {
                // Para otros roles, requerir contraseña
                return $this->sendError('Contraseña requerida', 'La contraseña es requerida para roles que no sean cliente', 400);
            }
        }

        $user = User::create($userData);

        // Manejar la subida de imagen de perfil después de crear el usuario
        if ($request->hasFile('profile_image')) {
            $image = $request->file('profile_image');
            // Usar el nuevo sistema de nombres basado en CI + timestamp
            $imageName = $this->generateImageFileName($user, $image->getClientOriginalName());
            $imagePath = $image->storeAs('profile-images', $imageName, 'public');
            $user->update(['profile_image' => $imagePath]);
        }

        // Asignar roles al usuario
        $user->assignRole($requestedRoles);

        $user->load('roles', 'permissions');

        $message = 'Usuario creado exitosamente';
        if (in_array('client', $requestedRoles)) {
            $warnings = [];
            if (! $request->has('password') || empty($request->password)) {
                $warnings[] = 'sin contraseña';
            }
            if (! $request->filled('email')) {
                $warnings[] = 'sin email';
            }
            if (! empty($warnings)) {
                $message .= ' (cliente '.implode(' y ', $warnings).' - no puede acceder al sistema)';
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
            'ci' => 'required|string|max:20|unique:users,ci,'.$user->id, // Validación para el CI en actualización
            'phone' => 'nullable|string|max:20|unique:users,phone,'.$user->id,
            'address' => 'nullable|string',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'roles' => 'array',
            'roles.*' => 'exists:roles,name',
            'client_category' => 'nullable|string|exists:client_categories,code', // Validación usando tabla client_categories
            'location' => 'nullable|array',
            'location.type' => 'nullable|string|in:Point',
            'location.coordinates' => 'nullable|array|size:2',
            'location.coordinates.0' => 'nullable|numeric|between:-180,180', // longitude
            'location.coordinates.1' => 'nullable|numeric|between:-90,90',   // latitude
        ];

        // Si solo es cliente, el email es opcional
        if ($isOnlyClient) {
            $validationRules['email'] = 'nullable|string|email|max:255|unique:users,email,'.$user->id;
        } else {
            $validationRules['email'] = 'required|string|email|max:255|unique:users,email,'.$user->id;
        }

        $request->validate($validationRules);

        $userData = [
            'name' => $request->name,
            'phone' => $request->phone,
            'address' => $request->address,
        ];

        // Agregar categoría de cliente si se proporciona y es un cliente
        if ($request->filled('client_category') && $isOnlyClient) {
            $userData['client_category'] = $request->client_category;
        }

        // Solo agregar email si se proporciona
        if ($request->filled('email')) {
            $userData['email'] = $request->email;
        }

        // Procesar ubicación GeoJSON si se proporciona
        if ($request->has('location') && $request->location) {
            $location = $request->location;
            if (isset($location['type']) && $location['type'] === 'Point' &&
                isset($location['coordinates']) && is_array($location['coordinates']) &&
                count($location['coordinates']) === 2) {

                $userData['longitude'] = $location['coordinates'][0]; // longitude es el primer elemento
                $userData['latitude'] = $location['coordinates'][1];  // latitude es el segundo elemento
            }
        }

        // Manejar la subida de imagen de perfil
        if ($request->hasFile('profile_image')) {
            // Eliminar la imagen anterior si existe
            if ($user->profile_image) {
                Storage::disk('public')->delete($user->profile_image);
            }

            $image = $request->file('profile_image');
            // Usar el nuevo sistema de nombres basado en CI + timestamp
            $imageName = $this->generateImageFileName($user, $image->getClientOriginalName());
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
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('ci', 'like', "%{$search}%")
                        ->orWhere('client_category', 'like', "%{$search}%");
                });
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
            if (! in_array(trim($role), $validRoles)) {
                return $this->sendError('Rol inválido', "El rol '{$role}' no es válido. Roles válidos: ".implode(', ', $validRoles), 400);
            }
        }

        $users = User::with(['roles', 'permissions'])
            ->whereHas('roles', function ($query) use ($roles) {
                $query->whereIn('name', array_map('trim', $roles));
            })
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('ci', 'like', "%{$search}%")
                        ->orWhere('client_category', 'like', "%{$search}%");
                });
            })
            ->orderBy('name', 'asc')
            ->paginate($request->get('per_page', 15));

        $rolesList = implode(', ', array_map('trim', $roles));

        return $this->sendResponse($users, "Usuarios con roles {$rolesList} obtenidos exitosamente");
    }

    /**
     * Generate a consistent filename based on user CI and timestamp
     */
    private function generateImageFileName(User $user, $originalFileName, ?string $type = null): string
    {
        // Obtener la extensión del archivo original
        $extension = pathinfo($originalFileName, PATHINFO_EXTENSION);

        // Limpiar el CI para usar como nombre de archivo (remover caracteres especiales)
        $cleanCI = preg_replace('/[^a-zA-Z0-9]/', '', $user->ci);

        // Generar timestamp
        $timestamp = time();

        // Si se especifica un tipo (para documentos), agregarlo al nombre
        $typePrefix = $type ? "_{$type}" : '';

        // Formato: CI_timestamp[_tipo].extension
        return "{$cleanCI}_{$timestamp}{$typePrefix}.{$extension}";
    }

    /**
     * Update user profile image.
     */
    public function updateProfileImage(Request $request, User $user)
    {
        $request->validate([
            'profile_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Verificar que el usuario tenga CI
        if (empty($user->ci)) {
            return $this->sendError('CI requerido', 'El usuario debe tener un CI válido para subir imágenes', 422);
        }

        // Eliminar la imagen anterior si existe
        if ($user->profile_image) {
            Storage::disk('public')->delete($user->profile_image);
        }

        // Subir nueva imagen con nombre basado en CI + timestamp
        $image = $request->file('profile_image');
        $imageName = $this->generateImageFileName($user, $image->getClientOriginalName());
        $imagePath = $image->storeAs('profile-images', $imageName, 'public');

        $user->update(['profile_image' => $imagePath]);
        $user->load('roles', 'permissions');

        return $this->sendResponse($user, 'Imagen de perfil actualizada exitosamente');
    }

    /**
     * Authorization helper: who can manage a user's photos.
     */
    private function canManageUserMedia(User $current, User $target): bool
    {
        // Log de debug temporal
        \Log::info('🔍 canManageUserMedia DEBUG', [
            'current_user_id' => $current->id,
            'current_user_name' => $current->name,
            'current_user_roles' => $current->roles->pluck('name')->toArray(),
            'target_user_id' => $target->id,
            'target_user_name' => $target->name,
            'target_user_roles' => $target->roles->pluck('name')->toArray(),
            'target_assigned_cobrador_id' => $target->assigned_cobrador_id,
        ]);

        // TEMPORAL: Permitir todo para debug (REMOVER EN PRODUCCIÓN)
        \Log::info('⚠️ MODO DEBUG: PERMITIENDO TODAS LAS SUBIDAS DE FOTOS');

        return true;

        if ($current->id === $target->id) {
            \Log::info('✅ Autorizado: mismo usuario');

            return true; // self
        }

        if ($current->hasRole('admin') || $current->hasRole('manager')) {
            \Log::info('✅ Autorizado: admin o manager');

            return true;
        }

        // Permitir que un cobrador suba fotos de sus clientes asignados
        $isCurrentCobrador = $current->hasRole('cobrador');
        $isTargetClient = $target->hasRole('client');
        $isAssigned = $target->assigned_cobrador_id === $current->id;

        \Log::info('🔍 Verificando cobrador-cliente:', [
            'is_current_cobrador' => $isCurrentCobrador,
            'is_target_client' => $isTargetClient,
            'is_assigned' => $isAssigned,
            'target_assigned_cobrador_id' => $target->assigned_cobrador_id,
            'current_user_id' => $current->id,
        ]);

        if ($isCurrentCobrador && $isTargetClient && $isAssigned) {
            \Log::info('✅ Autorizado: cobrador de cliente asignado');

            return true;
        }

        \Log::info('❌ No autorizado');

        return false;
    }

    /**
     * List photos for a user.
     */
    public function getPhotos(User $user)
    {
        $current = Auth::user();
        if (! $this->canManageUserMedia($current, $user)) {
            return $this->sendError('No autorizado', 'No tienes permisos para ver las fotos de este usuario', 403);
        }

        $photos = $user->photos()->orderByDesc('id')->get()->map(function ($p) {
            return [
                'id' => $p->id,
                'type' => $p->type,
                'path_url' => $p->path_url,
                'url' => asset('storage/'.$p->path_url),
                'uploaded_by' => $p->uploaded_by,
                'created_at' => $p->created_at,
                'notes' => $p->notes,
            ];
        });

        return $this->sendResponse($photos);
    }

    /**
     * Upload one or many photos for a user.
     * Accepts either:
     *  - photo (single file) + type (string)
     *  - photos[] (multiple files) + types[] (optional array aligned) + notes (optional)
     *
     * Si ya existe una foto del mismo tipo, la reemplaza (elimina la anterior).
     */
    public function uploadPhotos(Request $request, User $user)
    {
        $current = Auth::user();

        // Debug adicional: log del usuario autenticado en la solicitud
        \Log::info('🔍 UPLOAD PHOTOS REQUEST DEBUG', [
            'authenticated_user_id' => $current ? $current->id : 'NULL',
            'authenticated_user_name' => $current ? $current->name : 'NULL',
            'authenticated_user_roles' => $current ? $current->roles->pluck('name')->toArray() : 'NULL',
            'target_user_id' => $user->id,
            'target_user_name' => $user->name,
            'request_headers' => $request->headers->all(),
        ]);

        if (! $this->canManageUserMedia($current, $user)) {
            return $this->sendError('No autorizado', 'No tienes permisos para subir fotos para este usuario', 403);
        }

        // Verificar que el usuario tenga CI
        if (empty($user->ci)) {
            return $this->sendError('CI requerido', 'El usuario debe tener un CI válido para subir imágenes', 422);
        }

        $allowedTypes = ['id_front', 'id_back', 'other'];

        // Validación flexible (acepta tanto array como valor único en photos/types)
        $rules = [
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'type' => 'nullable|string|in:'.implode(',', $allowedTypes),
            'photos' => 'nullable', // puede ser array o archivo único
            'photos.*' => 'image|mimes:jpeg,png,jpg,gif|max:5120',
            'types' => 'nullable', // puede ser array o string único
            'types.*' => 'nullable|string|in:'.implode(',', $allowedTypes),
            'notes' => 'nullable|string|max:255',
        ];
        $validated = $request->validate($rules);

        $saved = [];
        $updated = [];
        $disk = 'public';
        $baseDir = 'user-photos/'.$user->ci; // Usar CI en lugar de ID para la carpeta

        // Helper function para procesar un archivo individual
        $processFile = function ($file, $type, $notes) use ($user, $current, $baseDir, $disk, &$saved, &$updated) {
            if (! in_array($type, ['id_front', 'id_back', 'other'])) {
                $type = 'other';
            }

            // Buscar si ya existe una foto del mismo tipo para este usuario
            $existingPhoto = UserPhoto::where('user_id', $user->id)
                ->where('type', $type)
                ->first();

            // Generar nombre de archivo basado en CI + timestamp + tipo
            $fileName = $this->generateImageFileName($user, $file->getClientOriginalName(), $type);
            $path = $file->storeAs($baseDir, $fileName, $disk);

            if ($existingPhoto) {
                // ACTUALIZAR: Eliminar archivo anterior del storage
                if ($existingPhoto->path_url && Storage::disk($disk)->exists($existingPhoto->path_url)) {
                    Storage::disk($disk)->delete($existingPhoto->path_url);
                }

                // Actualizar registro existente
                $existingPhoto->update([
                    'path_url' => $path,
                    'uploaded_by' => $current->id,
                    'notes' => $notes,
                    'updated_at' => now(),
                ]);

                $updated[] = [
                    'id' => $existingPhoto->id,
                    'type' => $existingPhoto->type,
                    'path_url' => $existingPhoto->path_url,
                    'url' => asset('storage/'.$existingPhoto->path_url),
                    'action' => 'updated',
                ];
            } else {
                // CREAR: Nuevo registro
                $record = UserPhoto::create([
                    'user_id' => $user->id,
                    'type' => $type,
                    'path_url' => $path,
                    'uploaded_by' => $current->id,
                    'notes' => $notes,
                ]);

                $saved[] = [
                    'id' => $record->id,
                    'type' => $record->type,
                    'path_url' => $record->path_url,
                    'url' => asset('storage/'.$record->path_url),
                    'action' => 'created',
                ];
            }
        };

        // Caso múltiple
        if ($request->hasFile('photos')) {
            $files = $request->file('photos');
            // Normalizar: si llega un solo archivo como 'photos', convertir a arreglo
            if (! is_array($files)) {
                $files = [$files];
            }
            $types = $request->input('types', []);
            // Normalizar: si llega un solo tipo como string, convertir a arreglo
            if (! is_array($types)) {
                $types = [$types];
            }

            foreach ($files as $idx => $file) {
                $type = $types[$idx] ?? 'other';
                $processFile($file, $type, $request->input('notes'));
            }
        }

        // Caso simple
        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $type = $request->input('type', 'other');
            $processFile($file, $type, $request->input('notes'));
        }

        if (empty($saved) && empty($updated)) {
            return $this->sendError('Datos inválidos', 'Debes enviar photo o photos[]', 422);
        }

        $allResults = array_merge($saved, $updated);
        $totalCreated = count($saved);
        $totalUpdated = count($updated);

        $message = '';
        if ($totalCreated > 0 && $totalUpdated > 0) {
            $message = "Se crearon {$totalCreated} fotos nuevas y se actualizaron {$totalUpdated} fotos existentes";
        } elseif ($totalCreated > 0) {
            $message = "Se crearon {$totalCreated} fotos correctamente";
        } elseif ($totalUpdated > 0) {
            $message = "Se actualizaron {$totalUpdated} fotos existentes correctamente";
        }

        $response = [
            'photos' => $allResults,
            'summary' => [
                'total_processed' => count($allResults),
                'created' => $totalCreated,
                'updated' => $totalUpdated,
            ],
        ];

        return $this->sendResponse($response, $message);
    }

    /**
     * Delete a specific photo of a user.
     */
    public function deletePhoto(User $user, UserPhoto $photo)
    {
        $current = Auth::user();
        if (! $this->canManageUserMedia($current, $user)) {
            return $this->sendError('No autorizado', 'No tienes permisos para eliminar fotos de este usuario', 403);
        }

        if ($photo->user_id !== $user->id) {
            return $this->sendError('Relación inválida', 'La foto no pertenece al usuario especificado', 400);
        }

        // Eliminar archivo físico
        if ($photo->path_url) {
            Storage::disk('public')->delete($photo->path_url);
        }
        $photo->delete();

        return $this->sendResponse(null, 'Foto eliminada correctamente');
    }

    /**
     * Get clients assigned to a specific cobrador.
     */
    public function getClientsByCobrador(Request $request, User $cobrador)
    {
        // Verificar que el usuario sea un cobrador
        if (! $cobrador->hasRole('cobrador')) {
            return $this->sendError('Usuario no válido', 'El usuario especificado no es un cobrador', 400);
        }

        $clients = $cobrador->assignedClients()
            ->with(['roles', 'permissions'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('ci', 'like', "%{$search}%")
                        ->orWhere('client_category', 'like', "%{$search}%");
                });
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
        if (! $cobrador->hasRole('cobrador')) {
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
        if (! $cobrador->hasRole('cobrador')) {
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
        if (! $client->hasRole('client')) {
            return $this->sendError('Usuario no válido', 'El usuario especificado no es un cliente', 400);
        }

        $cobrador = $client->assignedCobrador;

        if (! $cobrador) {
            return $this->sendResponse(null, 'El cliente no tiene un cobrador asignado');
        }

        $cobrador->load('roles', 'permissions');

        return $this->sendResponse($cobrador, "Cobrador asignado al cliente {$client->name} obtenido exitosamente");
    }

    /**
     * Get cobradores assigned to a specific manager.
     */
    public function getCobradoresByManager(Request $request, User $manager)
    {
        // Verificar que el usuario sea un manager
        if (! $manager->hasRole('manager')) {
            return $this->sendError('Usuario no válido', 'El usuario especificado no es un manager', 400);
        }

        // Obtener solo usuarios con rol 'cobrador' asignados a este manager
        $cobradores = User::whereHas('roles', function ($query) {
            $query->where('name', 'cobrador');
        })
            ->where('assigned_manager_id', $manager->id)
            ->with(['roles', 'permissions'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('ci', 'like', "%{$search}%")
                        ->orWhere('client_category', 'like', "%{$search}%");
                });
            })
            ->orderBy('name', 'asc')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($cobradores, "Cobradores asignados al manager {$manager->name} obtenidos exitosamente");
    }

    /**
     * Assign cobradores to a manager.
     */
    public function assignCobradoresToManager(Request $request, User $manager)
    {
        $request->validate([
            'cobrador_ids' => 'required|array|min:1',
            'cobrador_ids.*' => 'integer|exists:users,id',
        ]);

        // Verificar que el usuario sea un manager
        if (! $manager->hasRole('manager')) {
            return $this->sendError('Usuario no válido', 'El usuario especificado no es un manager', 400);
        }

        // Obtener los cobradores
        $cobradores = User::whereIn('id', $request->cobrador_ids)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'cobrador');
            })
            ->get();

        if ($cobradores->isEmpty()) {
            return $this->sendError('Cobradores no válidos', 'No se encontraron cobradores válidos para asignar', 400);
        }

        // Asignar cobradores al manager
        foreach ($cobradores as $cobrador) {
            $cobrador->update(['assigned_manager_id' => $manager->id]);
        }

        $cobradores->load('roles', 'permissions');

        return $this->sendResponse($cobradores, "Se asignaron {$cobradores->count()} cobradores al manager {$manager->name} exitosamente");
    }

    /**
     * Remove cobrador assignment from manager.
     */
    public function removeCobradorFromManager(Request $request, User $manager, User $cobrador)
    {
        // Verificar que el usuario sea un manager
        if (! $manager->hasRole('manager')) {
            return $this->sendError('Usuario no válido', 'El usuario especificado no es un manager', 400);
        }

        // Verificar que el cobrador esté asignado al manager
        if ($cobrador->assigned_manager_id !== $manager->id) {
            return $this->sendError('Asignación no válida', 'El cobrador no está asignado a este manager', 400);
        }

        // Remover asignación
        $cobrador->update(['assigned_manager_id' => null]);

        $cobrador->load('roles', 'permissions');

        return $this->sendResponse($cobrador, "Cobrador {$cobrador->name} removido del manager {$manager->name} exitosamente");
    }

    /**
     * Get manager assigned to a cobrador.
     */
    public function getManagerByCobrador(User $cobrador)
    {
        // Verificar que el usuario sea un cobrador
        if (! $cobrador->hasRole('cobrador')) {
            return $this->sendError('Usuario no válido', 'El usuario especificado no es un cobrador', 400);
        }

        $manager = $cobrador->assignedManager;

        if (! $manager) {
            return $this->sendResponse(null, 'El cobrador no tiene un manager asignado');
        }

        $manager->load('roles', 'permissions');

        return $this->sendResponse($manager, "Manager asignado al cobrador {$cobrador->name} obtenido exitosamente");
    }

    /**
     * Get all clients assigned to a specific manager (directly + through cobradores).
     */
    public function getAllClientsByManager(Request $request, User $manager)
    {
        // Verificar que el usuario sea un manager
        if (! $manager->hasRole('manager')) {
            return $this->sendError('Usuario no válido', 'El usuario especificado no es un manager', 400);
        }

        // Obtener todos los clientes del manager:
        // 1. Clientes asignados directamente al manager
        // 2. Clientes asignados a cobradores que están asignados al manager
        $directClients = $manager->assignedClientsDirectly();

        // Obtener IDs de cobradores asignados al manager (filtrar por rol cobrador)
        $cobradorIds = User::whereHas('roles', function ($query) {
            $query->where('name', 'cobrador');
        })
            ->where('assigned_manager_id', $manager->id)
            ->pluck('id');

        // Obtener clientes asignados a esos cobradores
        $cobradorClientsQuery = User::whereHas('roles', function ($query) {
            $query->where('name', 'client');
        })
            ->whereIn('assigned_cobrador_id', $cobradorIds);

        // Combinar ambas consultas usando UNION
        $allClients = User::whereHas('roles', function ($query) {
            $query->where('name', 'client');
        })
            // Excluir usuarios que tengan rol de manager para evitar conflictos
            ->whereDoesntHave('roles', function ($query) {
                $query->where('name', 'manager');
            })
            ->where(function ($query) use ($manager, $cobradorIds) {
                $query->where('assigned_manager_id', $manager->id) // Clientes directos del manager
                    ->orWhereIn('assigned_cobrador_id', $cobradorIds); // Clientes de cobradores del manager
            })
            ->with(['roles', 'permissions', 'assignedCobrador', 'assignedManagerDirectly'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('ci', 'like', "%{$search}%")
                        ->orWhere('client_category', 'like', "%{$search}%");
                });
            })
            ->orderBy('name', 'asc')
            ->paginate($request->get('per_page', 15));

        // Agregar información adicional sobre la asignación
        $allClients->getCollection()->transform(function ($client) {
            $client->assignment_type = $client->assigned_manager_id ? 'direct' : 'through_cobrador';
            $client->cobrador_name = $client->assignedCobrador ? $client->assignedCobrador->name : null;

            return $client;
        });

        return $this->sendResponse($allClients, "Todos los clientes del manager {$manager->name} obtenidos exitosamente");
    }

    /**
     * Get clients assigned directly to a specific manager.
     */
    public function getClientsByManager(Request $request, User $manager)
    {
        // Verificar que el usuario sea un manager
        if (! $manager->hasRole('manager')) {
            return $this->sendError('Usuario no válido', 'El usuario especificado no es un manager', 400);
        }

        $clients = $manager->assignedClientsDirectly()
            ->with(['roles', 'permissions'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('ci', 'like', "%{$search}%")
                        ->orWhere('client_category', 'like', "%{$search}%");
                });
            })
            ->orderBy('name', 'asc')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($clients, "Clientes asignados directamente al manager {$manager->name} obtenidos exitosamente");
    }

    /**
     * Assign clients to manager directly.
     */
    public function assignClientsToManager(Request $request, User $manager)
    {
        $request->validate([
            'client_ids' => 'required|array|min:1',
            'client_ids.*' => 'integer|exists:users,id',
        ]);

        // Verificar que el usuario sea un manager
        if (! $manager->hasRole('manager')) {
            return $this->sendError('Usuario no válido', 'El usuario especificado no es un manager', 400);
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

        // Asignar clientes al manager
        foreach ($clients as $client) {
            $client->update(['assigned_manager_id' => $manager->id]);
        }

        $clients->load('roles', 'permissions');

        return $this->sendResponse($clients, "Se asignaron {$clients->count()} clientes directamente al manager {$manager->name} exitosamente");
    }

    /**
     * Remove client assignment from manager.
     */
    public function removeClientFromManager(Request $request, User $manager, User $client)
    {
        // Verificar que el usuario sea un manager
        if (! $manager->hasRole('manager')) {
            return $this->sendError('Usuario no válido', 'El usuario especificado no es un manager', 400);
        }

        // Verificar que el cliente esté asignado al manager
        if ($client->assigned_manager_id !== $manager->id) {
            return $this->sendError('Asignación no válida', 'El cliente no está asignado directamente a este manager', 400);
        }

        // Remover asignación
        $client->update(['assigned_manager_id' => null]);

        $client->load('roles', 'permissions');

        return $this->sendResponse($client, "Cliente {$client->name} removido del manager {$manager->name} exitosamente");
    }

    /**
     * Get manager assigned directly to a client.
     */
    public function getManagerByClient(User $client)
    {
        // Verificar que el usuario sea un cliente
        if (! $client->hasRole('client')) {
            return $this->sendError('Usuario no válido', 'El usuario especificado no es un cliente', 400);
        }

        $manager = $client->assignedManagerDirectly;

        if (! $manager) {
            return $this->sendResponse(null, 'El cliente no tiene un manager asignado directamente');
        }

        $manager->load('roles', 'permissions');

        return $this->sendResponse($manager, "Manager asignado directamente al cliente {$client->name} obtenido exitosamente");
    }

    /**
     * Get available client categories.
     */
    public function getClientCategories()
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('client_categories')) {
                $categories = \App\Models\ClientCategory::query()
                    ->active()
                    ->orderBy('code')
                    ->get(['code', 'name', 'description']);

                if ($categories->count() > 0) {
                    return $this->sendResponse($categories, 'Categorías de clientes obtenidas exitosamente');
                }
            }
        } catch (\Throwable $e) {
            // Fallback to constants
        }
        $categories = collect(User::getClientCategories())
            ->map(fn ($name, $code) => ['code' => $code, 'name' => $name, 'description' => null])
            ->values();

        return $this->sendResponse($categories, 'Categorías de clientes obtenidas exitosamente');
    }

    /**
     * Update client category.
     */
    public function updateClientCategory(Request $request, User $client)
    {
        // Verificar que el usuario sea un cliente
        if (! $client->hasRole('client')) {
            return $this->sendError('Usuario no válido', 'El usuario especificado no es un cliente', 400);
        }

        $request->validate([
            'client_category' => 'required|string|exists:client_categories,code',
        ]);

        $client->update(['client_category' => $request->client_category]);
        $client->load('roles', 'permissions', 'clientCategory');

        $categoryName = $client->client_category_name ?? ($client->clientCategory->name ?? $request->client_category);

        return $this->sendResponse($client, "Categoría del cliente actualizada a: {$categoryName}");
    }

    /**
     * Get clients by category.
     */
    public function getClientsByCategory(Request $request)
    {
        $request->validate([
            'category' => 'required|string|exists:client_categories,code',
        ]);

        $category = $request->category;
        $categoryName = null;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('client_categories')) {
                $row = \App\Models\ClientCategory::query()->where('code', $category)->first();
                $categoryName = $row?->name;
            }
        } catch (\Throwable $e) {
        }
        if (! $categoryName) {
            $categoryName = User::getClientCategories()[$category] ?? $category;
        }

        $clients = User::whereHas('roles', function ($query) {
            $query->where('name', 'client');
        })
            ->where('client_category', $category)
            ->with(['roles', 'permissions', 'assignedCobrador', 'assignedManagerDirectly'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('ci', 'like', "%{$search}%");
                });
            })
            ->orderBy('name', 'asc')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($clients, "Clientes con categoría {$categoryName} obtenidos exitosamente");
    }

    /**
     * Get client statistics by category.
     */
    public function getClientCategoryStatistics()
    {
        $statistics = [];

        $codesWithNames = [];
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('client_categories')) {
                $codesWithNames = \App\Models\ClientCategory::query()->active()->orderBy('code')->get(['code', 'name'])
                    ->map(fn ($row) => [$row->code => $row->name])->collapse()->toArray();
            }
        } catch (\Throwable $e) {
        }
        if (empty($codesWithNames)) {
            $codesWithNames = User::getClientCategories();
        }

        foreach ($codesWithNames as $code => $name) {
            $count = User::whereHas('roles', function ($query) {
                $query->where('name', 'client');
            })
                ->where('client_category', $code)
                ->count();

            $statistics[] = [
                'category_code' => $code,
                'category_name' => $name,
                'client_count' => $count,
            ];
        }

        // Agregar clientes sin categoría
        $uncategorizedCount = User::whereHas('roles', function ($query) {
            $query->where('name', 'client');
        })
            ->whereNull('client_category')
            ->count();

        $statistics[] = [
            'category_code' => null,
            'category_name' => 'Sin categoría',
            'client_count' => $uncategorizedCount,
        ];

        return $this->sendResponse($statistics, 'Estadísticas de categorías de clientes obtenidas exitosamente');
    }

    /**
     * Bulk update client categories.
     */
    public function bulkUpdateClientCategories(Request $request)
    {
        $request->validate([
            'updates' => 'required|array|min:1',
            'updates.*.client_id' => 'required|integer|exists:users,id',
            'updates.*.category' => 'required|string|in:A,B,C',
        ]);

        $updatedClients = [];
        $errors = [];

        foreach ($request->updates as $update) {
            $client = User::find($update['client_id']);

            // Verificar que sea un cliente
            if (! $client->hasRole('client')) {
                $errors[] = "Usuario ID {$update['client_id']} no es un cliente";

                continue;
            }

            $client->update(['client_category' => $update['category']]);
            $client->load('roles', 'permissions');
            $updatedClients[] = $client;
        }

        $response = [
            'updated_clients' => $updatedClients,
            'updated_count' => count($updatedClients),
            'errors' => $errors,
        ];

        $message = count($updatedClients) > 0
            ? 'Se actualizaron '.count($updatedClients).' categorías de clientes exitosamente'
            : 'No se pudieron actualizar las categorías';

        if (! empty($errors)) {
            $message .= '. Se encontraron '.count($errors).' errores';
        }

        return $this->sendResponse($response, $message);
    }

    /**
     * Change password for users with appropriate authorization.
     * - Admins can change passwords for managers and cobradores
     * - Managers can change passwords for cobradores assigned to them
     */
    public function changePassword(Request $request, User $user)
    {
        $currentUser = Auth::user();

        // Validar los datos de entrada
        $request->validate([
            'new_password' => 'required|string|min:8|confirmed',
            'new_password_confirmation' => 'required|string|min:8',
        ]);

        // Verificar autorización
        if (! $this->canChangeUserPassword($currentUser, $user)) {
            return $this->sendError('No autorizado', 'No tienes permisos para cambiar la contraseña de este usuario', 403);
        }

        // Cambiar la contraseña
        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        // Log de la acción para auditoría
        \Log::info("Password changed for user {$user->id} by user {$currentUser->id}", [
            'target_user' => [
                'id' => $user->id,
                'name' => $user->name,
                'roles' => $user->roles->pluck('name')->toArray(),
            ],
            'changed_by' => [
                'id' => $currentUser->id,
                'name' => $currentUser->name,
                'roles' => $currentUser->roles->pluck('name')->toArray(),
            ],
        ]);

        return $this->sendResponse(
            null,
            "Contraseña cambiada exitosamente para {$user->name}"
        );
    }

    /**
     * Check if current user can change target user's password.
     */
    private function canChangeUserPassword(User $currentUser, User $targetUser): bool
    {
        // Los usuarios no pueden cambiar su propia contraseña por este endpoint
        if ($currentUser->id === $targetUser->id) {
            return false;
        }

        // Admins pueden cambiar contraseñas de managers y cobradores
        if ($currentUser->hasRole('admin')) {
            return $targetUser->hasRole('manager') || $targetUser->hasRole('cobrador');
        }

        // Managers pueden cambiar contraseñas solo de cobradores asignados a ellos
        if ($currentUser->hasRole('manager') && $targetUser->hasRole('cobrador')) {
            // Verificar que el cobrador esté asignado al manager
            return $targetUser->assigned_manager_id === $currentUser->id;
        }

        return false;
    }
}
