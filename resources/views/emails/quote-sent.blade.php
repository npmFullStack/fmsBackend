<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Shipping Quote</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            line-height: 1.6; 
            color: #333; 
            background-color: #f8fafc;
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            padding: 20px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        .header { 
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            padding: 30px 20px; 
            text-align: center; 
            border-radius: 8px 8px 0 0;
            color: white;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: bold;
        }
        .content { 
            padding: 30px 20px; 
        }
        .quote-details { 
            background: #eff6ff; 
            padding: 20px; 
            border-radius: 8px; 
            margin: 20px 0; 
            border-left: 4px solid #3b82f6;
        }
        .quote-details h3 {
            color: #1e40af;
            margin-top: 0;
            border-bottom: 1px solid #dbeafe;
            padding-bottom: 10px;
        }
        .charges-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 20px 0; 
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .charges-table th, .charges-table td { 
            padding: 12px 15px; 
            text-align: left; 
            border-bottom: 1px solid #e5e7eb; 
        }
        .charges-table th { 
            background: #1e40af; 
            color: white;
            font-weight: 600;
        }
        .charges-table tr:nth-child(even) {
            background: #f8fafc;
        }
        .charges-table tr:hover {
            background: #eff6ff;
        }
        .total-amount { 
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            color: white; 
            padding: 25px; 
            border-radius: 8px; 
            text-align: center; 
            font-size: 24px; 
            font-weight: bold; 
            margin: 25px 0; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .footer { 
            text-align: center; 
            padding: 20px; 
            color: #6b7280; 
            font-size: 14px; 
            border-top: 1px solid #e5e7eb;
            margin-top: 20px;
        }
        .contact-info {
            background: #eff6ff;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
            text-align: center;
            border: 1px solid #dbeafe;
        }
        .contact-info p {
            margin: 5px 0;
            color: #374151;
        }
        h3 {
            color: #1e40af;
            border-bottom: 2px solid #eff6ff;
            padding-bottom: 8px;
        }
        p {
            margin-bottom: 15px;
        }
        strong {
            color: #1e40af;
        }
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
                        <td>₱{{ number_format($charge['amount'], 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif

            <div class="total-amount">
                Total Quote Amount: ₱{{ number_format($quote->total_amount, 2) }}
            </div>

            <div class="contact-info">
                <p><strong>This quote is valid for 30 days.</strong></p>
                <p>If you'd like to proceed with this shipment or have any questions, please don't hesitate to contact us.</p>
            </div>
            
            <p>Best regards,<br><strong>Xtra Mile Freight Forwarding Inc.</strong></p>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} Xtra Mile Freight Forwarding Inc. All rights reserved.</p>
        </div>
    </div>
</body>
</html>