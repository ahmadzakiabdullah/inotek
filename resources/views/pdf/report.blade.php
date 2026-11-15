<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $report->title }}</title>
    <style>
        @page { margin: 14mm 12mm 16mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5px; color: #1f2937; }
        h1 { font-size: 15px; margin: 0 0 2px; color: #0f172a; }
        .meta { font-size: 9px; color: #475569; margin-bottom: 8px; }
        .note { border: 1px solid #f59e0b; background: #fffbeb; color: #92400e; padding: 5px 7px; margin-bottom: 8px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { background: #0f172a; color: #ffffff; text-align: left; padding: 4px 5px; font-size: 8px; }
        td { border-bottom: 1px solid #e2e8f0; padding: 3px 5px; vertical-align: top; word-wrap: break-word; }
        tbody tr:nth-child(even) td { background: #f8fafc; }
        .empty { text-align: center; padding: 16px; color: #64748b; }
        .footer { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7.5px; color: #64748b; }
    </style>
</head>
<body>
    <h1>{{ $report->title }}</h1>
    <div class="meta">
        Session: <strong>{{ $report->sessionName }}</strong>
        &nbsp;·&nbsp; Generated: {{ $generatedAt }}
        &nbsp;·&nbsp; Rows: {{ count($report->rows) }}
    </div>

    @if ($report->note)
        <div class="note">{{ $report->note }}</div>
    @endif

    <table>
        <thead>
            <tr>
                @foreach ($report->columns as $column)
                    <th>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($report->rows as $row)
                <tr>
                    @foreach ($row as $value)
                        <td>{{ $value ?? '-' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td class="empty" colspan="{{ count($report->columns) }}">No records for this session.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">INOTEK · Universiti Teknikal Malaysia Melaka · Confidential: for official competition use only.</div>
</body>
</html>
