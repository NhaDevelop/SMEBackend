<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Registration Update</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f9fafb;
            margin: 0;
            padding: 0;
            color: #374151;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        .header {
            background-color: #be123c; /* Rose 700 */
            color: #ffffff;
            padding: 24px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .logo-container {
            margin-bottom: 16px;
        }
        .logo {
            height: 48px;
            width: auto;
            border-radius: 50%;
            background: white;
            padding: 4px;
        }
        .content {
            padding: 32px 24px;
            line-height: 1.6;
        }
        .content p {
            margin-top: 0;
            margin-bottom: 16px;
        }
        .footer {
            background-color: #f3f4f6;
            color: #6b7280;
            text-align: center;
            padding: 16px;
            font-size: 12px;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo-container">
                <img src="{{ $message->embed(public_path('logo.png')) }}" alt="IRiP Logo" class="logo">
            </div>
            <h1>Update on Your Registration</h1>
        </div>
        <div class="content">
            <p>Hello {{ $user->full_name }},</p>
            
            <p>Thank you for your interest in the <strong>Investment Readiness Intelligence Platform (IRiP)</strong>.</p>
            
            <p>After careful review of your registration details, we are unable to approve your account at this time. This may be due to missing documentation, incorrect registration numbers, or other factors that did not meet the platform's requirements.</p>
            
            <p>If you believe this was a mistake or you have updated information, please feel free to reach out to our support team.</p>
            
            <p>Best regards,<br>The IRiP Team</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Investment Readiness Intelligence Platform. All rights reserved.
        </div>
    </div>
</body>
</html>
