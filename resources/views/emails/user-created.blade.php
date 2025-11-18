<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Created - XMFFI</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            line-height: 1.7; 
            color: #1e3a8a; 
            background-color: #ffffff; 
            margin: 0; 
            padding: 20px; 
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            background: #ffffff; 
            border-radius: 12px; 
            overflow: hidden; 
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15); 
            border: 2px solid #3b82f6;
        }
        .header { 
            background: #ffffff; 
            padding: 40px 20px; 
            text-align: center; 
            color: #2563eb; 
            border-bottom: 2px solid #bfdbfe;
        }
        .content { 
            padding: 40px 30px; 
            text-align: center;
            background-color: #ffffff;
        }
        .credentials { 
            background: #eff6ff; 
            padding: 25px; 
            border-radius: 10px; 
            margin: 25px 0; 
            border-left: 5px solid #3b82f6;
            border: 2px solid #bfdbfe;
            text-align: left;
        }
        .footer { 
            text-align: center; 
            padding: 30px 20px; 
            color: #64748b; 
            font-size: 14px; 
            background: #f0f9ff; 
            border-top: 2px solid #bfdbfe;
        }
        .btn { 
            display: inline-block; 
            padding: 14px 32px; 
            background: #3b82f6; 
            color: white; 
            text-decoration: none; 
            border-radius: 8px; 
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
        }
        .company-name { 
            color: #2563eb; 
            font-weight: 700; 
        }
        .logo { 
            max-width: 180px; 
            height: auto; 
            margin-bottom: 20px; 
            border-radius: 8px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        .celebrate-image {
            max-width: 200px;
            height: auto;
            margin: 20px auto;
            border-radius: 12px;
            display: block;
        }
        .welcome-text {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #2563eb;
        }
        .tagline {
            font-size: 16px;
            opacity: 0.95;
            margin: 0;
            color: #2563eb;
        }
        .security-note {
            background: #dbeafe;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
            margin: 20px 0;
            text-align: left;
            color: #1e40af;
        }
        .icon-text {
            color: #1e3a8a;
        }
        a {
            color: #2563eb;
        }
        code {
            background: #dbeafe; 
            padding: 4px 8px; 
            border-radius: 4px; 
            font-family: monospace;
            color: #1e40af;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <!-- Company Logo - Localhost URL -->
            <img src="http://localhost:8000/images/xmffi-logo.png" alt="XMFFI Logo" class="logo">
            <div class="welcome-text">
                Welcome to XMFFI
            </div>
            <div class="tagline">XtraMile Freight Forwarding Inc.</div>
        </div>
        
        <div class="content">
            <!-- Celebration Image - Localhost URL -->
            <img src="http://localhost:8000/images/addUser.png" alt="Congratulations" class="celebrate-image">
            
            <p class="icon-text">Dear <strong>{{ $user->first_name }} {{ $user->last_name }}</strong>,</p>
            
            <p class="icon-text">Your account has been successfully created with <span class="company-name">XtraMile Freight Forwarding Inc.</span>! You can now access our platform using the credentials below:</p>
            
            <div class="credentials">
                <h3 style="margin-top: 0; color: #2563eb; font-size: 18px;">
                    Your Login Credentials
                </h3>
                <p class="icon-text"><strong>Email:</strong> {{ $user->email }}</p>
                <p class="icon-text"><strong>Password:</strong> <code>{{ $password }}</code></p>
            </div>
            
            <div class="security-note">
                <strong>Security Note:</strong> For your security, we recommend that you change your password after your first login.
            </div>
            
            <p style="text-align: center; margin: 30px 0;">
                <a href="http://localhost:5173/login" class="btn">
                    Login to Your Account
                </a>
            </p>
            
            <p class="icon-text">If you have any questions or need assistance, please don't hesitate to contact our support team at <a href="mailto:support@xmffi.com">support@xmffi.com</a>.</p>
            
            <p class="icon-text">Best regards,<br><strong>The XMFFI Team</strong></p>
        </div>
        
        <div class="footer">
            <p>Thank you for choosing <span class="company-name">XtraMile Freight Forwarding Inc.</span>!</p>
            <p>&copy; {{ date('Y') }} XtraMile Freight Forwarding Inc. (XMFFI). All rights reserved.</p>
        </div>
    </div>
</body>
</html>