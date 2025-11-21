<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Request - XMFFI</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; background: #2563eb; color: white; padding: 20px; }
        .content { background: #f9f9f9; padding: 20px; }
        .footer { text-align: center; padding: 20px; color: #666; }
        .amount { font-size: 24px; font-weight: bold; color: #dc2626; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Payment Request</h1>
        </div>
        <div class="content">
            <p>Dear {{ $booking->first_name }} {{ $booking->last_name }},</p>
            
            <p>We are writing to request payment for your booking. Here are the details:</p>
            
            <h3>Booking Details:</h3>
            <ul>
                <li>Booking Number: {{ $booking->booking_number }}</li>
                <li>Route: {{ $booking->origin->name }} → {{ $booking->destination->name }}</li>
                <li>Container: {{ $booking->container_quantity }} x {{ $booking->container_size->size }}</li>
            </ul>
            
            <h3>Payment Request:</h3>
            <div class="amount">Amount Due: ₱{{ number_format($ar->total_payment, 2) }}</div>
            
            <p>Please log in to your account to make the payment:</p>
            <p style="text-align: center; margin: 20px 0;">
                <a href="{{ url('/customer/bookings') }}" style="background: #2563eb; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px;">
                    Pay Now
                </a>
            </p>
            
            <p>If you have any questions, please contact our support team.</p>
        </div>
        <div class="footer">
            <p>XMFFI - XtraMile Freight Forwarding Inc.</p>
        </div>
    </div>
</body>
</html>