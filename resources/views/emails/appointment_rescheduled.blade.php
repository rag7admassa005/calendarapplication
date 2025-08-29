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
            color: #f39c12;
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

        <p>Hello </p>

        <div class="details">
            Your appointment has been rescheduled by the manager.  
            The new time is:  
            <strong>{{ $appointment->date }} at {{ $appointment->start_time }}</strong>.
        </div>

        <p>Please confirm your availability for this new time.<br>
        If you have any questions, feel free to contact us.</p>

        <div class="footer">
            Thanks,<br>
            {{ config('app.name') }}
        </div>
    </div>
</body>
</html>
