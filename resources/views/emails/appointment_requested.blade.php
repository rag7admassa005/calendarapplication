<p>مرحباً {{ $appointment->manager->first_name ?? 'المدير' }},</p>

<p>قام المستخدم <strong>{{ $user->first_name }} {{ $user->last_name }}</strong> بطلب موعد جديد.</p>

<p>تفاصيل الموعد:</p>

<ul>
    <li><strong>التاريخ:</strong> {{ $appointment->preferred_date }}</li>
    <li><strong>الوقت:</strong> من {{ $appointment->preferred_start_time }} إلى {{ $appointment->preferred_end_time }}</li>
    <li><strong>المدة:</strong> {{ $appointment->preferred_duration }} دقيقة</li>
    <li><strong>السبب:</strong> {{ $appointment->reason }}</li>
</ul>

<p>يرجى مراجعة الطلب في أقرب وقت ممكن.</p>

<p>مع التحية،<br>
فريق النظام</p>