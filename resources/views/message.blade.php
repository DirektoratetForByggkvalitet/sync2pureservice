<!DOCTYPE html>
<html>
<head>
    <meta name="description" content="DiBK e-postmal v2.3.230530" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&display=swap" rel="stylesheet" />
    <style type="text/css">
        .bottom,.box{position:relative;left:0;right:0}body,code a,html{font-size:11pt;font-family:'Open Sans',Calibri,Arial,sans-serif}p{margin:0 0 1em}table{border-collapse:collapse;border-spacing:0}td{padding:5px;border:1px solid #ddd}h1,h2,h3,h4,h5,h6{margin-top:.5em;margin-bottom:.3em;font-weight:600}blockquote,div.sheet>div:first-of-type{background:#f2f1f0;padding:.5em;font-style:normal;margin:0 0 .5em}code a,code a:any-link{background-color:#072938;border:0;display:inline-block;cursor:pointer;color:#fff!important;font-weight:600;padding:8px;text-decoration:none;margin:0}.box{background-color:#ecf2f6;display:flex;flex-flow:column;top:0;padding:0;margin:0}.sheet{background-color:#fff;padding:1em 2vw;flex:1}.logo{margin:1.2em 0;padding-left:2vw}img#logo{max-width:100px;max-height:121px;width:25vw;height:auto}.sheet>h4:first-of-type{position:absolute;top:100px;right:0;display:block;padding-right:1em;font-weight:700}.bottom{background-color:#c9d12b;height:4px;top:relative}
    </style>
    <title>{{ $title }}</title>
</head>
<body>
<div class="box">
    <div class="logo">
        <img id="logo" alt="Logoen til Direktoratet for byggkvalitet" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGQAAAB5CAYAAADPqoQRAAAFVmlUWHRYTUw6Y29tLmFkb2JlLnhtcAAAAAAAPD94cGFja2V0IGJlZ2luPSLvu78iIGlkPSJXNU0wTXBDZWhpSHpyZVN6TlRjemtjOWQiPz4KPHg6eG1wbWV0YSB4bWxuczp4PSJhZG9iZTpuczptZXRhLyIgeDp4bXB0az0iWE1QIENvcmUgNS41LjAiPgogPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4KICA8cmRmOkRlc2NyaXB0aW9uIHJkZjphYm91dD0iIgogICAgeG1sbnM6ZGM9Imh0dHA6Ly9wdXJsLm9yZy9kYy9lbGVtZW50cy8xLjEvIgogICAgeG1sbnM6ZXhpZj0iaHR0cDovL25zLmFkb2JlLmNvbS9leGlmLzEuMC8iCiAgICB4bWxuczp0aWZmPSJodHRwOi8vbnMuYWRvYmUuY29tL3RpZmYvMS4wLyIKICAgIHhtbG5zOnBob3Rvc2hvcD0iaHR0cDovL25zLmFkb2JlLmNvbS9waG90b3Nob3AvMS4wLyIKICAgIHhtbG5zOnhtcD0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wLyIKICAgIHhtbG5zOnhtcE1NPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvbW0vIgogICAgeG1sbnM6c3RFdnQ9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZUV2ZW50IyIKICAgZXhpZjpQaXhlbFhEaW1lbnNpb249IjEwMCIKICAgZXhpZjpQaXhlbFlEaW1lbnNpb249IjEyMSIKICAgZXhpZjpDb2xvclNwYWNlPSIxIgogICB0aWZmOkltYWdlV2lkdGg9IjEwMCIKICAgdGlmZjpJbWFnZUxlbmd0aD0iMTIxIgogICB0aWZmOlJlc29sdXRpb25Vbml0PSIyIgogICB0aWZmOlhSZXNvbHV0aW9uPSI5Ni8xIgogICB0aWZmOllSZXNvbHV0aW9uPSI5Ni8xIgogICBwaG90b3Nob3A6Q29sb3JNb2RlPSIzIgogICBwaG90b3Nob3A6SUNDUHJv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" />
    </div>
</div>
<div class="sheet">
{!! $content !!}
</div>

    <div class="bottom"></div>
</div>

</body>
</html>
