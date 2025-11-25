<!DOCTYPE html>
<html>
<head>
    <title>Mock Payment Checkout</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md max-w-md w-full">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Mock Payment</h1>
            <p class="text-gray-600">Testing Payment Flow</p>
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <h2 class="font-semibold text-blue-800">Payment Details</h2>
            <div class="mt-2 space-y-2 text-sm">
                <div class="flex justify-between">
                    <span>Booking #:</span>
                    <span class="font-medium">{{ $payment->booking->booking_number }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Amount:</span>
                    <span class="font-medium text-green-600">₱{{ number_format($payment->amount, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Method:</span>
                    <span class="font-medium">{{ ucfirst($payment->payment_method) }}</span>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <button onclick="processPayment('success')" 
                    class="w-full bg-green-600 text-white py-3 px-4 rounded-lg hover:bg-green-700 transition-colors font-medium">
                ✅ Simulate Successful Payment
            </button>
            
            <button onclick="processPayment('fail')" 
                    class="w-full bg-red-600 text-white py-3 px-4 rounded-lg hover:bg-red-700 transition-colors font-medium">
                ❌ Simulate Failed Payment
            </button>
        </div>

        <div class="mt-6 text-center">
            <a href="{{ url('/customer/bookings') }}" 
               class="text-blue-600 hover:text-blue-800 text-sm">
                ← Back to Bookings
            </a>
        </div>
    </div>

    <script>
        async function processPayment(action) {
            const buttons = document.querySelectorAll('button');
            buttons.forEach(btn => btn.disabled = true);

            try {
                const response = await fetch('/api/mock-payment/{{ $payment->id }}/process', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ action: action })
                });

                const result = await response.json();

                if (result.success) {
                    alert('✅ Payment Successful!');
                    window.location.href = result.redirect_url || '/customer/bookings';
                } else {
                    alert('❌ Payment Failed: ' + result.message);
                    buttons.forEach(btn => btn.disabled = false);
                }
            } catch (error) {
                alert('❌ Error processing payment');
                console.error('Payment error:', error);
                buttons.forEach(btn => btn.disabled = false);
            }
        }
    </script>
</body>
</html>