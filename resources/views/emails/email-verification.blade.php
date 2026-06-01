<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Verify Your Email</title>
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
            background-color: #0f766e;
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
        .note {
            background-color: #f0fdf4;
            border-left: 4px solid #16a34a;
            padding: 12px 16px;
            border-radius: 4px;
            font-size: 13px;
            color: #166534;
            margin-bottom: 24px;
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
            <h1>✉️ Verify Your Email Address</h1>
        </div>
        <div class="content">
            <p>Hello <strong>{{ $user->full_name }}</strong>,</p>

            <p>Thank you for registering with the <strong>Investment Readiness Intelligence Platform (IRiP)</strong>.</p>

            <p>Please click the button below to verify your email address. Once verified, your application will be sent to our admin team for review.</p>

            <div style="text-align: center; margin: 32px 0;">
                <a href="{{ $verificationUrl }}"
                   style="display: inline-block; background-color: #0d9488; color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 6px; font-weight: bold; font-size: 16px;">
                    ✅ Verify My Email
                </a>
            </div>

            <div class="note">
                ⏱️ This link will expire in <strong>24 hours</strong>. If it expires, please register again.
            </div>

            <p>If you did not create an account, no action is required — simply ignore this email.</p>

            <p>Best regards,<br>The IRiP Team</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Investment Readiness Intelligence Platform. All rights reserved.
        </div>
    </div>
</body>
</html>
