<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Shipping Quote</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .quote-details { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .charges-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .charges-table th, .charges-table td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        .charges-table th { background: #f8f9fa; }
        .total-amount { background: #007bff; color: white; padding: 15px; border-radius: 5px; text-align: center; font-size: 24px; font-weight: bold; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; color: #6c757d; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Your Shipping Quote</h1>
        </div>
        
        <div class="content">
            <p>Dear {{ $quote->first_name }} {{ $quote->last_name }},</p>
            
            <p>Thank you for your interest in our shipping services. We've prepared a detailed quote for your shipment based on the information you provided.</p>
            
            <div class="quote-details">
                <h3>Shipment Details</h3>
                <p><strong>Route:</strong> {{ $quote->origin->route_name ?? 'N/A' }} → {{ $quote->destination->route_name ?? 'N/A' }}</p>
                <p><strong>Container:</strong> {{ $quote->container_quantity }} × {{ $quote->containerSize->size ?? 'N/A' }}</p>
                <p><strong>Service Mode:</strong> {{ $quote->mode_of_service }}</p>
                <p><strong>Terms:</strong> {{ $quote->terms }} days</p>
            </div>

            @if($quote->charges && count($quote->charges) > 0)
            <h3>Quote Breakdown</h3>
            <table class="charges-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($quote->charges as $charge)
                    <tr>
                        <td>{{ $charge['description'] }}</td>
                        <td>${{ number_format($charge['amount'], 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif

            <div class="total-amount">
                Total Quote Amount: ₱{{ number_format($quote->total_amount, 2) }}
            </div>

            <p>This quote is valid for 30 days. If you'd like to proceed with this shipment or have any questions, please don't hesitate to contact us.</p>
            
            <p>Best regards,<br>XMFFI</p>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} Xtra Mile Freight Forwarding Inc. All rights reserved.</p>
        </div>
    </div>
</body>
</html>