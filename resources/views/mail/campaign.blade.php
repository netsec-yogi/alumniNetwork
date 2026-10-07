<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>
<body style="margin:0;background:#f8fafc;font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:#0f172a">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px">
    <tr><td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:12px;border:1px solid #e2e8f0">
            <tr><td style="background:#0b3d6e;color:#ffffff;padding:18px 28px;border-radius:12px 12px 0 0;font-weight:600">ABV-IIITM Alumni Connect</td></tr>
            <tr><td style="padding:28px;line-height:1.6;font-size:15px">
                <p>Dear {{ $name }},</p>
                {{-- Sanitised Markdown output (Campaign::bodyHtml strips raw HTML and unsafe links). --}}
                {!! $html !!}
            </td></tr>
            <tr><td style="padding:16px 28px;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b">
                You receive this because you opted in to alumni communications.
                <a href="{{ $unsubscribeUrl }}" style="color:#64748b">Unsubscribe</a>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
