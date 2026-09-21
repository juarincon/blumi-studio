# Despliegue de staging

El entorno de staging debe estar completamente separado de producción.

## Recomendación

Usar un subdominio o dominio de prueba, por ejemplo:

```text
staging.example.com
```

con:

- base de datos propia;
- usuario de base de datos propio;
- `.env` propio;
- clave OpenAI separada y con límites de gasto;
- uploads y builds propios;
- usuario auditor específico.

No copiar datos reales de clientes/proyectos a staging.

## 1. Subir el código

Clona el repositorio en el servidor o despliega desde Git.

El document root ideal debe apuntar a:

```text
<repo>/public
```

Si el hosting no permite cambiar document root, conserva una URL que termine apuntando a la carpeta `public`.

## 2. Crear `.env`

Copia `.env.example` a `.env` solamente en el servidor.

Ejemplo conceptual:

```text
APP_NAME="Blumi Studio - Staging"
APP_ENV=staging
APP_URL=https://staging.example.com
SESSION_NAME=blumi_staging_session
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=blumi_staging
DB_USER=blumi_staging_user
DB_PASS=<password-servidor>
OPENAI_API_KEY=<clave-staging>
OPENAI_MODEL=gpt-5.6-terra
OPENAI_TIMEOUT=90
OPENAI_MAX_OUTPUT_TOKENS=12000
```

No subas este archivo a Git.

## 3. Base de datos

Crea una base vacía y ejecuta:

```text
database/install_fresh.sql
```

Después crea el auditor:

```bash
php tools/create_admin.php "Auditor" auditor@ejemplo.com "UnaContrasenaTemporalSegura"
```

Cambia la contraseña de ejemplo antes de compartir acceso.

## 4. Permisos de escritura

PHP necesita escribir en:

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

Usa permisos compatibles con el usuario del proceso PHP. Evita `777` salvo diagnóstico temporal controlado.

## 5. Extensiones PHP

Verifica:

```text
pdo_mysql
curl
mbstring
fileinfo
zip
```

GD/WebP es recomendado para ciertas conversiones de imágenes.

## 6. HTTPS

Usa HTTPS. El bootstrap marca la cookie de sesión como `Secure` cuando detecta HTTPS.

## 7. Prueba mínima antes de compartir

- Login.
- Crear proyecto.
- Configurar Diseño.
- Crear página.
- Abrir Builder.
- Añadir componente.
- Crear componente desde Component Factory.
- Aprobar / duplicar variante.
- Probar Navbar estático/sticky.
- Probar Desktop / Tablet / Mobile.
- Descargar build ZIP.
- Publicar proyecto.
- Cerrar sesión y volver a entrar.

## 8. Qué compartir con el auditor

- URL de staging.
- usuario/contraseña temporal por canal privado;
- URL del repositorio GitHub;
- `README.md`;
- `AUDIT.md`.

No compartas credenciales de producción ni claves API reales por el repositorio.
