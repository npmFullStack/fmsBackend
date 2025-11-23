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
        'charges',
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
    'is_deleted' => 'boolean',
    'charges' => 'array', // Make sure this exists
];

    // Relationships
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'booking_id', 'booking_id');
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

    // Calculate aging based on invoice date and terms
    public function calculateAging()
    {
        if ($this->is_paid) {
            $this->aging_days = 0;
            $this->aging_bucket = 'current';
            $this->is_overdue = false;
            return $this;
        }

        if (!$this->invoice_date) {
            $this->aging_days = 0;
            $this->aging_bucket = 'current';
            $this->is_overdue = false;
            return $this;
        }

        $invoiceDate = Carbon::parse($this->invoice_date);
        $now = Carbon::now();
        
        $agingDays = $invoiceDate->diffInDays($now);
        $this->aging_days = $agingDays;
        
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

        // Check if overdue based on terms
        $terms = $this->booking->terms ?? 0;
        $dueDate = $invoiceDate->copy()->addDays($terms);
        
        $this->is_overdue = $now->greaterThan($dueDate);
        $this->due_date = $dueDate;
        
        return $this;
    }

    // Set invoice date when booking is delivered
    public function setInvoiceDates()
    {
        if ($this->booking && $this->booking->booking_status === 'delivered') {
            // Use delivery date as invoice date
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
    // Ensure numeric values
    $totalPayment = $this->total_payment ?? 0;
    $totalExpenses = $this->total_expenses ?? 0;
    
    // Gross income is the total payment expected
    $this->gross_income = $totalPayment;
    
    // Net revenue is gross income minus expenses
    $this->net_revenue = $this->gross_income - $totalExpenses;
    
    // Profit (same as net revenue in this context)
    $this->profit = $this->net_revenue;
    
    // Calculate paid amount from payments if relationship exists
    $paidAmount = 0;
    if (method_exists($this, 'payments') && $this->relationLoaded('payments')) {
        $paidAmount = $this->payments->where('status', 'completed')->sum('amount');
    } elseif (method_exists($this, 'payments')) {
        $paidAmount = $this->payments()->where('status', 'completed')->sum('amount');
    }
    
    // Collectible amount is total payment minus paid amount
    $this->collectible_amount = max(0, $totalPayment - $paidAmount);
    
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
    return $this;
}

// Boot method for automatic calculations
protected static function boot()
{
    parent::boot();

    static::saving(function ($model) {
        // Only calculate if not already paid
        if (!$model->is_paid) {
            $model->setInvoiceDates()
                  ->calculateAging()
                  ->calculateFinancials();
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
        
        return 'due_in_' . $now->diffInDays($dueDate) . '_days';
    }

    // New accessor for total paid amount
    public function getTotalPaidAttribute()
    {
        return $this->payments->where('status', 'completed')->sum('amount');
    }

    // New accessor for remaining balance
    public function getRemainingBalanceAttribute()
    {
        return $this->collectible_amount;
    }
    
        // Add accessor for charges if needed
    public function getChargesAttribute($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    public function setChargesAttribute($value)
    {
        $this->attributes['charges'] = json_encode($value);
    }
}