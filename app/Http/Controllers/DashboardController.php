<?php
// app/Http/Controllers/DashboardController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\User;
use App\Models\Quote;
use App\Models\Payment;
use App\Models\CargoMonitoring;
use App\Models\AccountsPayable;
use App\Models\AccountsReceivable;
use App\Models\Port;
use App\Models\TruckComp;
use App\Models\ShippingLine;
use App\Models\ContainerType;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function getDashboardData(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }
            
            // Get role-specific dashboard data
            if ($user->role === 'general_manager') {
                return $this->getGmDashboardData();
            }
            
            if ($user->role === 'admin') {
                return $this->getAdminDashboardData();
            }
            
            if ($user->role === 'customer') {
                return $this->getCustomerDashboardData($user);
            }
            
            // For other roles
            return response()->json([
                'message' => 'Dashboard not available yet',
                'role' => $user->role,
                'timestamp' => now()
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Dashboard data error: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to fetch dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    private function getCustomerDashboardData($user)
    {
        try {
            // Get customer's total bookings
            $totalBookings = Booking::where('is_deleted', false)
                ->where('user_id', $user->id)
                ->count();
            
            // Get customer's pending bookings (not approved yet)
            $pendingBookings = Booking::where('is_deleted', false)
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->count();
            
            // Get customer's delivered/successful bookings
            $deliveredBookings = Booking::where('is_deleted', false)
                ->where('user_id', $user->id)
                ->whereHas('cargoMonitoring', function($query) {
                    $query->where('is_deleted', false)
                          ->where('current_status', 'Delivered');
                })
                ->count();
            
            // Get customer's paid payments
            $paidPayments = Payment::whereHas('booking', function($query) use ($user) {
                    $query->where('is_deleted', false)
                          ->where('user_id', $user->id);
                })
                ->where('status', 'paid')
                ->count();
            
            // Get customer's total payments
            $totalPayments = Payment::whereHas('booking', function($query) use ($user) {
                    $query->where('is_deleted', false)
                          ->where('user_id', $user->id);
                })
                ->count();
            
            // Get recent bookings for the customer (last 5)
            $recentBookings = Booking::where('is_deleted', false)
                ->where('user_id', $user->id)
                ->with(['containerSize', 'origin', 'destination'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function($booking) {
                    return [
                        'id' => $booking->id,
                        'booking_number' => $booking->booking_number,
                        'status' => $booking->status,
                        'booking_status' => $booking->booking_status,
                        'container_size' => $booking->containerSize->name ?? 'N/A',
                        'origin' => $booking->origin->name ?? 'N/A',
                        'destination' => $booking->destination->name ?? 'N/A',
                        'created_at' => $booking->created_at->format('M d, Y'),
                    ];
                });
            
            return response()->json([
                'customer_metrics' => [
                    // Bookings
                    'total_bookings' => $totalBookings,
                    'pending_bookings' => $pendingBookings,
                    'delivered_bookings' => $deliveredBookings,
                    'approved_bookings' => $totalBookings - $pendingBookings, // Calculate approved
                    
                    // Payments
                    'paid_payments' => $paidPayments,
                    'total_payments' => $totalPayments,
                    'payment_ratio' => $totalPayments > 0 ? ($paidPayments / $totalPayments) * 100 : 0,
                    
                    // User info
                    'user_name' => $user->first_name . ' ' . $user->last_name,
                    'email' => $user->email,
                    'contact_number' => $user->contact_number,
                ],
                'recent_bookings' => $recentBookings,
                'role' => 'customer',
                'timestamp' => now()
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Customer dashboard error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    private function getGmDashboardData()
    {
        try {
            // Get bookings counts
            $totalBookings = Booking::where('is_deleted', false)->count();
            $approvedBookings = Booking::where('is_deleted', false)
                ->where('status', 'approved')
                ->count();
            $pendingBookings = Booking::where('is_deleted', false)
                ->where('status', 'pending')
                ->count();
            
            // Get delivered bookings from CargoMonitoring
            $deliveredBookings = CargoMonitoring::where('is_deleted', false)
                ->whereHas('booking', function($query) {
                    $query->where('is_deleted', false);
                })
                ->where('current_status', 'Delivered')
                ->count();
            
            // Get quote counts
            $totalQuotes = Quote::where('is_deleted', false)->count();
            $pendingQuotes = Quote::where('is_deleted', false)
                ->where('status', 'pending')
                ->count();
            
            // Get payment counts
            $totalPayments = Payment::count();
            $pendingPayments = Payment::where('status', 'pending')->count();
            
            // Get total expenses from AccountsPayable
            $totalExpenses = AccountsPayable::where('is_deleted', false)
                ->sum('total_expenses');
            
            // Get total profit from AccountsReceivable
            $totalProfit = AccountsReceivable::where('is_deleted', false)
                ->sum('profit');
            
            // Get total sales/revenue (total_payment from AccountsReceivable)
            $totalSales = AccountsReceivable::where('is_deleted', false)
                ->sum('total_payment');
            
            // Get data for line graph (bookings per month for the last 6 months)
            $lineGraphData = $this->getBookingsByMonth();
            
            // Get data for pie chart (booking status distribution)
            $pieChartData = $this->getBookingStatusDistribution();
            
            return response()->json([
                'gm_metrics' => [
                    // Bookings
                    'total_bookings' => $totalBookings,
                    'approved_bookings' => $approvedBookings,
                    'pending_bookings' => $pendingBookings,
                    'delivered_bookings' => $deliveredBookings,
                    
                    // Quotes
                    'total_quotes' => $totalQuotes,
                    'pending_quotes' => $pendingQuotes,
                    
                    // Payments
                    'total_payments' => $totalPayments,
                    'pending_payments' => $pendingPayments,
                    
                    // Financials
                    'total_expenses' => (float) $totalExpenses,
                    'total_profit' => (float) $totalProfit,
                    'total_sales' => (float) $totalSales,
                ],
                'graphs' => [
                    'line_graph' => $lineGraphData,
                    'pie_chart' => $pieChartData
                ],
                'role' => 'general_manager',
                'timestamp' => now()
            ]);
            
        } catch (\Exception $e) {
            \Log::error('GM dashboard error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    private function getAdminDashboardData()
    {
        try {
            // Get bookings counts
            $totalBookings = Booking::where('is_deleted', false)->count();
            $approvedBookings = Booking::where('is_deleted', false)
                ->where('status', 'approved')
                ->count();
            
            // Get delivered bookings from CargoMonitoring
            $deliveredBookings = CargoMonitoring::where('is_deleted', false)
                ->whereHas('booking', function($query) {
                    $query->where('is_deleted', false);
                })
                ->where('current_status', 'Delivered')
                ->count();
            
            // Get ports count
            $totalPorts = Port::where('is_deleted', false)->count();
            
            // Get truck companies count
            $totalTruckCompanies = TruckComp::where('is_deleted', false)->count();
            
            // Get shipping lines count
            $totalShippingLines = ShippingLine::where('is_deleted', false)->count();
            
            // Get container types count
            $totalContainerTypes = ContainerType::where('is_deleted', false)->count();
            
            // Get total users count (excluding deleted)
            $totalUsers = User::count();
            
            return response()->json([
                'admin_metrics' => [
                    // Bookings
                    'total_bookings' => $totalBookings,
                    'approved_bookings' => $approvedBookings,
                    'delivered_bookings' => $deliveredBookings,
                    
                    // System entities
                    'total_ports' => $totalPorts,
                    'total_truck_companies' => $totalTruckCompanies,
                    'total_shipping_lines' => $totalShippingLines,
                    'total_container_types' => $totalContainerTypes,
                    'total_users' => $totalUsers,
                ],
                'role' => 'admin',
                'timestamp' => now()
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Admin dashboard error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    private function getBookingsByMonth()
    {
        $data = [];
        $months = [];
        
        // Get data for last 6 months
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthYear = $date->format('M Y');
            $months[] = $monthYear;
            
            // Count bookings for this month
            $count = Booking::where('is_deleted', false)
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
            
            $data[] = $count;
        }
        
        return [
            'labels' => $months,
            'data' => $data
        ];
    }
    
    private function getBookingStatusDistribution()
    {
        // Get count of bookings by status
        $totalBookings = Booking::where('is_deleted', false)->count();
        
        if ($totalBookings === 0) {
            return [
                'labels' => ['No Data'],
                'data' => [100],
                'colors' => ['#6B7280']
            ];
        }
        
        $statuses = [
            'approved' => Booking::where('is_deleted', false)->where('status', 'approved')->count(),
            'pending' => Booking::where('is_deleted', false)->where('status', 'pending')->count(),
            'rejected' => Booking::where('is_deleted', false)->where('status', 'rejected')->count(),
        ];
        
        // Also get booking_status from cargo monitoring if available
        $delivered = CargoMonitoring::where('is_deleted', false)
            ->where('current_status', 'Delivered')
            ->whereHas('booking', function($query) {
                $query->where('is_deleted', false);
            })
            ->count();
        
        // Adjust approved count by subtracting delivered
        $statuses['approved'] = max(0, $statuses['approved'] - $delivered);
        if ($delivered > 0) {
            $statuses['delivered'] = $delivered;
        }
        
        $labels = [];
        $data = [];
        $colors = [
            'approved' => '#10B981', // Green
            'pending' => '#F59E0B',  // Yellow
            'rejected' => '#EF4444', // Red
            'delivered' => '#3B82F6', // Blue
        ];
        
        $chartColors = [];
        
        foreach ($statuses as $status => $count) {
            if ($count > 0) {
                $percentage = ($count / $totalBookings) * 100;
                $labels[] = ucfirst($status) . ' (' . number_format($percentage, 1) . '%)';
                $data[] = $count;
                $chartColors[] = $colors[$status];
            }
        }
        
        return [
            'labels' => $labels,
            'data' => $data,
            'colors' => $chartColors
        ];
    }
}