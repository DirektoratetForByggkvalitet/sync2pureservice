@php
    use Illuminate\Support\{Str};
    use App\Services\Tools;
@endphp
<h2>{{ $subject }}</h2>
<ul>
@foreach ($attachments as $item)

    <li>{{ $item['fileName'] }} ({{ Tools::human_filesize($item['contentLength']) }})</li>

@endforeach
</ul>
