<h2>{{ $subject }}</h2>
<ul>
@foreach ($attachments as $filepath)
    <li>{{ basepath($filepath) }}</li>

@endforeach
</ul>
