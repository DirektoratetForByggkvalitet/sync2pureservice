@php
    use Illuminate\Support\{Str};
@endphp
<h2>{{ $subject }}</h2>
<ul>
@foreach ($attachments as $filepath)

    @php
        $filename = Str::afterLast($filepath, '/');
        // Hopper over arkivmelding.xml
        if ($filename == 'arkivmelding.xml') continue;
    @endphp

    <li>{{ $filename }}</li>

@endforeach
</ul>
