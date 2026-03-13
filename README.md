# Sonia Burger — Sistema de Restaurante

**Sistema de punto de venta y gestión para restaurante**: pedidos, menú, inventario y reportes (USD ↔ BS).

## Características
- Gestión de **pedidos** y **líneas de pedido**.
- Conversión automática **USD → BS** con tasa de referencia.
- **Reportes** diarios y exportación a CSV/Excel.
- Módulos: **Ventas**, **Menú**, **Inventario**, **Reportes**, **Auditoría**.

## Tecnologías
- **Frontend:** HTML, CSS, Bootstrap, JavaScript  
- **Backend:** PHP  
- **Base de datos:** MySQL (WAMPServer)  
- **Editor:** Visual Studio Code

## Instalación (local)
1. Instalar **WAMP** (o XAMPP).  
2. Copiar el proyecto a `www` (o `htdocs`).  
3. Importar `database.sql` o crear uno en **phpMyAdmin**.  
4. Configurar `config.php` con credenciales MySQL y ruta base.  
5. Abrir `http://localhost/tu-proyecto` en el navegador.

## Uso
- **Usuario cajero:** crear pedidos, cerrar caja.  
- **Usuario gerente:** administrar menú, ver reportes y gastos.  
- **Notas:** la sincronización entre PCs y la consulta automática de tasa BCV están planificadas como mejoras.

## Pruebas
- Casos funcionales: crear pedido, cerrar caja, exportar reporte.  
- Unit tests sugeridos: cálculo de totales, conversión USD→BS, validaciones.

## Mantenimiento y roadmap
- Backups diarios; revisión semanal de logs.  
- Próximas mejoras: **Node.js** para sincronización, inventario por receta, manejo desde multiples dispositivos como pc secundaria y tablet.

## contacto
- **Contacto:** Moises David Rojas Millan — `Moidavidtr@gmail.com`
