<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Mail\UserCreated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search', '');

        $query = User::where('is_deleted', false);

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', '%'. $search . '%')
                  ->orWhere('last_name', 'like', '%'. $search . '%')
                  ->orWhere('email', 'like', '%'. $search . '%')
                  ->orWhere('contact_number', 'like', '%'. $search . '%');
            });
        }

        $sort = $request->get('sort', 'id');
        $direction = $request->get('direction', 'asc');

        $users = $query->orderBy($sort, $direction)->paginate($perPage);

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'contact_number' => 'nullable|string|max:20',
        ]);

        // Generate random 8-character password
        $password = Str::random(8);
        
        // Hash password and set role to customer
        $validated['password'] = Hash::make($password);
        $validated['role'] = 'customer';

        $user = User::create($validated);

        // Send email with credentials - QUEUED to prevent timeout
        try {
            Mail::to($user->email)->queue(new UserCreated($user, $password));
            \Log::info("User creation email queued for: {$user->email}");
        } catch (\Exception $e) {
            // Log the error but don't fail the request
            \Log::error('Failed to queue user creation email: ' . $e->getMessage());
        }

        // Also log the password for testing purposes
        \Log::info("User created successfully. Email: {$user->email}, Password: {$password}");

        return response()->json($user, 201);
    }

    public function show($id)
    {
        $user = User::where('id', $id)->where('is_deleted', false)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json($user);
    }

    public function update(Request $request, $id)
    {
        $user = User::where('id', $id)->where('is_deleted', false)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($id),
            ],
            'contact_number' => 'nullable|string|max:20',
        ]);

        $user->update($validated);

        return response()->json($user);
    }

    public function destroy($id)
    {
        $user = User::where('id', $id)->where('is_deleted', false)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->update(['is_deleted' => true]);

        return response()->json(['message' => 'User deleted successfully'], 200);
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:users,id'
        ]);

        $ids = $validated['ids'];
        User::whereIn('id', $ids)
            ->where('is_deleted', false)
            ->update(['is_deleted' => true]);

        return response()->json([
            'message' => count($ids) . ' users deleted successfully'
        ], 200);
    }

    public function restore($id)
    {
        $user = User::find($id);

        if (!$user || $user->is_deleted == false) {
            return response()->json(['message' => 'User not found or not deleted'], 404);
        }

        $user->update(['is_deleted' => false]);

        return response()->json(['message' => 'User restored successfully'], 200);
    }

    public function promote($id)
    {
        $user = User::where('id', $id)->where('is_deleted', false)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Only allow promoting customers to admin
        if ($user->role !== 'customer') {
            return response()->json([
                'message' => 'Only customers can be promoted to admin'
            ], 422);
        }

        $user->update(['role' => 'admin']);

        return response()->json([
            'message' => 'User promoted to admin successfully',
            'user' => $user
        ], 200);
    }
}