<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Appointment Cancelled</title>
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
        <h1>Appointment Cancelled ❌</h1>

        <p>Hello {{ $appointment->user->first_name ?? 'User' }},</p>

        <p>The appointment you are a participant in <strong>{{ $appointment->preferred_date }}</strong> 
           at <strong>{{ $appointment->preferred_start_time }}</strong> 
           has been cancelled by <strong>{{ $cancelledBy->first_name ?? 'the user' }}</strong>.</p>

        @if($reason)
        <div class="details">
            <strong>Reason for cancellation:</strong> {{ $reason }}
        </div>
        @endif

        <p>If you have any questions, please contact the manager directly.</p>

        <div class="footer">
            Thanks,<br>
            {{ config('app.name') }}
        </div>
    </div>
</body>
</html>
