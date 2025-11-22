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
                // Customer Information
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email',
                'contact_number' => 'nullable|string',

                // Shipper Information
                'shipper_first_name' => 'required|string|max:255',
                'shipper_last_name' => 'required|string|max:255',
                'shipper_contact' => 'nullable|string',

                // Consignee Information
                'consignee_first_name' => 'required|string|max:255',
                'consignee_last_name' => 'required|string|max:255',
                'consignee_contact' => 'nullable|string',

                // Shipping Details
                'mode_of_service' => 'required|string',
                'container_size_id' => 'required|exists:container_types,id',
                'container_quantity' => 'required|integer|min:1',
                'origin_id' => 'required|exists:ports,id',
                'destination_id' => 'required|exists:ports,id',
                'shipping_line_id' => 'nullable|exists:shipping_lines,id',
                'truck_comp_id' => 'nullable|exists:truck_comps,id',

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

            // Create quote
            $quote = Quote::create(array_merge($validated, [
                'status' => 'pending',
                'is_deleted' => false,
            ]));

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

            // Send email to customer
            $this->sendQuoteEmail($quote);

            DB::commit();

            return response()->json([
                'message' => 'Quote sent successfully',
                'quote' => $quote->load(['items', 'containerSize', 'origin', 'destination'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to send quote',
                'error' => $e->getMessage()
            ], 500);
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

    private function sendQuoteEmail($quote)
    {
        try {
            Mail::to($quote->email)->send(new \App\Mail\QuoteSent($quote));

            \Log::info('Quote email sent successfully', [
                'to' => $quote->email,
                'quote_id' => $quote->id,
                'total_amount' => $quote->total_amount
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to send quote email: '. $e->getMessage(), [
                'to' => $quote->email,
                'quote_id' => $quote->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}