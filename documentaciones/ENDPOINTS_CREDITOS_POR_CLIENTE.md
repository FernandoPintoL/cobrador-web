# Endpoints para Consultar y Filtrar Créditos de un Cliente

Fecha: 2025-08-24 19:16

Este documento explica cómo listar todos los créditos de un cliente y cómo aplicar filtros útiles (estado, búsqueda, cobrador). Existen dos enfoques:
- Enfoque simple (sin filtros): GET /api/credits/client/{client}
- Enfoque flexible (con filtros): GET /api/credits?client_id={client_id}&...

Todos los endpoints requieren autenticación con Laravel Sanctum.

Autenticación:
- Header: Authorization: Bearer {token}
- Obtener token: POST /api/login

---

1) Enfoque simple: Créditos por cliente (sin filtros)
- Método: GET /api/credits/client/{client}
- Controlador: CreditController@getByClient
- Descripción: Devuelve todos los créditos del cliente indicado. Incluye relaciones payments y createdBy.

Parámetros de ruta:
- client (int): ID del usuario con rol "client".

Respuesta (200): Lista de créditos del cliente.
Campos destacados por crédito:
- id, amount, total_amount, balance, status, frequency, start_date, end_date, scheduled_delivery_date
- Relaciones: payments[], created_by

Ejemplo cURL:
```
curl -X GET "http://localhost/api/credits/client/123" \
     -H "Accept: application/json" \
     -H "Authorization: Bearer ${TOKEN}"
```

Notas:
- Este endpoint no acepta filtros. Para filtrar por estado, búsqueda, etc., usar el enfoque flexible.

---

2) Enfoque flexible: Listado de créditos con filtros (incluye por cliente)
- Método: GET /api/credits
- Controlador: CreditController@index
- Descripción: Devuelve créditos paginados con múltiples filtros. Para limitar a un cliente específico, usar client_id.

Parámetros de query soportados:
- client_id (int): ID del cliente para limitar los resultados a sus créditos.
- status (string): Filtra por estado. Valores posibles: pending_approval, waiting_delivery, active, completed, defaulted, cancelled, rejected (si aplica en datos).
- search (string): Búsqueda textual por nombre del cliente (coincidencia parcial).
- cobrador_id (int): Filtrar por cobrador asignado al cliente (SOLO visible para admin/manager). Para cobradores autenticados, ya se limita automáticamente a sus clientes.
- page (int): Página de resultados (paginado LengthAware).
- per_page (int): Tamaño de página (por defecto 15).

Respuesta (200): Paginada con estructura estándar de Laravel: data, current_page, per_page, total, etc. Incluye relaciones client, payments, createdBy en cada crédito.

Ejemplos de uso:

A) Todos los créditos de un cliente específico (paginado por defecto)
```
curl -X GET "http://localhost/api/credits?client_id=123" \
     -H "Accept: application/json" \
     -H "Authorization: Bearer ${TOKEN}"
```

B) Créditos de un cliente por estado (por ejemplo, activos)
```
curl -X GET "http://localhost/api/credits?client_id=123&status=active" \
     -H "Accept: application/json" \
     -H "Authorization: Bearer ${TOKEN}"
```

C) Créditos de un cliente con búsqueda adicional por nombre del cliente (útil cuando listamos varios clientes)
```
curl -X GET "http://localhost/api/credits?client_id=123&search=ana" \
     -H "Accept: application/json" \
     -H "Authorization: Bearer ${TOKEN}"
```

D) Filtrar por cobrador (solo admin/manager). Ejemplo: todos los créditos de clientes atendidos por el cobrador 45
```
curl -X GET "http://localhost/api/credits?client_id=123&cobrador_id=45" \
     -H "Accept: application/json" \
     -H "Authorization: Bearer ${TOKEN}"
```

E) Paginación personalizada
```
curl -X GET "http://localhost/api/credits?client_id=123&status=active&per_page=50&page=2" \
     -H "Accept: application/json" \
     -H "Authorization: Bearer ${TOKEN}"
```

---

Campos relevantes del crédito devuelto (resumen):
- amount: Monto original sin intereses.
- interest_rate: Tasa de interés aplicada (%). También puede existir interest_rate_id.
- total_amount: Monto total (monto + interés).
- balance: Saldo pendiente actual.
- installment_amount, total_installments: Monto y cantidad de cuotas.
- frequency: Frecuencia de pagos (daily, weekly, biweekly, monthly).
- start_date: Inicio del cronograma de cuotas.
- end_date: Fin estimado del cronograma.
- status: Estado del crédito.
- scheduled_delivery_date: Fecha programada de entrega.
- created_by, approved_by, delivered_by: Metadatos de responsables (si aplica).

Notas importantes de negocio (comportamientos recientes):
- Aprobación permite programar entrega el mismo día (validación after_or_equal:today en endpoints pertinentes).
- Al aprobar, el cronograma de cuotas inicia al día siguiente (start_date = approved_at + 1 día) y se ajusta end_date si fuese necesario.
- Si un manager crea un crédito para un cliente directo, se aplica fast‑track: pasa a waiting_delivery con approved_by/approved_at automáticos y start_date ajustado a mañana.

---

Permisos y visibilidad
- Cobradores autenticados: al llamar GET /api/credits, solo verán créditos de sus clientes asignados automáticamente (no necesitan enviar cobrador_id).
- Managers autenticados: verán créditos de sus clientes directos e indirectos (clientes de cobradores que administran). Pueden filtrar por cobrador_id para segmentar.
- Admins: sin restricciones de relación, pueden combinar client_id, status, search y cobrador_id.
- Endpoint simple GET /api/credits/client/{client}: accesible, pero si el solicitante es cobrador, se valida que el cliente esté asignado a él.

---

Consejos de integración (Flutter/JS)
- Usar el enfoque flexible (GET /api/credits) cuando necesites filtros y paginación.
- Mantener el client_id en el estado de pantalla y combinar con status y per_page para UX óptima.

Ejemplo JS (fetch) con filtros:
```
const qs = new URLSearchParams({ client_id: String(clientId), status: 'active', per_page: '20' }).toString();
const res = await fetch(`/api/credits?${qs}`, { headers: { Authorization: `Bearer ${token}` } });
const json = await res.json();
console.log(json.data.data); // array de créditos
```

---

Endpoints relacionados útiles
- GET /api/credits/{credit}/details: Resumen del crédito + pagos + cronograma.
- GET /api/credits/{credit}/payment-schedule: Cronograma con cuotas pagadas marcadas.
- GET /api/payments/credit/{credit}: Pagos de un crédito específico.

Para mapa completo de rutas, revisar routes/api.php y la guía `DOCUMENTACION_GENERAL.md`.
