<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f9f9f9; color: #333; padding: 20px; }
        .container { background: #fff; border-radius: 10px; padding: 25px; max-width: 600px; margin: auto; box-shadow: 0 2px 8px rgba(0,0,0,0.1);}
        h1 { color: #2d8fdd; }
        .details { margin: 20px 0; font-size: 16px; }
        .footer { margin-top: 25px; font-size: 14px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Appointment Request {{ ucfirst($status) }} </h1>

        <p>Hello {{ $appointmentRequest->user->name }},</p>

        <p>{{ $participantName }} has <strong>{{ $status }}</strong> your appointment request.</p>

        <div class="details">
            Preferred Date: <strong>{{ $appointmentRequest->preferred_date }}</strong><br>
            Preferred Time: <strong>{{ $appointmentRequest->preferred_start_time }}</strong><br>
            Reason: {{ $appointmentRequest->reason ?? 'No reason provided' }}
        </div>

        <div class="footer">
            Thanks,<br>
            {{ config('app.name') }}
        </div>
    </div>
</body>
</html>
