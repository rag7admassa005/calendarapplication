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
        .details li {
            margin-bottom: 8px;
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
        <h1>New Appointment Request 📩</h1>

        <p>Hello {{ $appointment->manager->first_name ?? 'Manager' }},</p>

        <p>The user <strong>{{ $user->first_name }} {{ $user->last_name }}</strong> has requested a new appointment.</p>

        <div class="details">
            <p><strong>Appointment Details:</strong></p>
            <ul>
                <li><strong>Date:</strong> {{ $appointment->preferred_date }}</li>
                <li><strong>Time:</strong> From {{ $appointment->preferred_start_time }} to {{ $appointment->preferred_end_time }}</li>
                <li><strong>Duration:</strong> {{ $appointment->preferred_duration }} minutes</li>
                <li><strong>Reason:</strong> {{ $appointment->reason ?? 'No reason provided' }}</li>
            </ul>
        </div>

        <p>Please review this request at your earliest convenience.</p>

        <div class="footer">
            Thanks,<br>
            The System Team
        </div>
    </div>
</body>
</html>
