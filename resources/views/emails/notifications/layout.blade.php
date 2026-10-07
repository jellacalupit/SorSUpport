{{-- One design for every ticket email. The wording comes from App\Services\TicketEmailComposer. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
</head>
<body style="margin: 0; background: #f5f5f5; color: #292929; font-family: Arial, sans-serif;">
    <div style="padding: 32px 16px;">
        <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e5e5; border-radius: 12px; padding: 32px;">
            <p style="margin: 0 0 20px;">
                <img src="{{ asset('branding/sorsu%20logo.png') }}" alt="SorSUpport" width="56" height="56" style="display: inline-block; width: 56px; height: 56px; vertical-align: middle; border: 0;">
                <span style="display: inline-block; margin-left: 10px; vertical-align: middle; color: #800000; font-size: 18px; font-weight: 700;">SorSUpport</span>
            </p>
            <h1 style="margin: 0 0 20px; font-size: 22px; line-height: 1.3;">{{ $heading ?? $subject }}</h1>

            @foreach ((array) ($lines ?? [$body ?? '']) as $line)
                <p style="margin: 0 0 16px; font-size: 15px; line-height: 1.6;">{{ $line }}</p>
            @endforeach

            @if (! empty($details))
                <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; margin: 0 0 24px; border-collapse: collapse; font-size: 14px;">
                    @foreach ($details as $label => $value)
                        <tr>
                            <td style="padding: 8px 12px 8px 0; border-top: 1px solid #eeeeee; color: #666666; white-space: nowrap; vertical-align: top;">{{ $label }}</td>
                            <td style="padding: 8px 0; border-top: 1px solid #eeeeee; font-weight: 600;">{{ $value }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif

            @if (! empty($actionUrl))
                <p style="margin: 0 0 24px;">
                    <a href="{{ $actionUrl }}" style="display: inline-block; padding: 12px 24px; border-radius: 999px; background: #800000; color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none;">{{ $actionLabel ?? 'Open Ticket' }}</a>
                </p>
            @endif

            <p style="margin: 0; color: #666666; font-size: 13px; line-height: 1.5;">{{ $footer ?? 'This is an automated message from SorSUpport, the student concern system of Sorsogon State University – Bulan Campus. Please do not reply to this email.' }}</p>
        </div>
    </div>
</body>
</html>
