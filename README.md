# pulse-web

Web de ritmo de Noon Interactive para **https://pulse.nooninteractive.com**, con PHP 8.2+, MySQL y Apache/cPanel.

La interfaz está en inglés e incluye catálogo, subida y descarga de mapas .pls para Unity. Incluye juego de círculos con cuatro beats demo sintetizados, registro e inicio de sesión, subida de canciones MP3/OGG/WAV con portada, reproducción de contenido de la comunidad, favoritos locales y eliminación de publicaciones propias.

## Instalar en cPanel

Sigue [INSTALAR-CPANEL.md](INSTALAR-CPANEL.md). La raíz confirmada del subdominio es `/home/noonint1/pulse.nooninteractive.com`.

- Sube sólo el contenido de `public/` a esa carpeta (incluidos `.htaccess` y `.user.ini`).
- Prepara la base MySQL y la tabla de mapas por separado. Los archivos SQL no se incluyen en este repositorio.
- Configura `pulse-config.example.php` como `/home/noonint1/pulse-config.php`, fuera de todas las carpetas públicas.
- Crea `/home/noonint1/pulse-storage` con escritura para PHP y ajusta `storage_path`.
- Activa HTTPS y las extensiones PHP `pdo_mysql`, `mbstring`, `fileinfo`, `zip` y sesiones.

No subas contraseñas, configuración real, archivos de usuarios ni bases de datos a GitHub. No subas este repositorio completo al directorio público.

## Local development

On Windows, install PHP 8.2+ with `pdo_sqlite`, `mbstring`, `fileinfo`, and `zip`, then run `./Start-Local.ps1`. Open `http://127.0.0.1:4191/`. Set `PULSE_PHP` to the absolute path of `php.exe` if PHP is not on PATH. Run `./Stop-Local.ps1` to stop this server.

The launcher creates private, disposable development storage in `.local-data/4191`, which is excluded from Git. `examples/example.pls` contains the original synthesized demo map. No production database, SQL files, credentials, sessions, or user uploads are included.

To run map integration tests, start a separate local server with `./Start-Local.ps1 -Port 4192`, then run `npm run test:maps -- /absolute/path/to/exported-map.pls`. These tests create disposable accounts and uploads on that test server. Stop it with `./Stop-Local.ps1 -Port 4192`.

## Pruebas

`npm ci` y `npm test` ejecutan la integración con PHP 8.3 WebAssembly y una base SQLite efímera, sin datos del hosting. Cubren cuentas, CSRF, validación de archivos, subida WAV/PNG, reproducción y Range, permisos del autor y eliminación. Estas pruebas no verifican la conexión MySQL del hosting.

Para inspeccionar la interfaz sin configurar cuentas: `php -S 127.0.0.1:4191 -t public`. La API mostrará un aviso de configuración pendiente. No utilices este servidor de desarrollo en producción.

## Alcance

Audio hasta 25 MB, portada hasta 5 MB, cuota de 250 MB por cuenta y seis publicaciones por hora. Las canciones subidas se reproducen en la comunidad; el juego utiliza los beats demo. No incluye generación automática de beatmaps, verificación por correo, recuperación de contraseña o moderación administrativa.

Inspirado por osu!. Proyecto independiente de Noon Interactive.
