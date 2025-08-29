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
        <h1>You're Invited 📩</h1>

        <p>Hello,</p>

        <p>You have been invited to an appointment by <strong>{{ $manager->name }}</strong>.</p>

        <div class="details">
            <strong>Title:</strong> {{ $invitation->title }} <br>
            <strong>Description:</strong> {{ $invitation->description }} <br>
            <strong>Date:</strong> {{ $invitation->date }} <br>
            <strong>Time:</strong> {{ $invitation->time }} <br>
            <strong>Duration:</strong> {{ $invitation->duration }} minutes
        </div>

        <p>Please make sure to be available at the scheduled time.<br>
        If you have any questions, feel free to contact us.</p>

        <div class="footer">
            Thanks,<br>
            {{ config('app.name') }}
        </div>
    </div>
</body>
</html>
