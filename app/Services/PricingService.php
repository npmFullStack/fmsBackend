<?php
namespace App\Services;

use App\Models\Category;

class PricingService
{
    public static function calculateItemPrice($basePrice, $weight, Category $category)
    {
        // Formula: (Base Price + (Weight × Weight Multiplier)) × (1 + Surcharge Percentage)
        $costBeforeSurcharge = $basePrice + ($weight * $category->weight_multiplier);
        $surchargeAmount = $costBeforeSurcharge * ($category->surcharge_percentage / 100);
        $totalPrice = $costBeforeSurcharge + $surchargeAmount;

        return [
            'cost_before_surcharge' => round($costBeforeSurcharge, 2),
            'surcharge_amount' => round($surchargeAmount, 2),
            'total_price' => round($totalPrice, 2)
        ];
    }
}