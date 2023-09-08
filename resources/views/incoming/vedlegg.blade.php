@php
    use Illuminate\Support\{Str};
@endphp
<h2>{{ $subject }}</h2>
<ul>
@foreach ($attachments as $filepath)
    <li>{{ Str::afterLast($filepath, '/') }}</li>

@endforeach
</ul>
