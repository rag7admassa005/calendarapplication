<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Verification Code</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2>Hello {{ $user_name }},</h2>
    <p>Here is your verification code:</p>
    <h1 style="color: #2d89ef;">{{ $code }}</h1>
    <p>Please use this code to verify your account.</p>
</body>
</html>
