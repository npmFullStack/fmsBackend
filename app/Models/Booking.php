<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'contact_number',
        'shipper_first_name',
        'shipper_last_name',
        'shipper_contact',
        'consignee_first_name',
        'consignee_last_name',
        'consignee_contact',
        'mode_of_service',
        'container_size_id',
        'container_quantity',
        'origin_id',
        'destination_id',
        'shipping_line_id',
        'truck_comp_id',
        'departure_date',
        'delivery_date',
        'terms',
        'pickup_location',
        'delivery_location',
        'booking_status' => 'sometimes|in:pending,picked_up,origin_port,in_transit,destination_port,out_for_delivery,delivered',
        'status',
        'is_deleted',
        'user_id',
        'booking_number',
        'hwb_number',
        'van_number'
    ];

    protected $casts = [
        'pickup_location' => 'array',
        'delivery_location' => 'array',
        'departure_date' => 'date',
        'delivery_date' => 'date',
        'container_quantity' => 'integer',
        'terms' => 'integer',
        'is_deleted' => 'boolean'
    ];

    protected static function boot()
    {
        parent::boot();

        // When a booking is soft deleted, also soft delete related records
        static::updated(function ($booking) {
            // When booking is deleted
            if ($booking->is_deleted && !$booking->getOriginal('is_deleted')) {
                // Delete associated AP record
                $ap = $booking->accountsPayable;
                if ($ap) {
                    $ap->update(['is_deleted' => true]);

                    // Delete all associated charges
                    if ($ap->freightCharge) {
                        $ap->freightCharge->update(['is_deleted' => true]);
                    }
                    
                    $ap->truckingCharges()->update(['is_deleted' => true]);
                    $ap->portCharges()->update(['is_deleted' => true]);
                    $ap->miscCharges()->update(['is_deleted' => true]);
                }

                // Delete cargo monitoring record
                if ($booking->cargoMonitoring) {
                    $booking->cargoMonitoring->update(['is_deleted' => true]);
                }
            }

            // When booking is restored
            if (!$booking->is_deleted && $booking->getOriginal('is_deleted')) {
                // Restore associated AP record
                $ap = AccountsPayable::where('booking_id', $booking->id)->first();
                if ($ap) {
                    $ap->update(['is_deleted' => false]);

                    // Restore all associated charges
                    if ($ap->freightCharge) {
                        $ap->freightCharge->update(['is_deleted' => false]);
                    }
                    
                    $ap->truckingCharges()->update(['is_deleted' => false]);
                    $ap->portCharges()->update(['is_deleted' => false]);
                    $ap->miscCharges()->update(['is_deleted' => false]);
                }

                // Restore cargo monitoring record
                $cargoMonitoring = CargoMonitoring::where('booking_id', $booking->id)->first();
                if ($cargoMonitoring) {
                    $cargoMonitoring->update(['is_deleted' => false]);
                }
            }
        });
    }

    // Relationships
    public function containerSize()
    {
        return $this->belongsTo(ContainerType::class, 'container_size_id');
    }

    public function cargoMonitoring()
    {
        return $this->hasOne(CargoMonitoring::class);
    }

    public function accountsPayable()
    {
        return $this->hasOne(AccountsPayable::class);
    }
    
    public function origin()
    {
        return $this->belongsTo(Port::class, 'origin_id');
    }

    public function destination()
    {
        return $this->belongsTo(Port::class, 'destination_id');
    }

    public function shippingLine()
    {
        return $this->belongsTo(ShippingLine::class, 'shipping_line_id');
    }

    public function truckComp()
    {
        return $this->belongsTo(TruckComp::class, 'truck_comp_id');
    }

    public function items()
    {
        return $this->hasMany(BookingItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

public function accountsReceivable()
{
    return $this->hasOne(AccountsReceivable::class);
}

public function payments() 
{
    return $this->hasMany(Payment::class);
}
    // Scopes
    public function scopeNotDeleted($query)
    {
        return $query->where('is_deleted', false);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeWithoutUser($query)
    {
        return $query->whereNull('user_id');
    }

    public function scopeWithUser($query)
    {
        return $query->whereNotNull('user_id');
    }

    // Calculate total weight from items
    public function getTotalWeightAttribute()
    {
        return $this->items->sum(function ($item) {
            return $item->weight * $item->quantity;
        });
    }

    // Check if booking is associated with a user
    public function getHasUserAttribute()
    {
        return !is_null($this->user_id);
    }

    // Generate unique booking number
    public static function generateBookingNumber()
    {
        do {
            $number = str_pad(mt_rand(1, 999999999), 9, '0', STR_PAD_LEFT);
        } while (self::where('booking_number', $number)->exists());

        return $number;
    }

    // Generate unique HWB number
    public static function generateHwbNumber()
    {
        do {
            $number = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (self::where('hwb_number', $number)->exists());

        return $number;
    }

    // Generate unique van number
    public static function generateVanNumber()
    {
        do {
            $letters = Str::upper(Str::random(4));
            $numbers = str_pad(mt_rand(1, 9999999), 7, '0', STR_PAD_LEFT);
            $vanNumber = $letters . $numbers;
        } while (self::where('van_number', $vanNumber)->exists());

        return $vanNumber;
    }

    // Generate all tracking numbers
    public function generateTrackingNumbers()
    {
        $this->update([
            'booking_number' => self::generateBookingNumber(),
            'hwb_number' => self::generateHwbNumber(),
            'van_number' => self::generateVanNumber()
        ]);
    }
}