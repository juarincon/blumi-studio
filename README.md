# Blumi Studio

Blumi Studio es un builder web orientado a diseñadores. Convierte referencias e intención visual en componentes web reutilizables, permite ensamblarlos dentro de páginas y genera sitios HTML/CSS/JavaScript convencionales.

Este repositorio contiene una versión preparada para revisión técnica: no incluye `.env`, bases de datos reales, uploads de proyectos, builds históricos, sitios publicados ni backups de actualización.

## Stack

- Backend: PHP 8.1+.
- Base de datos: MySQL / MariaDB mediante PDO.
- Frontend: HTML5, CSS3 y JavaScript vanilla.
- Arquitectura: monolito modular con Controllers, Services, Repositories, Middleware y Views.
- IA: OpenAI Responses API como capa de asistencia para Component Factory.
- Build: salida estática HTML/CSS/JS y ZIP de proyecto.
- Dependencias de aplicación: no usa Composer, npm, React, Vue, Angular, Bootstrap ni Tailwind.

## Arquitectura de alto nivel

```text
Browser / UI
    ↓
public/index.php
    ↓
Controller
    ↓
Service
    ↓
Repository
    ↓
MySQL / MariaDB
```

Render de páginas:

```text
Proyecto
+ Design Tokens
+ Página
+ Componentes / instancias
+ Navbar y Footer globales
        ↓
    RenderService
        ↓
    HTML / CSS / JS
        ↓
Canvas / Build / Publicación
```

Component Factory:

```text
Referencia visual / instrucción
        ↓
ComponentFactoryController
        ↓
ComponentFactoryService
        ↓
ComponentIntentService
        ↓
AIComponentService
        ↓
Validación
        ↓
Componente pendiente de revisión
        ↓
Aprobación → componente maestro inmutable
```

## Principios actuales

- Relaciones críticas por ID/FK, no por nombres.
- Borrado lógico para entidades relevantes cuando aplica.
- Los componentes aprobados son inmutables; los cambios estructurales generan variantes.
- No se permite PHP arbitrario dentro de componentes.
- CSS/JS/HTML generado debe quedar encapsulado en el componente.
- Navbar y Footer pueden ser globales al proyecto.
- Smart Links resuelven relaciones internas por página/sección.
- Canvas, build y publicación comparten el mismo contrato de render.
- Los sitios publicados no dependen de OpenAI en runtime.

## Estructura principal

```text
app/
  Controllers/
  Helpers/
  Middleware/
  Repositories/
  Services/
config/
database/
  migrations/
public/
  assets/
  index.php
storage/
tools/
views/
```

Las carpetas de runtime se mantienen vacías en Git mediante `.gitkeep`:

```text
public/uploads/
public/sites/
storage/builds/
storage/components/
storage/logs/
storage/projects/
storage/update-backups/
storage/updates/
```

## Requisitos de PHP

Requeridos por el código actual:

- PDO MySQL
- cURL
- mbstring
- fileinfo
- ZipArchive / ext-zip para descargar builds y usar el actualizador

Opcional / recomendado:

- GD con soporte WebP; se usa para normalización de ciertas referencias de imagen cuando está disponible.

## Instalación local

La ruta histórica del proyecto es:

```text
blumi/blumi_studio/public
```

1. Copia `.env.example` como `.env`.
2. Ajusta conexión de base de datos y `APP_URL`.
3. Crea una base vacía.
4. Ejecuta:

```text
database/install_fresh.sql
```

5. Crea el primer superadmin:

```bash
php tools/create_admin.php "Admin Demo" admin@ejemplo.com "CambiaEstaContrasena"
```

6. Asegura permisos de escritura para las carpetas de runtime listadas arriba.
7. Configura el document root para que apunte a `public/`, o conserva la ruta histórica si el hosting trabaja por subcarpeta.

## Variables de entorno

Usa `.env.example` como referencia. Nunca publiques `.env`.

Para habilitar Component Factory:

```text
OPENAI_API_KEY=
OPENAI_MODEL=gpt-5.6-terra
OPENAI_TIMEOUT=90
OPENAI_MAX_OUTPUT_TOKENS=12000
```

La clave de OpenAI es consumida únicamente desde PHP en servidor.

## Base de datos

Para una instalación nueva utiliza `database/install_fresh.sql`.

`database/migrations/` conserva la evolución histórica del esquema y sirve para revisar decisiones incrementales. No se debe ejecutar toda la carpeta sobre una instalación que ya fue creada con `install_fresh.sql`.

## Build y publicación

Blumi puede:

- generar un sitio estático multipágina;
- descargarlo como ZIP;
- publicar una copia bajo `public/sites/`;
- resolver Smart Links durante el build;
- reutilizar assets compartidos del proyecto.

`storage/builds/` se considera almacenamiento regenerable y no forma parte del código fuente.

## Seguridad implementada

Entre los controles presentes en el proyecto:

- sesiones PHP con `HttpOnly`, `SameSite=Lax` y `Secure` cuando HTTPS está activo;
- CSRF en operaciones de escritura relevantes;
- consultas PDO preparadas;
- validación de MIME/extensión/tamaño en uploads;
- roles de aplicación;
- uploads renombrados internamente;
- API key de OpenAI obtenida desde variables de entorno;
- componentes sin PHP arbitrario.

Esto no debe interpretarse como una auditoría de seguridad completada. Seguridad es una de las áreas que se solicita revisar.

## Alcance de esta revisión técnica

La intención del repositorio no es demostrar una arquitectura definitiva. Blumi se ha construido de forma iterativa con asistencia intensiva de IA y se busca una revisión externa específicamente sobre mantenibilidad, responsabilidades y deuda técnica.

Ver `AUDIT.md` para el contexto y las preguntas concretas de revisión.

## Staging

Ver `STAGING.md` para una guía de despliegue aislado de producción.
