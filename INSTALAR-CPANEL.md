# Pulse en pulse.nooninteractive.com

Este paquete contiene el sitio PHP independiente para el subdominio. Las cuentas son propias de Pulse. No modifica la web principal ni la base de datos de Noon.

1. El subdominio y la carpeta indicada son `pulse.nooninteractive.com`. En cPanel → Domains comprueba la ruta completa de esa carpeta (por ejemplo `/home/TU_USUARIO/pulse.nooninteractive.com`). Conserva esa raíz independiente. Si necesitas crear el dominio, desmarca compartir la raíz de documentos. Documentación: https://docs.cpanel.net/cpanel/domains/domains/create-a-new-domain/
2. Comprueba el registro DNS `pulse` hacia el hosting (si gestionas DNS fuera de cPanel, créalo allí). Activa AutoSSL para el subdominio. El sitio requiere HTTPS.
3. Selecciona PHP 8.2 o posterior para Pulse. Activa `pdo_mysql`, `mbstring`, `fileinfo` y sesiones. Ajusta upload_max_filesize a 25M y post_max_size a 32M mediante MultiPHP INI Editor si `.user.ini` no se aplica.
4. En MySQL Database Wizard crea una base y usuario exclusivos para Pulse y asigna permisos a esa base. En phpMyAdmin importa `schema.sql`. No reutilices tablas de otras aplicaciones.
5. Copia `pulse-config.example.php` como `/home/TU_USUARIO/pulse-config.php`, fuera de `public_html`. Edita los datos MySQL y las rutas. Genera app_key en el terminal de cPanel: `php -r "echo bin2hex(random_bytes(32));"`. No compartas ni subas este archivo al directorio público. Permisos recomendados: 600.
6. Crea `/home/TU_USUARIO/pulse-storage`, con permisos 700 y escritura para PHP. Los archivos se guardan aquí y se sirven mediante la API; no se ejecutan ni se extraen archivos enviados por usuarios. Haz copia de seguridad de esta carpeta y la base de datos.
7. Extrae `pulse-public.zip` **dentro de la carpeta del subdominio `pulse.nooninteractive.com`** indicada en Domains. El ZIP contiene `index.html` directamente; no incluye una carpeta contenedora. Incluye `.htaccess` y `.user.ini`. No lo extraigas sobre la raíz de la web principal. Si ya hay un sitio Pulse, respáldalo primero.
8. Abre `https://pulse.nooninteractive.com`. Crea una cuenta, cierra sesión e inicia sesión de nuevo. En `/upload.html`, publica un audio y portada, reproduce el audio desde inicio y comprueba que sólo su autor puede eliminarlo. Verifica también en un navegador sin sesión que el formulario pide acceso antes de publicar.

## Qué incluye

Registro, inicio y cierre de sesión, contraseñas con bcrypt, cookies de sesión HttpOnly/Secure/SameSite, tokens CSRF, consultas preparadas, límites de intentos y publicaciones, validación real de MIME y tamaño de archivos, audio con soporte Range, listado paginado y eliminación de publicaciones propias. El registro inicia sesión directamente. Correos y contraseñas no se guardan en el navegador.

Las subidas admiten MP3/OGG/WAV (25 MB), portada JPG/PNG/WebP (5 MB) y metadata. Estas canciones se pueden escuchar en la comunidad; los cuatro beats demo mantienen el juego. No incluye importación de beatmaps `.osz`, generación de mapas desde el audio, recuperación de contraseña, verificación por correo ni panel de moderación.

Si aparece “todavía no está configurado”, revisa el archivo privado, extensiones PHP, credenciales MySQL y esquema. Si la subida supera el límite del servidor, la interfaz indica el rechazo. El código no imprime contraseñas, rutas privadas ni errores SQL. Revisa los registros de errores del hosting.

La búsqueda del archivo privado soporta `/home/usuario/pulse.nooninteractive.com`, `/home/usuario/public_html/pulse.nooninteractive.com`, `/home/usuario/public_html/pulse` o `/home/usuario/pulse`. Para otras estructuras, ajusta `$configPath` en `public/api/pulse.php` a la ruta absoluta privada. No coloques configuración o almacenamiento dentro de otra carpeta pública.
