<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Quote;
use App\Mail\QuoteSent;
use Illuminate\Support\Facades\Mail;

class SendQuoteEmailCommand extends Command
{
    protected $signature = 'send:quote-email {quoteId}';
    protected $description = 'Send quote email to customer';

    public function handle()
    {
        $quoteId = $this->argument('quoteId');
        
        try {
            $quote = Quote::with(['containerSize', 'origin', 'destination', 'items'])
                ->find($quoteId);

            if (!$quote) {
                \Log::error("Quote not found for email: {$quoteId}");
                return 1;
            }

            \Log::info("Sending email for quote: {$quoteId} to {$quote->email}");

            Mail::to($quote->email)->send(new QuoteSent($quote));

            \Log::info("Email sent successfully for quote: {$quoteId}");

            return 0;
        } catch (\Exception $e) {
            \Log::error("Failed to send quote email: " . $e->getMessage(), [
                'quote_id' => $quoteId,
                'error' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}