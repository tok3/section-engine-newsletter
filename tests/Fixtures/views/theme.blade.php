<!doctype html>
<html lang="{{ $locale }}">
<head>
    <meta name="robots" content="{{ $seo['robots'] }}">
    <title>{{ $seo['title'] }}</title>
</head>
<body data-test-theme-layout>
    <main>{!! $content !!}</main>
</body>
</html>
