<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
{{--
    The single shared layout every notification type renders through
    (see App\Mail\LoyaltyNotificationMail) — {{ $bodyHtml }} is already
    sanitized by NotificationTemplateService::sanitize() before it ever
    reaches this view, so it's safe to output unescaped here; nothing
    else on this page renders merchant- or customer-supplied content.
--}}
<body style="margin:0;padding:0;background-color:#f4f5f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f7;padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="padding:32px;color:#202223;font-size:15px;line-height:1.6;">
                            {!! $bodyHtml !!}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
