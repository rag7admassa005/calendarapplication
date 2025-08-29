<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f9f9f9;
            color: #333;
            padding: 20px;
        }
        .container {
            background: #fff;
            border-radius: 10px;
            padding: 25px;
            max-width: 600px;
            margin: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 {
            color: #e74c3c;
        }
        .details {
            margin: 20px 0;
            font-size: 16px;
        }
        .footer {
            margin-top: 25px;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Appointment Rejected ❌</h1>

        <p>Hello {{ $appointment->user->name }},</p>

        <div class="details">
            Unfortunately, your appointment scheduled on  
            <strong>{{ $appointment->preferred_date }} at {{ $appointment->preferred_start_time }}</strong>  
            has been rejected by {{ $rejectedBy->name }}.
        </div>

        <p>You may request another appointment at a different time.<br>
        If you need assistance, please contact us.</p>

        <div class="footer">
            Thanks,<br>
            {{ config('app.name') }}
        </div>
    </div>
</body>
</html>
