<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Datos de demostración

Para poblar la base de datos local con datos de prueba, ejecuta:

```bash
php artisan db:seed
```

El seeder crea o actualiza usuarios, al menos diez asignaturas, grupos, equipos y evidencias relacionadas entre sí. Las cuentas de demostración usan la contraseña `Demo2026!`. El seeder es idempotente: puede volver a ejecutarse sin duplicar esos registros.

Los documentos del corpus y el historial de respaldos no se generan automáticamente, porque requieren archivos PDF y copias SQL reales en el almacenamiento.

## Sanitización de PDFs con Python

La eliminación de la carátula y el conteo de páginas los realiza [scripts/pdf_sanitizer.py](scripts/pdf_sanitizer.py) (Python 3.9+ con `pypdf`); Laravel solo lo invoca desde `App\Services\PdfSanitizer`. Los PDFs se guardan en disco (`storage/app/private/corpus/...`) y la base de datos conserva únicamente su ruta.

```bash
python3 -m venv .venv
.venv/bin/pip install -r scripts/requirements.txt
```

Si existe `.venv` en la raíz del proyecto se usa automáticamente; en otro caso se usa `python3`. Puedes indicar otro intérprete con `PYTHON_BIN` en `.env` (y `PYTHON_TIMEOUT` para el límite en segundos). Sin Python y `pypdf` instalados no se pueden sanitizar PDFs.

## Evaluación docente por rubro

En **Evaluación Docente** el docente califica cada criterio de la rúbrica entre 0 y su puntaje máximo; la calificación total (sobre 100) es la suma de los rubros. La retroalimentación de cada rubro es opcional, igual que los comentarios generales. Las calificaciones se guardan en `evaluacion_docente_criterios` (ejecuta `php artisan migrate`).

## Evaluación con IA y Motor Comparativo

- **Evaluación con IA** (menú de Profesor y Líder): evalúa con Gemini cada PDF sanitizado según los criterios «evaluables desde el producto» de la rúbrica y **guarda** el resultado en `evaluaciones_ia` / `evaluacion_ia_criterios`; no se descarga ningún archivo. Volver a evaluar reemplaza la evaluación anterior. Al guardar la primera evaluación la rúbrica de la evidencia queda bloqueada.
- **Motor Comparativo (FN.10)**: cruza la calificación de la IA con la del docente y guarda en `evaluaciones_comparativas` y `comparativa_criterios_detalle` el margen de error porcentual, la varianza y la diferencia de tiempos. Ambas calificaciones se comparan sobre los mismos criterios (los que evalúa la IA) y se llevan a 100 puntos. La comparación se actualiza sola al guardar cualquiera de las dos evaluaciones; «Procesar lote» recalcula todo el lote. El umbral de alerta es `COMPARATIVA_UMBRAL` (10 % por defecto). Las evaluaciones del docente capturadas antes de calificar por rubro solo son comparables si la IA cubrió toda la rúbrica.
- Ejecuta `php artisan migrate` para crear las tablas comparativas si no existen.

## Reporte de Discrepancia Evaluativa

Solo el Líder de Proyecto (menú «Reportes Gerenciales»). Compara la diferencia porcentual entre la calificación del docente y la de Gemini con una gráfica de barras comparativas por evidencia, filtrable por rango de fechas y profesor, y descargable en PDF. Usa las comparaciones que ya calculó el Motor Comparativo.

- El rango de fechas se aplica a la fecha en que el docente registró su evaluación; el profesor es el dueño de la evidencia.
- El PDF lo genera `App\Services\ReporteDiscrepanciaPdf` con FPDF (sin servicios externos): filtros, indicadores, gráfica de barras (8 evidencias por gráfica), resumen por evidencia y detalle por documento. Los valores en rojo superan el umbral `COMPARATIVA_UMBRAL`.

## Reporte de Eficiencia de Tiempos

Solo el Líder de Proyecto (menú «Reportes Gerenciales»). Contrasta el tiempo de revisión del docente con el tiempo de respuesta de la API de Gemini mediante una gráfica de columnas comparativas por evidencia, filtrable por rango de fechas y asignatura, y descargable en Excel (.xlsx).

- Solo cuentan documentos con evaluación del docente y de Gemini. El rango de fechas se aplica a la fecha de registro de la evaluación del docente; la asignatura es la del grupo del equipo.
- El Excel (`App\Services\ReporteTiemposExcel`, PhpSpreadsheet) trae la hoja «Resumen» (indicadores, tabla por evidencia y una gráfica de columnas nativa de Excel) y la hoja «Detalle» (un renglón por documento, con filtros). Los textos se guardan como cadenas, por lo que un título que empiece con `=` no se ejecuta como fórmula.

## Reporte de Rendimiento Grupal

Para el Profesor (menú «Reportes Académicos») y para el Líder de Proyecto (menú «Reportes Gerenciales» y botón del tablero, con acceso a los grupos de todos los profesores). Muestra las calificaciones y el desempeño por rubro de los equipos de un grupo, cruzando la evaluación del docente con la de la IA mediante una gráfica de barras apiladas, y se descarga en Excel (.xlsx).

- Se filtra por asignatura y grupo, y además por **evidencia**, porque los rubros pertenecen a cada evidencia. Cada equipo tiene una barra del docente y otra de la IA, con un segmento por rubro.
- La IA solo califica los rubros «evaluables desde el producto»; los demás aparecen solo en la barra del docente. Las evaluaciones del docente anteriores al registro por rubro se muestran como un total «sin desglose».
- El Excel (`App\Services\RendimientoGrupalExcel`) trae tres hojas: «Resumen» (indicadores, desempeño por rubro y total por equipo), «Gráfica» (tabla fuente y gráfica de columnas apiladas nativa de Excel) y «Detalle por rubro» (equipo × rubro, con retroalimentación).

## Reporte de Contraste Evaluativo Individual

Solo para el Estudiante (menú «Reportes y Contraste»). Compara, rubro por rubro, la calificación del profesor con la de la IA para el documento de **su propio equipo** mediante una gráfica de barras horizontales, y se descarga en PDF.

- Se filtra por asignatura y evidencia. El equipo se deduce siempre de la sesión (`equipo_estudiantes`), nunca de la petición, por lo que un estudiante no puede consultar el documento de otro equipo.
- La longitud de cada barra es el porcentaje de la calificación máxima de cada rubro. La IA solo califica los rubros evaluables desde el producto; los demás aparecen solo con la barra del profesor. Una fila «TOTAL» compara ambas calificaciones sobre los mismos rubros.
- El PDF (`App\Services\ContrasteIndividualPdf`, FPDF) incluye los datos del estudiante, indicadores, la gráfica y la retroalimentación del profesor y de la IA. Solo se puede descargar cuando el profesor y la IA ya evaluaron el documento.

## Evaluación de evidencias con Gemini

1. Define `GEMINI_API_KEY` en el archivo `.env` con una clave de Google AI Studio. No la publiques ni la guardes en el repositorio. `GEMINI_MODEL` permite cambiar el modelo; el valor inicial es `gemini-3.5-flash`. Si Gemini responde «model no longer available» (HTTP 404), elige otro de los que devuelve `https://generativelanguage.googleapis.com/v1beta/models` para tu clave.
2. Ejecuta `php artisan migrate` para crear las tablas de rúbrica y evaluación que aún no estén en el esquema.
3. Cada evidencia se califica sobre 100 puntos. Puedes asignar a la evidencia un **código** `ASIGNATURA-EP` (ej. `GDS-EP1`) y definir su rúbrica de una de estas formas, siempre con una suma exacta de 100 puntos:
   - En **Evidencias**, abre **Rúbrica** y captura los criterios.
   - En **Evaluación Docente**, usa **Importar rúbricas (Excel/CSV)** con un .xlsx, .xls o .csv con las columnas `asignatura, tipo_evidencia, orden_criterio, id_criterio, criterio, puntaje_max, evaluable_desde_el_producto`. Las coincidencias inequívocas por nombre de asignatura o código generan un formulario editable; si hay varias evidencias, un código incompatible o una abreviatura sin coincidencia, elige la asignatura y la evidencia. La rúbrica se guarda al confirmar el formulario; si ya existe, se solicita confirmación para reemplazarla. Las rúbricas con evaluaciones (de IA o calificadas por el docente rubro por rubro) no pueden modificarse.
   Los criterios con `evaluable_desde_el_producto = no` se conservan, pero no se envían a la IA. No se puede enviar una evidencia a la IA sin rúbrica.
4. En **Corpus**, sanitiza los PDFs y entra a **Evaluación con IA** para evaluarlos (la evaluación se hace al momento, sin worker).

La integración envía a Gemini solo la ruta PDF sanitizada y los criterios evaluables de la rúbrica. Se guardan la calificación global, comentario cualitativo, tiempo, modelo y resultados por criterio. La tabla `evaluacion_ia_criterios` complementa el diagrama existente, que no incluye un detalle de puntajes de IA por criterio.

La sanitización actual elimina la primera página, pero no detecta nombres, matrículas ni otros datos identificables en las páginas restantes o en metadatos. Revisa los documentos antes de enviarlos a un proveedor externo. El PDF temporal se elimina de Gemini al terminar cada evaluación.

## Notificaciones por correo

Además de la recuperación de contraseña, el sistema envía estos correos (SMTP de `.env`) y deja una copia en la campana del sistema. El envío ocurre al terminar la respuesta y un fallo de SMTP solo se registra en el log, sin interrumpir la operación.

- **Solicitud para ser profesor:** el estudiante usa el botón «Solicitar cambio a Profesor» de su panel (tabla `solicitudes_profesor`; solo una pendiente a la vez). El aviso llega a **un solo** usuario: el Líder cuyo correo esté en `SOLICITUDES_PROFESOR_EMAIL` o, si no se define, el Líder activo más antiguo. La solicitud se atiende cambiando el rol en Gestión de Usuarios, lo que la marca como atendida.
- **Perfil modificado:** al editar un usuario en Gestión de Usuarios o cambiar su estado, el titular recibe un correo con los datos modificados (nunca sus valores; la contraseña solo se menciona como cambiada). Si cambió el correo, también se avisa al anterior.
- **Dispositivo nuevo:** al iniciar sesión (formulario o Google) desde un navegador que no se había usado con esa cuenta, el titular recibe un correo con navegador, sistema, IP y hora. El navegador se identifica con la cookie `dispositivo_id` (se guarda solo su hash en `dispositivos_usuario`); el primer acceso de una cuenta solo registra el dispositivo, sin correo. Aplica a todos los roles.

## Envío de lotes a la IA y notificaciones

- En **Evaluación con IA**, «Enviar lote a la IA» evalúa de uno en uno los PDFs sanitizados de la evidencia que aún no tienen evaluación (los ya evaluados se reenvían individualmente con «Volver a evaluar»). Cada PDF es un job (`App\Jobs\ProcesarLoteEvaluacionIa`) que, al terminar, encola el siguiente; el avance queda en `lotes_evaluacion_ia` y la pantalla lo muestra con una barra de progreso. Un PDF con error no detiene el lote.
- Si Gemini responde **HTTP 503**, la petición se reintenta tras 1, 2, 4, 8 y 16 segundos (`GeminiEvaluationService::ESPERAS_503_MS`); otros errores no se reintentan. Aplica también a la evaluación individual.
- Al terminar un lote o una evaluación individual se crea una **notificación del sistema** (tabla `notificaciones`, canal `sistema`). La campana del encabezado muestra las no leídas, avisa con un recuadro al llegar una nueva y, si el usuario lo permite, con un aviso de escritorio del navegador.
- Con `QUEUE_CONNECTION=database` la aplicación inicia sola un `php artisan queue:work --stop-when-empty` en segundo plano (intérprete configurable con `PHP_BIN`; su salida va a `storage/logs/cola-ia.log`). Si tu entorno no lo permite, ejecuta `php artisan queue:work` manualmente. Ejecuta `php artisan migrate` para crear `lotes_evaluacion_ia`.

## Zona horaria

Todas las fechas y horas (pantallas, reportes PDF/Excel, `now()` y registros de MySQL) usan la hora de la Ciudad de México. Se configura con `APP_TIMEZONE=America/Mexico_City` y `DB_TIMEZONE=-06:00` (CDMX no aplica horario de verano; se usa el desfase porque MySQL no tiene cargadas las zonas con nombre). Los datos guardados antes de este ajuste con hora UTC (evaluaciones, comparativas, respaldos y tokens de contraseña) se restaron 6 horas una sola vez; si importas una base de datos antigua, haz el mismo ajuste.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
