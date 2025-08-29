<h2>Hello {{ $assistant->user->name }},</h2>

<p>Your permissions have been modified by your administrator.</p>

<p>New permissions:</p>
<ul>
@foreach ($permissions as $permission)
<li>{{ $permission }}</li>
@endforeach
</ul>

<p>Please check your control panel for more details.</p>