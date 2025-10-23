<?php

namespace App\Http\Controllers;

use App\Models\ContainerType;
use Illuminate\Http\Request;

class ContainerTypeController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search', '');

        $query = ContainerType::where('is_deleted', 0);

        if (!empty($search)) {
            $query->where('size', 'like', '%' . $search . '%');
        }

        $sort = $request->get('sort', 'id');
        $direction = $request->get('direction', 'asc');

        $data = $query->orderBy($sort, $direction)->paginate($perPage);

        return response()->json($data);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'size' => 'required|string|max:255',
            'load_type' => 'required|in:LCL,FCL',
            'max_weight' => 'required|numeric|min:0',
            'fcl_rate' => 'nullable|numeric|min:0',
        ]);

        $containerType = ContainerType::create(array_merge($validated, ['is_deleted' => 0]));

        return response()->json($containerType, 201);
    }

    public function show($id)
    {
        $containerType = ContainerType::where('id', $id)->where('is_deleted', 0)->first();

        if (!$containerType) {
            return response()->json(['message' => 'Container type not found'], 404);
        }

        return response()->json($containerType);
    }

    public function update(Request $request, $id)
    {
        $containerType = ContainerType::where('id', $id)->where('is_deleted', 0)->first();

        if (!$containerType) {
            return response()->json(['message' => 'Container type not found'], 404);
        }

        $validated = $request->validate([
            'size' => 'required|string|max:255',
            'load_type' => 'required|in:LCL,FCL',
            'max_weight' => 'required|numeric|min:0',
            'fcl_rate' => 'nullable|numeric|min:0',
        ]);

        $containerType->update($validated);

        return response()->json($containerType);
    }

    public function destroy($id)
    {
        $containerType = ContainerType::where('id', $id)->where('is_deleted', 0)->first();

        if (!$containerType) {
            return response()->json(['message' => 'Container type not found'], 404);
        }

        $containerType->update(['is_deleted' => 1]);

        return response()->json(['message' => 'Container type deleted successfully'], 200);
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:container_types,id'
        ]);

        $ids = $validated['ids'];
        ContainerType::whereIn('id', $ids)->where('is_deleted', 0)->update(['is_deleted' => 1]);

        return response()->json(['message' => count($ids) . ' container types deleted successfully'], 200);
    }

    public function restore($id)
    {
        $containerType = ContainerType::find($id);

        if (!$containerType || $containerType->is_deleted == 0) {
            return response()->json(['message' => 'Container type not found or not deleted'], 404);
        }

        $containerType->update(['is_deleted' => 0]);

        return response()->json(['message' => 'Container type restored successfully'], 200);
    }
}
