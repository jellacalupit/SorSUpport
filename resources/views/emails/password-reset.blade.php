<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset your password</title>
</head>
<body style="margin: 0; background: #f5f5f5; color: #292929; font-family: Arial, sans-serif;">
    <div style="padding: 32px 16px;">
        <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e5e5; border-radius: 12px; padding: 32px;">
            <h1 style="margin: 0 0 20px; font-size: 24px; line-height: 1.3;">Reset your password</h1>
            <p style="margin: 0 0 16px; font-size: 16px; line-height: 1.6;">You are receiving this email because we received a password reset request for your account.</p>
            <p style="margin: 0 0 24px; font-size: 16px; line-height: 1.6;">Click the button below to create a new password.</p>
            <p style="margin: 0 0 24px;">
                <a href="{{ $resetUrl }}" style="display: inline-block; padding: 12px 24px; border-radius: 999px; background: #800000; color: #ffffff; font-size: 16px; font-weight: 600; text-decoration: none;">Reset Password</a>
            </p>
            <p style="margin: 0; color: #666666; font-size: 14px; line-height: 1.5;">For your security, this password reset link will expire in {{ $expires }} minutes.</p>
        </div>
    </div>
</body>
</html>
