<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Confirmation - XMFFI</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; background: #2563eb; color: white; padding: 20px; }
        .content { background: #f9f9f9; padding: 20px; }
        .footer { text-align: center; padding: 20px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Payment Confirmation</h1>
        </div>
        <div class="content">
            <p>Dear {{ $user->first_name }} {{ $user->last_name }},</p>
            
            <p>Your payment has been successfully processed. Here are the details:</p>
            
            <h3>Payment Details:</h3>
            <ul>
                <li>Booking Number: {{ $booking->booking_number }}</li>
                <li>Amount Paid: ₱{{ number_format($payment->amount, 2) }}</li>
                <li>Payment Method: {{ ucfirst($payment->payment_method) }}</li>
                <li>Reference Number: {{ $payment->reference_number }}</li>
                <li>Payment Date: {{ $payment->payment_date }}</li>
            </ul>
            
            <h3>Account Summary:</h3>
            <ul>
                <li>Total Amount: ₱{{ number_format($ar->total_payment, 2) }}</li>
                <li>Amount Paid: ₱{{ number_format($ar->total_paid, 2) }}</li>
                <li>Remaining Balance: ₱{{ number_format($ar->remaining_balance, 2) }}</li>
            </ul>
            
            <p>Thank you for your payment!</p>
        </div>
        <div class="footer">
            <p>XMFFI - XtraMile Freight Forwarding Inc.</p>
        </div>
    </div>
</body>
</html>