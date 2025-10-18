<?php

namespace App\Http\Controllers;

use App\Models\ShipRoute;
use Illuminate\Http\Request;

class ShipRouteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(ShipRoute::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $shipRoute = ShipRoute::create($validated);

        return response()->json($shipRoute, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(ShipRoute $shipRoute)
    {
        return response()->json($shipRoute);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ShipRoute $shipRoute)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $shipRoute->update($validated);

        return response()->json($shipRoute);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ShipRoute $shipRoute)
    {
        $shipRoute->delete();

        return response()->json(null, 204);
    }
}