<p>مرحباً {{ $managerName }},</p>

<p>قام المستخدم <strong>{{ $userName }}</strong> بإلغاء الموعد الذي كان بتاريخ:</p>

<ul>
    <li>التاريخ: {{ $date }}</li>
    <li>من: {{ $start }} إلى {{ $end }}</li>
</ul>

<p><strong>سبب الإلغاء:</strong> {{ $reason }}</p>

<p>مع تحياتنا،</p>
<p>نظام المواعيد</p>