<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;background:#f3f8ff;font-family:Segoe UI,Arial,sans-serif;color:#14213d;">
    <div style="padding:32px 16px;">
        <div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #d7e5fb;border-radius:24px;box-shadow:0 24px 60px rgba(20,33,61,0.08);overflow:hidden;">
            <div style="padding:28px 28px 20px;background:linear-gradient(135deg,#eef6ff 0%,#ffffff 100%);border-bottom:1px solid #e2ebfb;">
                <div style="font-size:12px;letter-spacing:0.28em;text-transform:uppercase;color:#5e759f;font-weight:700;">Ticket Update</div>
                <h1 style="margin:12px 0 8px;font-size:28px;line-height:1.2;color:#14213d;">{{ $title }}</h1>
                <p style="margin:0;font-size:15px;line-height:1.7;color:#4a6288;">{{ $emailMessage }}</p>
            </div>

            <div style="padding:28px;">
                <table role="presentation" style="width:100%;border-collapse:separate;border-spacing:0 12px;">
                    <tr>
                        <td style="width:180px;font-size:13px;font-weight:700;color:#5e759f;">Ticket Number</td>
                        <td style="font-size:14px;color:#14213d;">{{ $ticket->ticket_number }}</td>
                    </tr>
                    <tr>
                        <td style="font-size:13px;font-weight:700;color:#5e759f;">Subject</td>
                        <td style="font-size:14px;color:#14213d;">{{ $ticket->subject }}</td>
                    </tr>
                    <tr>
                        <td style="font-size:13px;font-weight:700;color:#5e759f;">Status</td>
                        <td style="font-size:14px;color:#14213d;">{{ str($ticket->status)->headline() }}</td>
                    </tr>
                    <tr>
                        <td style="font-size:13px;font-weight:700;color:#5e759f;">Priority</td>
                        <td style="font-size:14px;color:#14213d;">{{ str($ticket->priority)->headline() }}</td>
                    </tr>
                    @if($ticket->team?->name)
                        <tr>
                            <td style="font-size:13px;font-weight:700;color:#5e759f;">Project</td>
                            <td style="font-size:14px;color:#14213d;">{{ $ticket->team->name }}</td>
                        </tr>
                    @endif
                    @if(!empty($data['comment_body']))
                        <tr>
                            <td style="font-size:13px;font-weight:700;color:#5e759f;">Latest Comment</td>
                            <td style="font-size:14px;line-height:1.7;color:#14213d;">{{ $data['comment_body'] }}</td>
                        </tr>
                    @endif
                </table>

                <div style="margin-top:20px;">
                    <a href="{{ route('tickets.show', $ticket) }}" style="display:inline-block;padding:12px 18px;border-radius:999px;background:#3d5af1;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;">
                        Open Ticket
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

