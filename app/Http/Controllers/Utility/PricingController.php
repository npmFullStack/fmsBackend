<?php

namespace App\Http\Controllers\Utility;

use App\Http\Controllers\Controller;
use App\Services\PricingService;
use App\Models\Item;
use App\Models\Category;
use App\Models\ShipRoute;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    public function calculateItemPrice(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:items,id',
            'ship_route_id' => 'required|exists:ship_routes,id'
        ]);

        $item = Item::with('category')->find($validated['item_id']);
        $shipRoute = ShipRoute::with(['shippingLine', 'origin', 'destination'])
                        ->where('is_deleted', false)
                        ->find($validated['ship_route_id']);

        if (!$item || !$shipRoute) {
            return response()->json(['message' => 'Item or ship route not found'], 404);
        }

        $pricing = PricingService::calculateItemPrice(
            $item->base_price,
            $item->weight,
            $item->category,
            $shipRoute
        );

        return response()->json([
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'weight' => $item->weight,
                'base_price' => $item->base_price,
                'category' => $item->category->name
            ],
            'pricing' => $pricing
        ]);
    }

    public function calculateBulkShipping(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'sometimes|integer|min:1',
            'ship_route_id' => 'required|exists:ship_routes,id'
        ]);

        $shipRoute = ShipRoute::with(['shippingLine', 'origin', 'destination'])
                        ->where('is_deleted', false)
                        ->find($validated['ship_route_id']);

        if (!$shipRoute) {
            return response()->json(['message' => 'Ship route not found'], 404);
        }

        $result = PricingService::calculateBulkShipping($validated['items'], $shipRoute);

        return response()->json($result);
    }

    public function getAvailableShippingLines(Request $request)
    {
        $validated = $request->validate([
            'origin_id' => 'required|exists:ports,id',
            'destination_id' => 'required|exists:ports,id'
        ]);

        $shippingLines = PricingService::getAvailableShippingLines(
            $validated['origin_id'],
            $validated['destination_id']
        );

        return response()->json($shippingLines);
    }

    public function getShippingRates(Request $request)
    {
        $validated = $request->validate([
            'weight' => 'required|numeric|min:0.01',
            'origin_id' => 'required|exists:ports,id',
            'destination_id' => 'required|exists:ports,id'
        ]);

        $routes = ShipRoute::with(['shippingLine', 'origin', 'destination'])
                    ->where('origin_id', $validated['origin_id'])
                    ->where('destination_id', $validated['destination_id'])
                    ->where('is_deleted', false)
                    ->get();

        $rates = $routes->map(function($route) use ($validated) {
            $shippingCost = PricingService::calculateShippingPrice(
                $validated['weight'],
                $route
            );

            return [
                'ship_route_id' => $route->id,
                'shipping_line' => $route->shippingLine->name,
                'origin_port' => $route->origin->name,
                'destination_port' => $route->destination->name,
                'distance_km' => $route->distance_km,
                'base_rate_per_km' => $route->shippingLine->base_rate_per_km,
                'weight_rate_per_km' => $route->shippingLine->weight_rate_per_km,
                'min_charge' => $route->shippingLine->min_charge,
                'estimated_cost' => $shippingCost
            ];
        });

        return response()->json($rates);
    }
}