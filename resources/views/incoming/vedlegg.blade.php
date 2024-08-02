@php
    use Illuminate\Support\{Str};
    use App\Services\Tools;
    use Illuminate\Support\Facades\{Storage};
@endphp
<h2>{{ $subject }}</h2>
<ul>
@foreach ($attachments as $file)

    <li>{{ basename($file) }} ({{ Tools::human_filesize(Storage::filesize($file)) }})</li>

@endforeach
</ul>
