<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,sans-serif;color:#0f172a;">
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f8fafc;padding:24px 0;">
    <tr>
        <td align="center">
            <table role="presentation" cellpadding="0" cellspacing="0" width="640" style="max-width:640px;width:100%;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
                <tr>
                    <td style="background:#1e3a8a;color:#ffffff;padding:16px 20px;font-size:18px;font-weight:700;">
                        Attributa - Notificação de Lead
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px;">
                        <h1 style="margin:0 0 10px;font-size:20px;line-height:1.3;">{{ $title }}</h1>
                        <p style="margin:0 0 12px;font-size:14px;line-height:1.6;color:#334155;">
                            {{ $messageText }}
                        </p>
                        @if(!empty($createdAt))
                            <p style="margin:0 0 16px;font-size:12px;color:#64748b;">
                                Enviado em: {{ $createdAt }}
                            </p>
                        @endif

                        <div style="margin-top:8px;">
                            <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#64748b;margin-bottom:8px;">
                                Resumo
                            </div>
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
                                @if(!empty($payload['campaign_name']))
                                    <tr>
                                        <td style="border:1px solid #e2e8f0;padding:8px;font-size:12px;font-weight:700;width:35%;background:#f8fafc;">
                                            Campanha
                                        </td>
                                        <td style="border:1px solid #e2e8f0;padding:8px;font-size:12px;">
                                            {{ $payload['campaign_name'] }}
                                        </td>
                                    </tr>
                                @endif
                                @if(!empty($payload['campaign_code']))
                                    <tr>
                                        <td style="border:1px solid #e2e8f0;padding:8px;font-size:12px;font-weight:700;width:35%;background:#f8fafc;">
                                            Código da campanha
                                        </td>
                                        <td style="border:1px solid #e2e8f0;padding:8px;font-size:12px;">
                                            {{ $payload['campaign_code'] }}
                                        </td>
                                    </tr>
                                @endif
                                @if(!empty($payload['lead_status_label']))
                                    <tr>
                                        <td style="border:1px solid #e2e8f0;padding:8px;font-size:12px;font-weight:700;width:35%;background:#f8fafc;">
                                            Status do lead
                                        </td>
                                        <td style="border:1px solid #e2e8f0;padding:8px;font-size:12px;">
                                            {{ $payload['lead_status_label'] }}
                                        </td>
                                    </tr>
                                @endif
                                @if(!empty($payload['platform_lead_id']))
                                    <tr>
                                        <td style="border:1px solid #e2e8f0;padding:8px;font-size:12px;font-weight:700;width:35%;background:#f8fafc;">
                                            ID na plataforma
                                        </td>
                                        <td style="border:1px solid #e2e8f0;padding:8px;font-size:12px;">
                                            {{ $payload['platform_lead_id'] }}
                                        </td>
                                    </tr>
                                @endif
                                @if(!empty($payload['payout_amount']) && !empty($payload['currency_code']))
                                    <tr>
                                        <td style="border:1px solid #e2e8f0;padding:8px;font-size:12px;font-weight:700;width:35%;background:#f8fafc;">
                                            Payout
                                        </td>
                                        <td style="border:1px solid #e2e8f0;padding:8px;font-size:12px;">
                                            {{ $payload['payout_amount'] }} {{ $payload['currency_code'] }}
                                        </td>
                                    </tr>
                                @endif
                            </table>
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
