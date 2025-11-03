<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Booking Approved</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .credentials { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .footer { text-align: center; padding: 20px; color: #6c757d; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Booking Approved! 🎉</h1>
        </div>
        
        <div class="content">
            <p>Dear {{ $booking->first_name }} {{ $booking->last_name }},</p>
            
            <p>We're excited to inform you that your booking request <strong>#{{ $booking->id }}</strong> has been approved!</p>
            
            <p>You can now track your shipment and manage your booking using our customer portal.</p>
            
            <div class="credentials">
                <h3>Your Login Credentials:</h3>
                <p><strong>Email:</strong> {{ $booking->email }}</p>
                <p><strong>Password:</strong> {{ $password }}</p>
            </div>
            
            <p><strong>Booking Details:</strong></p>
            <ul>
                <li>Service Mode: {{ $booking->mode_of_service }}</li>
                <li>Departure Date: {{ $booking->departure_date->format('M d, Y') }}</li>
                <li>Container Quantity: {{ $booking->container_quantity }}</li>
            </ul>
            
            <p>Please log in to your account to view complete booking details and track your shipment.</p>
            
            <p>If you have any questions, please don't hesitate to contact our support team.</p>
        </div>
        
        <div class="footer">
            <p>Thank you for choosing our service!</p>
            <p>&copy; {{ date('Y') }} Your Company Name. All rights reserved.</p>
        </div>
    </div>
</body>
</html>