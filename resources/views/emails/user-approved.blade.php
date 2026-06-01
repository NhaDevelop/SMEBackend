<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Account Approved</title>
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
            /* Teal 700 */
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

        .btn {
            display: inline-block;
            background-color: #0d9488;
            /* Teal 600 */
            color: #ffffff;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: bold;
            margin-top: 16px;
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
            <h1>Account Approved!</h1>
        </div>
        <div class="content">
            <p>Hello {{ $user->full_name }},</p>

            <p>Great news! Your account on the <strong>Investment Readiness Intelligence Platform (IRiP)</strong> has
                been successfully reviewed and approved by an administrator.</p>

            <p>You can now log in and access your {{ ucfirst(strtolower($user->role)) }} dashboard to start using the
                platform.</p>

            <div style="text-align: center;">
                <a href="{{ config('app.frontend_url') }}/login"
                    style="display: inline-block; background-color: #0d9488; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: bold; margin-top: 16px; margin-bottom: 16px;">Log
                    In to Your Account</a>
            </div>

            <p>If you have any questions or need assistance, feel free to contact our support team.</p>

            <p>Best regards,<br>The IRiP Team</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Investment Readiness Intelligence Platform. All rights reserved.
        </div>
    </div>
</body>

</html>