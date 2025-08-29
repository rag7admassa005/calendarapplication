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
        <h1>Appointment Approved ✅</h1>

        <p>Hello </p>

        <p>We are pleased to inform you that your appointment has been <strong>approved</strong>.</p>

        <div class="details">
            📅 <strong>Date:</strong> {{ $appointment->date }} <br>
            ⏰ <strong>Time:</strong> {{ $appointment->start_time }}
        </div>

        <p>Please make sure to be available on time.<br>
        If you have any questions, feel free to contact us.</p>

        <div class="footer">
            Thanks,<br>
            {{ config('app.name') }}
        </div>
    </div>
</body>
</html>
