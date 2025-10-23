<?php namespace App\Services;

use App\Models\Category;
use App\Models\ShippingLine;
use App\Models\ShipRoute;
use App\Models\Item;

class PricingService
{
    public static function calculateShippingPrice($itemWeight, ShipRoute $shipRoute)
    {
        $shippingLine = $shipRoute->shippingLine;
        
        // Calculate shipping cost: base rate + (weight × weight rate) × distance
        $baseCost = $shippingLine->base_rate_per_km * $shipRoute->distance_km;
        $weightCost = $itemWeight * $shippingLine->weight_rate_per_km * $shipRoute->distance_km;
        $totalShippingCost = $baseCost + $weightCost;
        
        // Apply minimum charge if applicable
        if ($totalShippingCost < $shippingLine->min_charge) {
            $totalShippingCost = $shippingLine->min_charge;
        }

        return round($totalShippingCost, 2);
    }

    public static function calculateItemPrice($basePrice, $weight, Category $category, ShipRoute $shipRoute = null)
    {
        // Original item pricing formula
        $costBeforeSurcharge = $basePrice + ($weight * $category->weight_multiplier);
        $surchargeAmount = $costBeforeSurcharge * ($category->surcharge_percentage / 100);
        $itemTotalPrice = $costBeforeSurcharge + $surchargeAmount;

        $result = [
            'cost_before_surcharge' => round($costBeforeSurcharge, 2),
            'surcharge_amount' => round($surchargeAmount, 2),
            'item_total_price' => round($itemTotalPrice, 2),
            'shipping_cost' => 0,
            'grand_total' => round($itemTotalPrice, 2),
            'shipping_details' => null
        ];

        // Add shipping cost if ship route provided
        if ($shipRoute) {
            $shippingCost = self::calculateShippingPrice($weight, $shipRoute);
            $result['shipping_cost'] = $shippingCost;
            $result['grand_total'] = round($itemTotalPrice + $shippingCost, 2);
            $result['shipping_details'] = [
                'shipping_line' => $shipRoute->shippingLine->name,
                'origin_port' => $shipRoute->origin->name,
                'destination_port' => $shipRoute->destination->name,
                'distance_km' => $shipRoute->distance_km,
                'base_rate_per_km' => $shipRoute->shippingLine->base_rate_per_km,
                'weight_rate_per_km' => $shipRoute->shippingLine->weight_rate_per_km,
                'min_charge' => $shipRoute->shippingLine->min_charge
            ];
        }

        return $result;
    }

    public static function calculateBulkShipping($items, ShipRoute $shipRoute)
    {
        $totalItemPrice = 0;
        $totalShippingCost = 0;
        $itemBreakdown = [];

        foreach ($items as $itemData) {
            if (is_array($itemData)) {
                $itemId = $itemData['item_id'] ?? null;
                $quantity = $itemData['quantity'] ?? 1;
            } else {
                $itemId = $itemData;
                $quantity = 1;
            }

            $item = Item::with('category')->find($itemId);
            
            if (!$item) {
                continue;
            }

            // Calculate price for one item
            $itemPricing = self::calculateItemPrice(
                $item->base_price, 
                $item->weight, 
                $item->category, 
                $shipRoute
            );

            // Multiply by quantity
            $itemTotal = $itemPricing['item_total_price'] * $quantity;
            $itemShipping = $itemPricing['shipping_cost'] * $quantity;

            $itemBreakdown[] = [
                'item_id' => $item->id,
                'item_name' => $item->name,
                'quantity' => $quantity,
                'unit_weight' => $item->weight,
                'total_weight' => $item->weight * $quantity,
                'unit_price' => $itemPricing['item_total_price'],
                'total_price' => $itemTotal,
                'unit_shipping' => $itemPricing['shipping_cost'],
                'total_shipping' => $itemShipping,
                'unit_total' => $itemPricing['grand_total'],
                'line_total' => $itemTotal + $itemShipping
            ];

            $totalItemPrice += $itemTotal;
            $totalShippingCost += $itemShipping;
        }

        return [
            'item_breakdown' => $itemBreakdown,
            'summary' => [
                'total_items_price' => round($totalItemPrice, 2),
                'total_shipping_cost' => round($totalShippingCost, 2),
                'grand_total' => round($totalItemPrice + $totalShippingCost, 2),
                'total_quantity' => array_sum(array_column($itemBreakdown, 'quantity')),
                'total_weight' => array_sum(array_column($itemBreakdown, 'total_weight'))
            ],
            'shipping_details' => [
                'shipping_line' => $shipRoute->shippingLine->name,
                'route' => $shipRoute->origin->name . ' to ' . $shipRoute->destination->name,
                'distance_km' => $shipRoute->distance_km
            ]
        ];
    }

    public static function getAvailableShippingLines($originId, $destinationId)
    {
        return ShipRoute::with(['shippingLine', 'origin', 'destination'])
            ->where('origin_id', $originId)
            ->where('destination_id', $destinationId)
            ->where('is_deleted', false)
            ->get()
            ->map(function($route) {
                return [
                    'ship_route_id' => $route->id,
                    'shipping_line_id' => $route->shippingLine->id,
                    'shipping_line_name' => $route->shippingLine->name,
                    'origin_port' => $route->origin->name,
                    'destination_port' => $route->destination->name,
                    'distance_km' => $route->distance_km,
                    'base_rate_per_km' => $route->shippingLine->base_rate_per_km,
                    'weight_rate_per_km' => $route->shippingLine->weight_rate_per_km,
                    'min_charge' => $route->shippingLine->min_charge
                ];
            });
    }
}