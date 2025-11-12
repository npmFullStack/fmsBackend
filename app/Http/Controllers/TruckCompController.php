<?php

namespace App\Http\Controllers;

use App\Models\TruckComp;
use Illuminate\Http\Request;

class TruckCompController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search', '');

        $query = TruckComp::where('is_deleted', 0);

        if (!empty($search)) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $sort = $request->get('sort', 'id');
        $direction = $request->get('direction', 'asc');

        $data = $query->orderBy($sort, $direction)->paginate($perPage);

        return response()->json($data);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $truckComp = TruckComp::create([
            'name' => $validated['name'],
            'is_deleted' => 0,
        ]);

        return response()->json($truckComp, 201);
    }

    public function show($id)
    {
        $truckComp = TruckComp::where('id', $id)->where('is_deleted', 0)->first();

        if (!$truckComp) {
            return response()->json(['message' => 'Truck company not found'], 404);
        }

        return response()->json($truckComp);
    }

    public function update(Request $request, $id)
    {
        $truckComp = TruckComp::where('id', $id)->where('is_deleted', 0)->first();

        if (!$truckComp) {
            return response()->json(['message' => 'Truck company not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $truckComp->update($validated);

        return response()->json($truckComp);
    }

    public function destroy($id)
    {
        $truckComp = TruckComp::where('id', $id)->where('is_deleted', 0)->first();

        if (!$truckComp) {
            return response()->json(['message' => 'Truck company not found'], 404);
        }

        $truckComp->update(['is_deleted' => 1]);

        return response()->json(['message' => 'Truck company deleted successfully'], 200);
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:truck_comps,id',
        ]);

        $ids = $validated['ids'];

        TruckComp::whereIn('id', $ids)
            ->where('is_deleted', 0)
            ->update(['is_deleted' => 1]);

        return response()->json(['message' => count($ids) . ' truck companies deleted successfully'], 200);
    }

    public function restore($id)
    {
        $truckComp = TruckComp::find($id);

        if (!$truckComp || $truckComp->is_deleted == 0) {
            return response()->json(['message' => 'Truck company not found or not deleted'], 404);
        }

        $truckComp->update(['is_deleted' => 0]);

        return response()->json(['message' => 'Truck company restored successfully'], 200);
    }
}
