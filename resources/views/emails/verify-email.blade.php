<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify your email address</title>
</head>
<body style="margin: 0; background: #f5f5f5; color: #292929; font-family: Arial, sans-serif;">
    <div style="padding: 32px 16px;">
        <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e5e5; border-radius: 12px; padding: 32px;">
            <p style="margin: 0 0 20px;">
                <img src="{{ asset('branding/sorsu%20logo.png') }}" alt="SorSUpport" width="56" height="56" style="display: inline-block; width: 56px; height: 56px; vertical-align: middle; border: 0;">
                <span style="display: inline-block; margin-left: 10px; vertical-align: middle; color: #800000; font-size: 18px; font-weight: 700;">SorSUpport</span>
            </p>
            <h1 style="margin: 0 0 20px; font-size: 24px; line-height: 1.3;">Verify your email address</h1>
            <p style="margin: 0 0 16px; font-size: 16px; line-height: 1.6;">Please verify your email address to activate your SorSUpport account.</p>
            <p style="margin: 0 0 24px; font-size: 16px; line-height: 1.6;">Click the button below to complete verification.</p>
            <p style="margin: 0 0 24px;">
                <a href="{{ $verificationUrl }}" style="display: inline-block; padding: 12px 24px; border-radius: 999px; background: #800000; color: #ffffff; font-size: 16px; font-weight: 600; text-decoration: none;">Verify Email Address</a>
            </p>
            <p style="margin: 0; color: #666666; font-size: 14px; line-height: 1.5;">For your security, please use this verification link only once.</p>
        </div>
    </div>
</body>
</html>
