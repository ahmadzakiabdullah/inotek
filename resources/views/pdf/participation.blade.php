<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate of Participation</title>
    <style>
        @page {
            margin: 0;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            background-color: #f8fafc;
            color: #1f2937;
            margin: 0;
            padding: 0;
        }
        .certificate-container {
            width: 297mm;
            height: 210mm;
            padding: 20mm;
            box-sizing: border-box;
            position: relative;
            background-color: #ffffff;
            border: 15px solid #0f172a;
        }
        .inner-border {
            border: 2px solid #0d9488;
            height: 100%;
            width: 100%;
            box-sizing: border-box;
            padding: 20px;
            position: relative;
        }
        .header {
            text-align: center;
            margin-top: 10px;
        }
        .logo-text {
            font-size: 24px;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: 2px;
            margin-bottom: 5px;
        }
        .subtitle {
            font-size: 11px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 3px;
        }
        .title {
            text-align: center;
            font-size: 38px;
            font-weight: 700;
            color: #0d9488;
            margin: 25px 0 10px 0;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .presented-to {
            text-align: center;
            font-size: 14px;
            color: #4b5563;
            margin-top: 20px;
            font-style: italic;
        }
        .name {
            text-align: center;
            font-size: 26px;
            font-weight: bold;
            color: #0f172a;
            border-bottom: 2px solid #e5e7eb;
            width: 70%;
            margin: 15px auto;
            padding-bottom: 8px;
        }
        .team-members {
            text-align: center;
            font-size: 11px;
            color: #4b5563;
            max-width: 80%;
            margin: 5px auto 20px auto;
            line-height: 1.5;
        }
        .reason {
            text-align: center;
            font-size: 13px;
            color: #4b5563;
            line-height: 1.6;
            margin: 20px auto;
            max-width: 85%;
        }
        .project-title {
            font-weight: bold;
            color: #0f172a;
            font-style: italic;
            font-size: 15px;
        }
        .footer-table {
            width: 100%;
            position: absolute;
            bottom: 30px;
            left: 20px;
            right: 20px;
            border-collapse: collapse;
        }
        .footer-col {
            width: 33.33%;
            vertical-align: bottom;
            text-align: center;
        }
        .signature-line {
            width: 200px;
            border-top: 1px solid #9ca3af;
            margin: 0 auto 5px auto;
        }
        .signature-title {
            font-size: 11px;
            color: #6b7280;
            font-weight: 500;
        }
        .qr-code {
            display: block;
            margin: 0 auto;
            border: 1px solid #e5e7eb;
            padding: 3px;
            background-color: #fff;
        }
        .qr-text {
            font-size: 9px;
            color: #9ca3af;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <div class="certificate-container">
        <div class="inner-border">
            <div class="header">
                <div class="logo-text">INOTEK INNOVATION EXHIBITION</div>
                <div class="subtitle">Official Participation Recognition</div>
            </div>

            <div class="title">Certificate of Participation</div>

            <div class="presented-to">This certificate is proudly awarded to</div>
            
            <div class="name">{{ $project->user->name }}</div>

            @if($project->teamMembers && $project->teamMembers->count() > 0)
                <div class="team-members">
                    <strong>Team Members:</strong> 
                    {{ implode(', ', $project->teamMembers->pluck('name')->toArray()) }}
                </div>
            @else
                <div style="height: 15px;"></div>
            @endif

            <div class="reason">
                for successfully submitting and presenting the project titled <br>
                <span class="project-title">"{{ $project->title }}"</span> <br>
                under the category of <strong>{{ $project->category->name }}</strong> during 
                <strong>{{ $project->session->name }}</strong>.
            </div>

            <table class="footer-table">
                <tr>
                    <td class="footer-col" style="text-align: left; padding-left: 20px;">
                        <div class="signature-line"></div>
                        <div class="signature-title">INOTEK Committee Chair</div>
                        <div style="font-size: 10px; color: #9ca3af; margin-top: 2px;">Date: {{ $date }}</div>
                    </td>
                    <td class="footer-col">
                        @if($qrCodeData)
                            <img class="qr-code" src="data:image/png;base64,{{ $qrCodeData }}" width="80" height="80" alt="Verification QR Code">
                            <div class="qr-text">Scan to Verify Authenticity</div>
                        @endif
                    </td>
                    <td class="footer-col" style="text-align: right; padding-right: 20px;">
                        <div class="signature-line" style="margin-left: auto; margin-right: 0;"></div>
                        <div class="signature-title" style="text-align: right; padding-right: 15px;">Official Secretariat</div>
                        <div style="font-size: 10px; color: #9ca3af; margin-top: 2px; text-align: right; padding-right: 15px;">INOTEK Registry</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
