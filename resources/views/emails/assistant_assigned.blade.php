<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<title>You have been assigned as an assistant</title>
</head>
<body>
<h2>Hello {{ $user->name }}</h2>
<p>Your administrator <strong>{{ $manager->name }}</strong> has assigned you as an assistant in the system.</p>
<p>You can now log in and start using your privileges.</p>
<p>Regards,</p>
<p>Support Team</p>
</body>
</html>