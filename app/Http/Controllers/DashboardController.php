<?php
// app/Http/Controllers/DashboardController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\User;
use App\Models\CargoMonitoring;
use App\Models\Quote;
use App\Models\AccountsReceivable;
use App\Models\AccountsPayable;

class DashboardController extends Controller
{
    public function getDashboardData(Request $request)
    {
        try {
            \Log::info('Dashboard data request received');
            
            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }
            
            // Get all data in single query
            $bookings = $this->getBookingsData();
            $users = $this->getUsersData();
            $cargoMonitoring = $this->getCargoMonitoringData();
            $quotes = $this->getQuotesData();
            $financials = $this->getFinancialData();
            
            \Log::info('Dashboard data fetched successfully', [
                'bookings_count' => count($bookings),
                'users_count' => count($users),
                'cargo_count' => count($cargoMonitoring),
                'quotes_count' => count($quotes)
            ]);
            
            return response()->json([
                'bookings' => $bookings,
                'users' => $users,
                'cargo_monitoring' => $cargoMonitoring,
                'quotes' => $quotes,
                'financials' => $financials,
                'timestamp' => now()
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Dashboard data error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to fetch dashboard data',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }
    
    private function getBookingsData()
    {
        try {
            return Booking::with(['containerSize', 'origin', 'destination'])
                ->where('is_deleted', false)
                ->get();
        } catch (\Exception $e) {
            \Log::error('Error fetching bookings: ' . $e->getMessage());
            return [];
        }
    }
    
    private function getUsersData()
    {
        try {
            return User::where('is_deleted', false)->get();
        } catch (\Exception $e) {
            \Log::error('Error fetching users: ' . $e->getMessage());
            return [];
        }
    }
    
    private function getCargoMonitoringData()
    {
        try {
            return CargoMonitoring::with(['booking'])
                ->where('is_deleted', false)
                ->whereHas('booking', function($query) {
                    $query->where('is_deleted', false);
                })
                ->get();
        } catch (\Exception $e) {
            \Log::error('Error fetching cargo monitoring: ' . $e->getMessage());
            return [];
        }
    }
    
    private function getQuotesData()
    {
        try {
            return Quote::where('is_deleted', false)->get();
        } catch (\Exception $e) {
            \Log::error('Error fetching quotes: ' . $e->getMessage());
            return [];
        }
    }
    
    private function getFinancialData()
    {
        try {
            return [
                'ar_summary' => AccountsReceivable::where('is_deleted', false)
                    ->selectRaw('COUNT(*) as total_records, COALESCE(SUM(total_payment), 0) as total_gross_income')
                    ->first(),
                'ap_summary' => AccountsPayable::where('is_deleted', false)
                    ->selectRaw('COUNT(*) as total_records, COALESCE(SUM(total_expenses), 0) as total_expenses')
                    ->first()
            ];
        } catch (\Exception $e) {
            \Log::error('Error fetching financial data: ' . $e->getMessage());
            return [
                'ar_summary' => ['total_records' => 0, 'total_gross_income' => 0],
                'ap_summary' => ['total_records' => 0, 'total_expenses' => 0]
            ];
        }
    }
}