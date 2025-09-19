<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Usuarios</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .summary {
            background: #f5f5f5;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }

    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte de Usuarios</h1>
        <p>Generado el: {{ $generated_at->format('d/m/Y H:i:s') }}</p>
        <p>Por: {{ $generated_by }}</p>
    </div>

    <div class="summary">
        <h3>Resumen</h3>
        <p><strong>Total de usuarios:</strong> {{ $summary['total_users'] }}</p>
        <h4>Usuarios por rol:</h4>
        <ul>
            @foreach($summary['by_role'] as $role => $count)
            <li><strong>{{ $role }}:</strong> {{ $count }}</li>
            @endforeach
        </ul>
        <h4>Clientes por categoría:</h4>
        <ul>
            @foreach($summary['by_category'] as $category => $count)
            <li><strong>Categoría {{ $category }}:</strong> {{ $count }}</li>
            @endforeach
        </ul>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Email</th>
                <th>Teléfono</th>
                <th>CI</th>
                <th>Roles</th>
                <th>Categoría</th>
                <th>Fecha Registro</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
            <tr>
                <td>{{ $user->id }}</td>
                <td>{{ $user->name }}</td>
                <td>{{ $user->email ?? '-' }}</td>
                <td>{{ $user->phone ?? '-' }}</td>
                <td>{{ $user->ci ?? '-' }}</td>
                <td>{{ $user->roles->pluck('name')->join(', ') }}</td>
                <td>{{ $user->client_category ?? '-' }}</td>
                <td>{{ $user->created_at->format('d/m/Y') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Reporte generado por el Sistema de Cobrador</p>
    </div>
</body>
</html>
