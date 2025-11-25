<?php
// app/Http/Controllers/QuoteController.php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class QuoteController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search', '');

        $query = Quote::with(['containerSize', 'origin', 'destination', 'shippingLine', 'truckComp', 'items'])
            ->notDeleted();

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', '%' . $search . '%')
                  ->orWhere('last_name', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        // Filter by status if provided
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $sort = $request->get('sort', 'created_at');
        $direction = $request->get('direction', 'desc');

        $data = $query->orderBy($sort, $direction)->paginate($perPage);

        return response()->json($data);
    }

    public function store(Request $request)
{
    DB::beginTransaction();

    try {
        $validated = $request->validate([
            // Customer Information - make all optional
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email',
            'contact_number' => 'nullable|string',

            // Shipper Information - make all optional
            'shipper_first_name' => 'nullable|string|max:255',
            'shipper_last_name' => 'nullable|string|max:255',
            'shipper_contact' => 'nullable|string',

            // Consignee Information - make all optional
            'consignee_first_name' => 'nullable|string|max:255',
            'consignee_last_name' => 'nullable|string|max:255',
            'consignee_contact' => 'nullable|string',

            // Shipping Details
            'mode_of_service' => 'required|string',
            'container_size_id' => 'required|exists:container_types,id',
            'container_quantity' => 'required|integer|min:1',
            'origin_id' => 'required|exists:ports,id',
            'destination_id' => 'required|exists:ports,id',
            'shipping_line_id' => 'nullable|exists:shipping_lines,id',
            'truck_comp_id' => 'nullable|exists:truck_comps,id',
            'departure_date' => 'nullable|date',
            'delivery_date' => 'nullable|date',

            // Terms
            'terms' => 'required|integer|min:1',

            // Locations
            'pickup_location' => 'nullable|array',
            'delivery_location' => 'nullable|array',

            // Items
            'items' => 'required|array',
            'items.*.name' => 'required|string',
            'items.*.weight' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.category' => 'required|string',
        ]);

        // Convert empty strings to null for database compatibility
        $quoteData = [
            'first_name' => !empty($validated['first_name']) ? $validated['first_name'] : null,
            'last_name' => !empty($validated['last_name']) ? $validated['last_name'] : null,
            'email' => !empty($validated['email']) ? $validated['email'] : null,
            'contact_number' => !empty($validated['contact_number']) ? $validated['contact_number'] : null,
            'shipper_first_name' => !empty($validated['shipper_first_name']) ? $validated['shipper_first_name'] : null,
            'shipper_last_name' => !empty($validated['shipper_last_name']) ? $validated['shipper_last_name'] : null,
            'shipper_contact' => !empty($validated['shipper_contact']) ? $validated['shipper_contact'] : null,
            'consignee_first_name' => !empty($validated['consignee_first_name']) ? $validated['consignee_first_name'] : null,
            'consignee_last_name' => !empty($validated['consignee_last_name']) ? $validated['consignee_last_name'] : null,
            'consignee_contact' => !empty($validated['consignee_contact']) ? $validated['consignee_contact'] : null,
            'mode_of_service' => $validated['mode_of_service'],
            'container_size_id' => $validated['container_size_id'],
            'container_quantity' => $validated['container_quantity'],
            'origin_id' => $validated['origin_id'],
            'destination_id' => $validated['destination_id'],
            'shipping_line_id' => $validated['shipping_line_id'] ?? null,
            'truck_comp_id' => $validated['truck_comp_id'] ?? null,
            'departure_date' => $validated['departure_date'] ?? null,
            'delivery_date' => $validated['delivery_date'] ?? null,
            'terms' => $validated['terms'],
            'pickup_location' => !empty($validated['pickup_location']) ? $validated['pickup_location'] : null,
            'delivery_location' => !empty($validated['delivery_location']) ? $validated['delivery_location'] : null,
            'status' => 'pending',
            'is_deleted' => false,
        ];

        // Create quote
        $quote = Quote::create($quoteData);

        // Create quote items
        foreach ($validated['items'] as $itemData) {
            QuoteItem::create([
                'quote_id' => $quote->id,
                'name' => $itemData['name'],
                'weight' => $itemData['weight'],
                'quantity' => $itemData['quantity'],
                'category' => $itemData['category'],
            ]);
        }

        DB::commit();

        return response()->json([
            'message' => 'Quote request submitted successfully',
            'quote_id' => $quote->id,
            'quote' => $quote->load(['items', 'truckComp', 'containerSize', 'origin', 'destination'])
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Quote submission error: ' . $e->getMessage(), [
            'request_data' => $request->all(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'message' => 'Failed to submit quote request',
            'error' => $e->getMessage()
        ], 500);
    }
}


public function sendQuote(Request $request, $id)
{
    DB::beginTransaction();

    try {
        $quote = Quote::notDeleted()->find($id);

        if (!$quote) {
            return response()->json(['message' => 'Quote not found'], 404);
        }

        $validated = $request->validate([
            'charges' => 'required|array',
            'charges.*.description' => 'required|string',
            'charges.*.amount' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
        ]);

        // Update quote with charges and mark as sent
        $quote->update([
            'charges' => $validated['charges'],
            'total_amount' => $validated['total_amount'],
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        DB::commit();

        // Send email in background (non-blocking)
        $this->sendQuoteEmailBackground($quote);

        return response()->json([
            'message' => 'Quote sent successfully! Email is being processed.',
            'quote' => $quote->load(['items', 'containerSize', 'origin', 'destination'])
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Failed to send quote: ' . $e->getMessage(), [
            'quote_id' => $id,
            'error' => $e->getMessage()
        ]);
        
        return response()->json([
            'message' => 'Failed to send quote',
            'error' => $e->getMessage()
        ], 500);
    }
}

private function sendQuoteEmailBackground($quote)
{
    try {
        // Use Artisan command in background
        $artisanPath = base_path('artisan');
        $command = "php \"{$artisanPath}\" send:quote-email {$quote->id} > /dev/null 2>&1 &";
        
        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen("start /B " . $command, "r"));
        } else {
            exec($command);
        }
        
        \Log::info('Quote email background process started', [
            'quote_id' => $quote->id,
            'email' => $quote->email
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Failed to start background email process: ' . $e->getMessage(), [
            'quote_id' => $quote->id
        ]);
    }
}

    public function show($id)
    {
        $quote = Quote::with(['containerSize', 'origin', 'destination', 'shippingLine', 'truckComp', 'items'])
            ->notDeleted()
            ->find($id);

        if (!$quote) {
            return response()->json(['message' => 'Quote not found'], 404);
        }

        return response()->json($quote);
    }

    public function destroy($id)
    {
        $quote = Quote::notDeleted()->find($id);

        if (!$quote) {
            return response()->json(['message' => 'Quote not found'], 404);
        }

        $quote->update(['is_deleted' => true]);

        return response()->json(['message' => 'Quote deleted successfully'], 200);
    }

    
}