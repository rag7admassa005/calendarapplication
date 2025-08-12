<p>مرحباً {{ $assistant->user->first_name ?? 'المساعد' }},</p>

<p>تم تعيينك كمساعد من قبل المدير <strong>{{ $assistant->manager->first_name }} {{ $assistant->manager->last_name }}</strong>.</p>

<p>الصلاحيات التي تم منحها لك:</p>

<ul>
    @foreach ($permissions as $permission)
        <li><strong>{{ $permission }}</strong></li>
    @endforeach
</ul>

<p>يرجى استخدام هذه الصلاحيات ضمن النظام وفقاً للمهام الموكلة إليك.</p>

<p>مع التحية،<br>
فريق النظام</p>
