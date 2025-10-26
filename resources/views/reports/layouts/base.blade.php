<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Reporte' }} - Sistema de Cobrador</title>

    @include('reports.layouts.styles')

    @yield('additional-styles')
</head>
<body>
    <main class="report-container">
        @yield('content')
    </main>
</body>
</html>
