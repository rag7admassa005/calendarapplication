<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>طلب موعد جماعي</title>
</head>
<body>
    <p>مرحباً،</p>

    <p>المستخدم <strong>{{ $user->first_name }} {{ $user->last_name }}</strong> قام بطلب موعد جماعي معك.</p>

    <p><strong>تفاصيل الموعد:</strong></p>
    <ul>
        <li>التاريخ: {{ $appointmentRequest->preferred_date }}</li>
        <li>من الساعة: {{ $appointmentRequest->preferred_start_time }} إلى {{ $appointmentRequest->preferred_end_time }}</li>
        <li>المدة: {{ $appointmentRequest->preferred_duration }} دقيقة</li>
       <li>
    عدد المستخدمين المدعوين:
    {{
        is_array(json_decode($appointmentRequest->invited_users_data))
            ? count(json_decode($appointmentRequest->invited_users_data))
            : 0
    }}
</li>

    </ul>

    <p>
        لمراجعة الطلب، يرجى الضغط على الرابط التالي:<br>
        <a href="{{ url('/manager/appointment-requests/' . $appointmentRequest->id) }}">
            عرض الطلب
        </a>
    </p>

    <p>شكراً لك،<br>
    {{ config('app.name') }}</p>
</body>
</html>
