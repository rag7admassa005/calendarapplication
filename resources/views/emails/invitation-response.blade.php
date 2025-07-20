@component('mail::message')
# طلب موعد جماعي جديد

مرحباً،  
المستخدم **{{ $user->first_name }} {{ $user->last_name }}** قام بطلب موعد جماعي معك.

**تفاصيل الموعد:**
- التاريخ: {{ $appointmentRequest->preferred_date }}
- من الساعة: {{ $appointmentRequest->preferred_start_time }} إلى {{ $appointmentRequest->preferred_end_time }}
- المدة: {{ $appointmentRequest->preferred_duration }} دقيقة
- السبب: {{ $appointmentRequest->reason ?? 'لا يوجد' }}
- عدد المستخدمين المدعوين: {{ count(json_decode($appointmentRequest->invited_users_data)) }}

@component('mail::button', ['url' => url('/manager/appointment-requests/'.$appointmentRequest->id)])
عرض الطلب
@endcomponent

شكراً لك،  
{{ config('app.name') }}
@endcomponent
