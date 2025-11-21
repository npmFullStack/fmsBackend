<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AccountsReceivable extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'total_expenses',
        'total_payment',
        'collectible_amount',
        'gross_income',
        'net_revenue',
        'profit',
        'invoice_date',
        'due_date',
        'aging_days',
        'aging_bucket',
        'is_overdue',
        'is_paid',
        'is_deleted'
    ];

    protected $casts = [
        'total_expenses' => 'decimal:2',
        'total_payment' => 'decimal:2',
        'collectible_amount' => 'decimal:2',
        'gross_income' => 'decimal:2',
        'net_revenue' => 'decimal:2',
        'profit' => 'decimal:2',
        'invoice_date' => 'date',
        'due_date' => 'date',
        'is_overdue' => 'boolean',
        'is_paid' => 'boolean',
        'is_deleted' => 'boolean'
    ];

    // Relationships
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    // Scopes
    public function scopeNotDeleted($query)
    {
        return $query->where('is_deleted', false);
    }

    public function scopePaid($query)
    {
        return $query->where('is_paid', true);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('is_paid', false);
    }

    public function scopeOverdue($query)
    {
        return $query->where('is_overdue', true);
    }

    public function scopeByAgingBucket($query, $bucket)
    {
        return $query->where('aging_bucket', $bucket);
    }

    // Calculate aging based on due date
    public function calculateAging()
    {
        if (!$this->due_date || $this->is_paid) {
            $this->aging_days = 0;
            $this->aging_bucket = 'current';
            $this->is_overdue = false;
            return $this;
        }

        $dueDate = Carbon::parse($this->due_date);
        $now = Carbon::now();
        
        // Only start counting aging if due date has passed
        if ($now->greaterThan($dueDate)) {
            $agingDays = $dueDate->diffInDays($now);
            $this->aging_days = $agingDays;
            $this->is_overdue = true;
            
            // Determine aging bucket
            if ($agingDays <= 30) {
                $this->aging_bucket = '1-30';
            } elseif ($agingDays <= 60) {
                $this->aging_bucket = '31-60';
            } elseif ($agingDays <= 90) {
                $this->aging_bucket = '61-90';
            } else {
                $this->aging_bucket = 'over_90';
            }
        } else {
            // Not yet due
            $this->aging_days = 0;
            $this->aging_bucket = 'current';
            $this->is_overdue = false;
        }
        
        return $this;
    }

    // Set invoice and due dates based on booking delivery and terms
    public function setInvoiceDates()
    {
        if ($this->booking && $this->booking->booking_status === 'delivered') {
            // Use delivery date as invoice date, or current date if not set
            $deliveryDate = $this->booking->delivery_date ?: $this->booking->updated_at;
            $this->invoice_date = Carbon::parse($deliveryDate)->format('Y-m-d');
            
            // Calculate due date based on terms
            $terms = $this->booking->terms ?: 0;
            $this->due_date = Carbon::parse($this->invoice_date)->addDays($terms)->format('Y-m-d');
        }
        
        return $this;
    }

    // Calculate financial metrics
    public function calculateFinancials()
    {
        // Gross income is the total payment expected
        $this->gross_income = $this->total_payment;
        
        // Net revenue is gross income minus expenses
        $this->net_revenue = $this->gross_income - $this->total_expenses;
        
        // Profit (same as net revenue in this context)
        $this->profit = $this->net_revenue;
        
        // Collectible amount is what's still owed
        $this->collectible_amount = $this->is_paid ? 0 : $this->total_payment;
        
        return $this;
    }

    // Check if fully paid
    public function markAsPaid()
    {
        $this->is_paid = true;
        $this->collectible_amount = 0;
        $this->is_overdue = false;
        $this->aging_days = 0;
        $this->aging_bucket = 'current';
        $this->calculateFinancials();
        return $this;
    }

    // Boot method for automatic calculations
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            $model->setInvoiceDates()
                  ->calculateAging()
                  ->calculateFinancials();
        });

        // Listen for booking status changes to update AR
        static::updated(function ($model) {
            if ($model->booking && $model->booking->wasChanged('booking_status')) {
                $model->setInvoiceDates()->calculateAging()->save();
            }
        });
    }

    // Accessor for due status
    public function getDueStatusAttribute()
    {
        if ($this->is_paid) {
            return 'paid';
        }
        
        if (!$this->due_date) {
            return 'pending';
        }
        
        $dueDate = Carbon::parse($this->due_date);
        $now = Carbon::now();
        
        if ($now->greaterThan($dueDate)) {
            return 'overdue';
        }
        
        return 'due_in_' . $now->diffInDays($due_date) . '_days';
    }
}