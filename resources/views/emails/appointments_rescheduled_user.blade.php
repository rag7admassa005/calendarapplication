<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Appointment Rescheduled</title>
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
            color: #2d8fdd;
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
        <h1>Appointment Rescheduled 🔄</h1>

        <p>Hello,</p>

        <p>The appointment you are a participant in has been <strong>rescheduled</strong> by <strong>{{ $rescheduledBy->first_name ?? 'the user' }}</strong>.</p>

        <div class="details">
            <strong>New Date:</strong> {{ $appointment->preferred_date }}<br>
            <strong>New Time:</strong> {{ $appointment->preferred_start_time }} - {{ $appointment->preferred_end_time }}<br>
            <strong>Duration:</strong> {{ $appointment->preferred_duration }} minutes
        </div>

        <p>Please make sure to be available at the new time. If you have any questions, contact the manager directly.</p>

        <div class="footer">
            Thanks,<br>
            {{ config('app.name') }}
        </div>
    </div>
</body>
</html>
