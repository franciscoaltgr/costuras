# Sistema de Gestión Monolítico para Taller de Costuras - Grill Guard

Aplicación web desarrollada con **PHP 8 (PDO)** y **MySQL 8 (InnoDB)**, concebida bajo principios de ingeniería backend senior: **integridad referencial estricta, operaciones ACID mediante transacciones atómicas, prevención de inyecciones SQL nativas, manejo profesional de UTF-8 y un módulo oficial de cotizaciones con generación de PDF mediante librería open-source (FPDF)**.

---

## 1. Arquitectura y Estructura del Proyecto

El proyecto sigue una estructura limpia que desacopla la configuración, la persistencia de datos, los componentes de interfaz reutilizables, los controladores de negocio y los generadores de documentos:

```text
c:\xampp\htdocs\costuras\
├── assets/
│   └── img/
│       ├── logo_grill_guard.jpg       # Logo oficial Grill Guard extraído y optimizado
│       └── cotizacion_ejemplo.jpg     # Muestra original del formato de cotización
├── database/
│   ├── schema.sql                     # Definición DDL completa con cotizaciones y seeders UTF-8
│   └── migracion_cotizaciones_y_charset.sql # Script de migración y saneamiento
├── includes/
│   ├── header.php                     # Encabezado HTML5 con sticky layout, logo y menú Grill Guard
│   ├── footer.php                     # Sticky footer "2026 grill guard" al final de todas las páginas
│   ├── helpers.php                    # Funciones de escape XSS con ENT_SUBSTITUTE, CLP y fechas
│   └── CotizacionPDF.php              # Clase generadora de PDF profesional basada en FPDF
├── lib/
│   └── fpdf/                          # Librería open-source FPDF 1.86 para generación de PDF en PHP
├── conexion.php                       # Conexión centralizada PDO con UTF-8mb4 unicode y manejo de errores
├── index.php                          # Panel de pedidos activos, filtros y balances financieros
├── crear_pedido.php                   # Alta atómica: cliente, orden, medidas y abono inicial
├── ver_pedido.php                     # Consulta detallada: cliente, prendas, medidas y pagos
├── actualizar_estado.php              # Transacción con bloqueo pesimista: estado y abonos
├── eliminar_pedido.php                # Eliminación atómica transaccional de pedidos
├── cotizaciones.php                   # Módulo de cotizaciones: listado, filtros e indicadores
├── nueva_cotizacion.php               # Formulario interactivo con cálculo dinámico de IVA (19%) y abonos
├── ver_cotizacion.php                 # Vista de cotización con la estética oficial exacta y soporte de impresión
├── generar_pdf_cotizacion.php         # Generación y descarga directa de PDF vectorial vía FPDF
├── convertir_cotizacion_a_pedido.php  # Conversión directa de cotización a pedido activo de confección
├── eliminar_cotizacion.php            # Eliminación segura de cotizaciones
└── README.md                          # Documentación técnica completa
```

---

## 2. Puntos Clave de la Solución

### A. Footer y Top Bar Grill Guard
1. **Footer siempre al final:** Implementado con un diseño *Sticky Footer* usando Flexbox (`min-vh-100`, `flex-direction: column`, `flex-grow-1` en el contenedor `<main>` y `mt-auto` en el `<footer>`). Esto garantiza que el pie de página permanezca siempre anclado al fondo de la pantalla incluso si el contenido de la página es corto, y se desplace naturalmente en páginas extensas.
2. **Texto del Footer:** Muestra exactamente `2026 grill guard`.
3. **Top Bar Superior Izquierdo:** Incluye el isotipo/logo de Grill Guard junto al nombre oficial `Grill Guard | Taller de Costuras`.
4. **Pestaña del Navegador:** `<title>` dinámico con el formato `[Título de Página] - Grill Guard | Taller de Costuras`.

### B. Corrección Profesional de Codificación UTF-8
1. **Base de Datos y Tablas:** La base de datos y todas las tablas fueron convertidas a `utf8mb4` con cotejamiento `utf8mb4_unicode_ci`, soportando todos los acentos, tildes y caracteres especiales en español.
2. **Sesión PDO en PHP:** La capa `conexion.php` define `charset=utf8mb4`, envía `SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'` en la inicialización y ejecuta `SET CHARACTER SET utf8mb4`.
3. **Entorno PHP:** Se configuran `mb_internal_encoding('UTF-8')`, `mb_http_output('UTF-8')`, `ini_set('default_charset', 'UTF-8')` y la cabecera HTTP `Content-Type: text/html; charset=UTF-8`.
4. **Escape Seguro:** La función `e()` utiliza `htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` para evitar pérdidas de contenido en secuencias no conformes.
5. **Saneamiento de Registros:** Se repararon los registros existentes que tenían caracteres corruptos (`?` o `Ã¡`) convirtiéndolos a sus formas ortográficas correctas en español (`Gómez`, `Valenzuela`, `Díaz`, `francés`, `ilusión`, `Reducción`, `tacón`, `graduación`).

### C. Módulo de Cotizaciones Grill Guard
1. **Ubicación en Navbar:** Acceso directo llamado **"Cotizaciones"** ubicado a la izquierda de "Listado de Pedidos".
2. **Formato Fiel a la Muestra:** Reproducción exacta del membrete oficial con:
   - Logo en relieve de Grill Guard.
   - Datos del taller: Pecheras Parrilleras, +56 9 1013 1407, grillguardch@gmail.com, Rosas 1339 Santiago Centro, La Hermita 1556 Talca.
   - Caja destacada en verde para el número de cotización (ej. `COTIZACION 21126`).
   - Tabla de datos del cliente (Nombre, Dirección, Teléfono, Correo, Fecha).
   - Cuadrícula de confección con cabeceras en tonos pastel (`DESCRIPCION`, `COLOR`, `CANTIDAD`, `UNITARIO`, `VALOR`).
   - Bloque de totales: Subtotal Neto, IVA (19%), Total General, Abono (50%) y Saldo (50%).
   - Nota al pie: `"TALLER SOLICITA 50% CON LA CONFIRMACION Y 50% CON LA ENTREGA"`.
3. **Generación de PDF Open-Source (FPDF):** Implementado con **FPDF 1.86** (código abierto, sin dependencias externas pesadas, ultrarrápido y compatible con PHP 8.x). Permite abrir en línea o descargar el PDF vectorial idéntico al formato comercial.
4. **Impresión Nativa:** Reglas CSS `@media print` para imprimir directamente o guardar como PDF desde el navegador ocultando barras y botones.
5. **Flujo de Negocio:** Permite convertir con un solo clic una cotización aprobada en un pedido activo de confección en taller.
