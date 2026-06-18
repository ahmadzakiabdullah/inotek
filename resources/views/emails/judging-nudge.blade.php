<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Pending Evaluations Reminder</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333; margin: 0; padding: 20px; background-color: #f4f4f7;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
        <tr>
            <td style="background-color: #0f172a; padding: 25px; text-align: center; color: #ffffff;">
                <h2 style="margin: 0; font-size: 20px; letter-spacing: 1px;">INOTEK INNOVATION EXHIBITION</h2>
            </td>
        </tr>
        <tr>
            <td style="padding: 30px;">
                <p style="margin-top: 0; font-size: 16px;">Dear <strong>{{ $judge->name }}</strong>,</p>
                <p>This is a friendly reminder from the INOTEK Committee regarding the evaluations assigned to you for the current active session.</p>
                <p>According to our records, you still have <strong>{{ $pendingProjects->count() }}</strong> project(s) pending your score evaluation. Please complete your appraisals at your earliest convenience to help us finalize the competition results.</p>
                
                <h3 style="color: #0d9488; border-bottom: 2px solid #f1f5f9; padding-bottom: 8px; margin-top: 25px;">Pending Appraisals Checklist:</h3>
                <ul style="padding-left: 20px; margin-bottom: 25px;">
                    @foreach($pendingProjects as $proj)
                        <li style="margin-bottom: 10px; font-size: 14px;">
                            <strong>[{{ $proj['pcode'] ?? 'No Code' }}] {{ $proj['title'] }}</strong> <br>
                            <span style="color: #64748b; font-size: 12px;">Category: {{ $proj['category_name'] }} | Round {{ $proj['round_no'] }}</span>
                        </li>
                    @endforeach
                </ul>

                <p style="text-align: center; margin: 30px 0 20px 0;">
                    <a href="{{ route('home') }}/judge/evaluations" style="background-color: #0d9488; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: bold; display: inline-block;">Go to Judge Portal</a>
                </p>

                <p style="font-size: 13px; color: #64748b; margin-top: 35px; border-top: 1px solid #f1f5f9; padding-top: 15px;">
                    If you have already submitted these scores or believe this is an error, please ignore this email or contact the admin panel.
                </p>
                <p style="font-size: 13px; color: #64748b; margin-bottom: 0;">
                    Best regards,<br>
                    <strong>INOTEK Innovation Secretariat</strong>
                </p>
            </td>
        </tr>
        <tr>
            <td style="background-color: #f8fafc; padding: 15px; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0;">
                This is an automated system reminder. Please do not reply directly to this email.
            </td>
        </tr>
    </table>
</body>
</html>
