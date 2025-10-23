<?php

namespace App\Http\Controllers;

use App\Models\Port;
use Illuminate\Http\Request;

class PortController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search', '');

        $query = Port::where('is_deleted', false);

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%'. $search . '%')
                  ->orWhere('route_name', 'like', '%'. $search . '%') 
                  ->orWhere('address', 'like', '%'. $search . '%');
            });
        }

        $sort = $request->get('sort', 'id');
        $direction = $request->get('direction', 'asc');

        $ports = $query->orderBy($sort, $direction)->paginate($perPage);

        return response()->json($ports);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:ports,name',
            'route_name' => 'required|string|max:255|unique:ports,route_name', // Add this
            'address' => 'nullable|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'is_active' => 'boolean'
        ]);

        $port = Port::create(array_merge($validated, [
            'is_deleted' => false
        ]));

        return response()->json($port, 201);
    }

    public function show($id)
    {
        $port = Port::where('id', $id)->where('is_deleted', false)->first();
        
        if (!$port) {
            return response()->json(['message' => 'Port not found'], 404);
        }

        return response()->json($port);
    }

    public function update(Request $request, $id)
    {
        $port = Port::where('id', $id)->where('is_deleted', false)->first();
        
        if (!$port) {
            return response()->json(['message' => 'Port not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:ports,name,' . $id,
            'route_name' => 'required|string|max:255|unique:ports,route_name,' . $id,
            'address' => 'nullable|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'is_active' => 'boolean'
        ]);

        $port->update($validated);

        return response()->json($port);
    }

    public function destroy($id)
    {
        $port = Port::where('id', $id)->where('is_deleted', false)->first();
        
        if (!$port) {
            return response()->json(['message' => 'Port not found'], 404);
        }

        $port->update(['is_deleted' => true]);

        return response()->json(['message' => 'Port deleted successfully'], 200);
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:ports,id'
        ]);

        $ids = $validated['ids'];
        
        Port::whereIn('id', $ids)
            ->where('is_deleted', false)
            ->update(['is_deleted' => true]);

        return response()->json([
            'message' => count($ids) . ' ports deleted successfully'
        ], 200);
    }

    public function restore($id)
    {
        $port = Port::find($id);

        if (!$port || $port->is_deleted == false) {
            return response()->json(['message' => 'Port not found or not deleted'], 404);
        }

        $port->update(['is_deleted' => false]);

        return response()->json(['message' => 'Port restored successfully'], 200);
    }
}