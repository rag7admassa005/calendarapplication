<h2>مرحباً {{ $assistant->user->name }}،</h2>

<p>تم تعديل صلاحياتك من قبل المدير.</p>

<p>الصلاحيات الجديدة:</p>
<ul>
  @foreach ($permissions as $permission)
    <li>{{ $permission }}</li>
  @endforeach
</ul>

<p>يرجى مراجعة لوحة التحكم الخاصة بك لمزيد من التفاصيل.</p>
