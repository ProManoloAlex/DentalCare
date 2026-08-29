# DentalCare — Sistema de Gestión para Clínica Dental

## Instalación desde cero (máquina nueva)

### 1. Requisitos
- XAMPP (PHP 8.0+, MySQL/MariaDB, Apache)
- Composer instalado y apuntado al `php.exe` de XAMPP
- Un cliente SMTP real si quieres correos funcionando desde el día uno (Gmail con contraseña de aplicación, SendGrid, Mailtrap para pruebas, etc.)

### 2. Clonar / copiar el proyecto
Copia todo el código a tu carpeta de proyectos (fuera de `htdocs` de XAMPP si usas la extensión "PHP Server" de VS Code, o dentro de `htdocs` si vas a usar Apache directo).

### 3. Base de datos
1. Abre phpMyAdmin → **Nueva** → nombre `clinica_dental` → Crear
2. Selecciona esa base → pestaña **Importar** → elige `instalacion.sql` → Continuar

Esto crea las 26 tablas completas y siembra solo lo necesario para arrancar: horarios de atención, catálogo de servicios inicial, y las preferencias de notificaciones/alertas. **No** trae pacientes, citas ni ningún dato de prueba.

### 4. Variables de entorno
1. Copia `.env.example` y renómbralo a `.env` (en la raíz del proyecto, junto a `index.php`)
2. Llena los valores reales:
   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` — tus credenciales de MySQL
   - `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_SECURE` — tu proveedor de correo
   - `MAIL_FROM_EMAIL`, `MAIL_FROM_NAME` — remitente por defecto (el remitente real que ve el paciente se configura después, en Recordatorios → Configurar Canales)
   - `APP_URL` — la URL base donde sirves el proyecto (ej. `http://localhost/dentalcare` o `http://localhost:8012`, según cómo lo levantes). **Este valor cambia según el entorno** — revísalo cada vez que muevas el proyecto a una máquina o servidor distinto, o el link de "recuperar contraseña" no va a funcionar.

### 5. Composer
Desde la raíz del proyecto:
```
composer install
```
Esto crea `vendor/` con PHPMailer y las demás librerías. No se sube a git, así que hay que correrlo en cada máquina nueva.

### 6. Crear el primer doctor
Todavía no hay un flujo de auto-registro funcional para doctores, así que:
1. Copia `crear-primer-doctor.php` a la raíz del proyecto
2. Edita los 4 valores del inicio del archivo (nombre, correo, contraseña, especialidad)
3. Ábrelo en el navegador
4. **Bórralo del proyecto en cuanto veas la confirmación** — dejarlo ahí es un hueco de seguridad

### 7. Probar
Entra a `auth/Login.php` con el correo y contraseña que acabas de crear. Desde ahí ya puedes usar el sistema completo — pero antes de dar de alta pacientes reales, revisa el punto de Configuración → Clínica (horarios, datos de la clínica) y Recordatorios → Configurar Canales (remitente de correo).

---

## Pendientes conocidos

### Antes de manejar datos reales de una clínica de verdad

- **Backups automáticos en el servidor real** — el script de backup que ya tienes (`backup-clinica-dental.bat`) es para tu máquina de desarrollo. En producción hace falta el equivalente corriendo en el servidor real (o backups gestionados por el proveedor de hosting).

### Funcionalidad incompleta
- **PDF real en Reportes** — hoy es impresión del navegador, no un archivo generado en servidor.
- **Notificaciones automáticas por Email para eventos de pacientes** — solo Recordatorios de citas tiene envío real por SMTP. Los otros 5 eventos que existían en Configuración → Notificaciones se quitaron por decorativos (nunca se conectaron); si más adelante quieres reactivar alguno (confirmación de cita, bienvenida a paciente nuevo, etc.), hay que agregar la llamada a `Notificador::disparar()` en el Service correspondiente.
- **Auto-registro de doctores** — hoy la única forma de crear un doctor nuevo es por SQL directo (`crear-primer-doctor.php`) o insertando a mano. Si vas a tener más de un doctor usando el sistema, vale la pena construir un flujo real desde Configuración → Usuarios y Roles.


## Notas de arquitectura (para retomar el proyecto más adelante)

- **Patrón Repository → Service → Endpoint** estricto en todo el backend: SQL solo en Repository, reglas de negocio en Service, HTTP↔JSON en Endpoint.
- **Rutas relativas por profundidad de carpeta** — antes de crear un endpoint nuevo, cuenta cuántos niveles hay entre el archivo y `_verificar_sesion.php` / `services/`.
- **Auditoría**: casi todas las acciones que modifican datos llaman a `Auditoria::registrar()` — es un helper estático que nunca tumba la acción principal si falla.
- **Transacciones multi-tabla**: cuando una acción toca más de una tabla relacionada (por ejemplo, asignar un tratamiento crea tratamiento + cita + consentimiento a la vez), todo va dentro de una sola transacción — si algo falla a medias, se revierte todo.
