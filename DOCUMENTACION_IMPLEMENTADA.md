# Sistema de laboratorio clínico Ismalej Prado

## Estado documentado

**Fecha de revisión:** 11 de noviembre de 2026.  
**Proyecto:** LABORATORIO CLÍNICO-BIOLÓGICO ISMALEJ PRADO.  
**Desarrollo:** IteraTotal.

Este documento describe el código implementado y las verificaciones realizadas en el entorno local. No constituye aprobación clínica, certificación de seguridad ni acta de aceptación para producción. No contiene credenciales ni datos de pacientes.

El sistema tiene un panel operativo con pacientes, catálogo de exámenes, órdenes, captura, validación, PDF, publicación QR, auditoría y configuración institucional. Conserva una POC independiente de edición visual con GrapesJS. El PDF operativo todavía no se diseña desde GrapesJS.

## Índice

1. [Tecnologías y entorno](#1-tecnologías-y-entorno)
2. [Arquitectura y ubicación del código](#2-arquitectura-y-ubicación-del-código)
3. [Acceso, usuarios y permisos](#3-acceso-usuarios-y-permisos)
4. [Pacientes](#4-pacientes)
5. [Catálogo clínico y HECES](#5-catálogo-clínico-y-heces)
6. [Órdenes y correlativos](#6-órdenes-y-correlativos)
7. [Captura de resultados](#7-captura-de-resultados)
8. [Validación y reapertura](#8-validación-y-reapertura)
9. [Reportes PDF](#9-reportes-pdf)
10. [Publicación y QR](#10-publicación-y-qr)
11. [Historial y búsquedas](#11-historial-y-búsquedas)
12. [Configuración del laboratorio](#12-configuración-del-laboratorio)
13. [POC de GrapesJS](#13-poc-de-grapesjs)
14. [Modelo de datos](#14-modelo-de-datos)
15. [Seguridad y auditoría](#15-seguridad-y-auditoría)
16. [Operación del personal](#16-operación-del-personal)
17. [Desarrollo y mantenimiento local](#17-desarrollo-y-mantenimiento-local)
18. [Pruebas y evidencias](#18-pruebas-y-evidencias)
19. [Estado de las fases](#19-estado-de-las-fases)
20. [Pendientes y límites](#20-pendientes-y-límites)

## 1. Tecnologías y entorno

Versiones identificadas en esta sesión:

| Componente | Versión / situación |
| --- | --- |
| PHP local | 8.4.27 |
| Laravel | 13.40.0 |
| Filament | 5.11.2 |
| Livewire | Utilizado por Filament y las pantallas de captura |
| Spatie Laravel Permission | 8.5.0 |
| Filament Shield | 4.3.1 |
| Laravel Dompdf | 3.1.2 |
| HTMLPurifier | 4.19.1 |
| GrapesJS | 0.22.14, cargado desde CDN en la POC |
| Pest | 5.3.5 |
| Laravel Pint | 1.32.1 |
| Base local configurada | SQLite |
| Base prevista para hosting | MySQL, pendiente de verificación en destino |
| Recursos frontend | Vite; Node durante desarrollo/compilación |
| Servidor local | Laravel Herd |
| Zona horaria local configurada | UTC |
| Idioma de Laravel | Español |

El entorno revisado es `local` y tiene depuración habilitada. Esas condiciones no equivalen a una configuración lista para producción. Las versiones efectivas se consultan con `composer show --direct`; las restricciones de `composer.json` pueden abarcar versiones diferentes.

## 2. Arquitectura y ubicación del código

La lógica operativa se encuentra principalmente en acciones PHP, separada de las pantallas de Filament y de las vistas PDF.

| Responsabilidad | Ubicación |
| --- | --- |
| Modelos y relaciones | `app/Models/` |
| Políticas de autorización | `app/Policies/` |
| Operaciones sobre órdenes | `app/Actions/Orders/` |
| Captura y revisiones | `app/Actions/Results/SaveOrderExamResultDraft.php` |
| Catálogo y versiones | `app/Actions/Exams/` |
| Configuración versionada | `app/Actions/Settings/UpdateLabProfile.php` |
| Recursos y páginas internas | `app/Filament/` |
| Configuración del panel | `app/Providers/Filament/AdminPanelProvider.php` |
| Auditoría de modelos | `app/Observers/AuditObserver.php` |
| Controladores PDF operativo | `OrderReportPdfController`, `PublicOrderReportPdfController` |
| Vista PDF y pie fijo | `resources/views/reports/order.blade.php`, `footer.blade.php` |
| Editor y PDF de la POC | `resources/views/plantillas/`, `PlantillaPocController` |
| Estructura inicial de HECES | `config/heces.php`, `HecesCatalogSeeder` |
| Esquema de la base | `database/migrations/` |
| Pruebas | `tests/Feature/` |

Flujo operativo del PDF:

```text
Catálogo → orden con snapshot → captura → validación completa
→ snapshot de resultados, paciente e institución
→ BuildValidatedOrderReportData
→ RenderValidatedOrderPdf
→ vistas Blade → Dompdf → visualizar / descargar
```

Las operaciones que afectan varias entidades utilizan transacciones en sus acciones. La creación de correlativos y las operaciones de validación/publicación utilizan bloqueos sobre los registros correspondientes.

## 3. Acceso, usuarios y permisos

El panel se encuentra bajo `/admin`. Se implementaron login, usuarios activos/inactivos, asignación de roles y gestión de permisos. El acceso al panel requiere usuario activo y `Access:Panel`.

Las contraseñas se guardan con hash. La administración de usuarios exige una contraseña de al menos 12 caracteres con mayúsculas/minúsculas, números y símbolos, además de confirmación. No hay una contraseña de producción predefinida.

### Roles iniciales efectivos

La siguiente tabla refleja `RolesAndPermissionsSeeder`, no una propuesta de permisos futura:

| Operación | Administrador | Técnico | Validador |
| --- | --- | --- | --- |
| Consultar pacientes | Sí | Sí | Sí |
| Crear y editar pacientes | Sí | Sí | No |
| Consultar catálogo | Sí | No | Sí |
| Gestionar catálogo | Sí | No | No |
| Consultar órdenes y resultados | Sí | No | Sí |
| Crear, editar y anular órdenes permitidas | Sí | No | No |
| Capturar resultados editables | Sí | No | No |
| Validar y reabrir órdenes | No | No | Sí |
| Generar PDF de orden validada | Sí | No | Sí |
| Publicar QR y revocar acceso | No | No | Sí |
| Usuarios, roles, plantillas y configuración | Sí | No | No |
| Consultar auditoría | Sí | No | No |

La restricción del técnico a pacientes y la reserva de validación/reapertura al validador fueron decisiones adoptadas durante la implementación. El administrador no tiene una excepción general para validar clínicamente.

Los permisos usan nombres como `ViewAny:Patient`, `Create:Order`, `Capture:Result`, `Validate:Order`, `Publish:Report` y `Manage:LabSettings`. Las políticas vuelven a verificar las acciones y el estado del registro en el servidor.

El seeder crea permisos de forma idempotente, pero usa `syncPermissions` para los roles iniciales: volver a ejecutarlo restablece sus asignaciones definidas en código y puede reemplazar personalizaciones hechas desde el panel.

## 4. Pacientes

Se implementaron registro, consulta, edición, búsqueda y listado paginado, sujetos a permisos.

Datos almacenados: nombres, apellidos, fecha de nacimiento opcional, sexo, teléfono e identificador. El formulario contempla sexo femenino/masculino y ausencia de dato. El PDF presenta ese campo como «Género».

Los datos del paciente se copian al snapshot al validar la orden. Editar posteriormente al paciente no cambia ese contexto del reporte validado.

## 5. Catálogo clínico y HECES

El catálogo utiliza exámenes, secciones y parámetros. Incluye códigos, orden de visualización, activación, fuente y estado de validación clínica. Cada parámetro puede definir unidad, referencia, opciones y tipo de resultado.

En el formulario de parámetros, el selector «Examen y sección» identifica las opciones con el código del examen y el nombre de la sección, por ejemplo `HECES — MACROSCÓPICO` y `ORINA — MACROSCÓPICO`. La búsqueda admite nombre/código del examen y nombre de sección, y la edición conserva la etiqueta del registro seleccionado.

Tipos implementados:

- Texto.
- Número.
- Selección.
- Positivo/negativo con opciones configuradas.
- Observación.
- Fila de matriz.

Las secciones admiten tablas convencionales y matriz parasitológica. La matriz configura sus columnas; la definición exige al menos dos. El PDF calcula sus anchos según esa configuración.

En las secciones de tipo «Tabla de parámetros», «Distribución en el PDF» permite elegir la tabla de parámetro/resultado/unidad/referencia o dos grupos de parámetro/resultado, como HECES. La elección se guarda en `settings.report_layout`, se refleja en la vista previa y se conserva en los snapshots de las órdenes. Si no se elige, se conserva el diseño anterior: HECES por pares y los demás exámenes con tabla estándar. No se modifican automáticamente secciones existentes de ORINA.

### Vista previa PDF durante la configuración

Cada examen dispone de «Vista previa PDF» en su listado y sus páginas de consulta/edición. Al crear un parámetro, seleccionar «Examen y sección» habilita «Vista previa del examen»; la edición del parámetro también ofrece este acceso.

La vista abre otra pestaña y usa las secciones y parámetros activos del catálogo editable, respetando su orden. Después de guardar nuevos campos o cambios, recargar esa pestaña para verlos. Los cambios aún sin guardar no se incluyen y no es necesario publicar una versión del catálogo.

`BuildExamPreviewReportData` construye exclusivamente datos ficticios; `ExamPreviewPdfController` reutiliza `RenderValidatedOrderPdf` y el diseño compartido, incluido el pie fijo. La vista identifica el documento como BORRADOR y sin validación clínica. No aplica firmas ni crea órdenes, resultados, eventos de validación o tokens QR. Las referencias no aprobadas se etiquetan como pendientes dentro de esta vista de configuración.

Se admite previsualizar un examen inactivo en preparación. Las secciones sin parámetros y los exámenes sin secciones muestran mensajes explícitos; una matriz sin columnas suficientes indica qué falta configurar.

La ruta `GET /catalogo/examenes/{exam}/vista-previa`, denominada `exams.preview`, requiere autenticación y permiso de consulta del examen. Con los roles iniciales, administrador y validador pueden acceder; técnico no. `ExamPreviewTest.php` cubre estos permisos, datos ficticios, actualización del catálogo, escape de contenido, estados vacíos y acceso desde formularios.

### Publicación de versiones del catálogo

`PublishExamCatalogVersion` crea una versión numerada con snapshot del examen, secciones y parámetros. Publicar una versión de catálogo no publica un resultado clínico. Son dos acciones diferentes.

Al crear la orden se conserva la versión publicada disponible o una definición construida desde el catálogo. Esa copia permite conservar nombres, unidades, referencias y estructura de la solicitud aunque el catálogo cambie después.

### HECES como piloto

La estructura inicial proviene de la hoja HECES de `2025.xlsx` y se mantiene en `config/heces.php`:

| Sección | Parámetros / estructura |
| --- | --- |
| Macroscópico | Color, Aspecto, R. alimenticios, Moco, Sangre y Pseudoparásitos |
| Microscópico | C. vegetal, Jabón, Grasa, Almidón, Levaduras, Eritrocitos, Leucocitos, Crist. de charcot. y Fibra muscular |
| Matriz parasitológica | Organismo, Quiste, Trofozoíto, Huevo y Larva |

La matriz incluye los organismos y el campo «Otros» definidos en la configuración fuente. Los nombres y su orden pueden consultarse allí; no se deducen desde el HTML del reporte.

El catálogo inicial queda con estado clínico pendiente. La extracción del Excel y el funcionamiento del software no aprueban por sí mismos parámetros, referencias ni criterios clínicos. En el PDF operativo las referencias se muestran cuando el parámetro del snapshot figura como validado clínicamente.

## 6. Órdenes y correlativos

Se implementó creación de órdenes para un paciente con uno o varios exámenes activos, edición permitida, consulta, anulación con motivo y selección de médico remitente opcional.

El correlativo conserva una secuencia por año con bloqueo para evitar duplicados. Su formato predeterminado es `LAB-AÑO-000001`. La configuración permite cambiar el prefijo y la cantidad mínima de dígitos, sin reiniciar el contador ni renombrar órdenes existentes.

Al editar una orden, los exámenes seleccionados se recuperan en el formulario. Quitar un examen lo marca como retirado; no destruye su snapshot ni sus resultados. Reincluirlo recupera el registro según su situación previa. No se permite cambiar de paciente después de haber capturado resultados.

### Estados

| Entidad | Estados implementados |
| --- | --- |
| Orden | `pendiente`, `en_proceso`, `validada`, `publicada`, `anulada` |
| Examen de una orden | `pendiente`, `en_proceso`, `validada`, `anulada`, `retirado` |

La orden queda pendiente al crearse. La captura la lleva a proceso. Las órdenes validadas, publicadas o anuladas no se editan libremente. La anulación está permitida para pendientes o en proceso y conserva motivo y actor.

## 7. Captura de resultados

La pantalla `CaptureOrderExamResults` genera campos a partir del snapshot del examen solicitado. No hay un Resource independiente por cada examen clínico.

`SaveOrderExamResultDraft`:

- Rechaza parámetros que no pertenecen al examen de la orden.
- Valida números, texto, opciones y columnas de matriz.
- Guarda resultados estructurados por código de parámetro.
- Conserva el usuario editor.
- Registra revisiones de valores anteriores cuando cambian.
- Impide la captura no autorizada o en un estado bloqueado.

### Matriz sin hallazgos

Se implementaron estados explícitos de sección:

| Estado | Uso |
| --- | --- |
| `pending` | Matriz aún no completada |
| `completed_with_findings` | Hay al menos una celda con hallazgo registrado |
| `completed_without_findings` | Matriz completada con sus celdas vacías |

Un campo vacío no significa automáticamente ausencia de hallazgos. El operador debe elegir el estado correspondiente. Se rechaza marcar «sin hallazgos» cuando existen celdas llenas, y «con hallazgos» cuando todas están vacías.

El PDF operativo presenta «Completada sin hallazgos.» para ese estado, en lugar de una matriz de resultados vacíos. No genera una interpretación clínica automática.

## 8. Validación y reapertura

La decisión vigente es **validar la orden completa**. Se incluyen sus exámenes activos; los retirados o anulados se excluyen.

La validación comprueba que existan resultados requeridos y estados coherentes de matriz. Si faltan datos, la interfaz explica cuáles impiden validar. Al completarse guarda usuario validador, fecha y una versión incremental.

El evento de validación conserva un snapshot de:

- Identidad de la orden y médico remitente.
- Datos del paciente.
- Catálogo utilizado para cada examen.
- Valores y estados de secciones.
- Identidad del validador y fecha.
- Perfil institucional y rutas privadas de sus imágenes.

La reapertura requiere permiso y motivo. Devuelve los exámenes validados a proceso, conserva los eventos históricos y revoca los enlaces públicos activos. La corrección requiere una nueva validación y una nueva publicación para disponer de otro enlace QR.

## 9. Reportes PDF

### Motor operativo

El reporte usa `BuildValidatedOrderReportData` y `RenderValidatedOrderPdf`. Ambos controladores, interno y público, emplean el mismo renderizador. Los resultados provienen del snapshot de validación y se escapan al presentarlos en Blade.

El PDF se genera bajo demanda; no hay un archivo PDF histórico archivado automáticamente por cada emisión.

### Distribución visual actual

| Zona | Contenido |
| --- | --- |
| Encabezado | Nombre institucional, texto secundario, QR cuando está publicado y datos del informe |
| Datos del informe | Hora/fecha, R. Sanitario -DRACES-, orden, paciente, género, edad y doctor |
| Cuerpo | Título del examen, secciones y resultados |
| Pie fijo inferior | Firmas, logo, sello, validador, fecha, dirección, horario, contacto y emergencias |

El logotipo institucional se presenta en el pie. El QR permanece en una esquina del encabezado y no contiene datos del paciente: codifica el enlace de consulta con token.

La fecha del informe corresponde a la validación. La edad se calcula con la fecha de nacimiento del snapshot a esa fecha; usa años, meses o días según corresponda. La fecha de generación del archivo se indica por separado. Los datos ausentes muestran «Sin registrar».

### Papel, tablas y pie fijo

- Carta vertical: 8.5 × 11 pulgadas.
- Márgenes superior y laterales de 15 mm; el margen inferior reserva además el espacio del pie.
- El renderizador hace una primera pasada para medir el pie con sus datos e imágenes reales, convierte su altura a milímetros y añade una separación de 3 mm.
- La segunda pasada genera el documento con ese espacio reservado y el pie fijo en la parte inferior de cada página.
- El pie se incluye antes del contenido en el HTML para que Dompdf pueda repetirlo en las páginas siguientes.
- Las tablas mantienen `thead`/`tbody`, cabeceras repetibles y reglas contra cortes de filas.
- Los documentos extensos pueden paginar; no se usa recorte ni un alto fijo del cuerpo para ocultar resultados.

HECES usa una presentación compacta con pares parámetro/resultado. La selección se basa en el código estable `HECES`, no en que su nombre contenga esa palabra. Otros exámenes conservan la tabla de parámetro, resultado, unidad y referencia; las matrices calculan sus columnas dinámicamente.

Las pruebas generan HECES completo en una página, con y sin hallazgos, QR y recursos institucionales ficticios. Esto no garantiza una página para textos arbitrariamente largos, cualquier catálogo futuro o una orden con muchos exámenes.

### Firmas e integridad histórica

Las firmas son imágenes institucionales configuradas. No son firmas digitales criptográficas ni se seleccionan automáticamente según la identidad profesional del usuario validador. El nombre del validador procede del evento de validación.

Los snapshots conservan el contenido y el perfil utilizado. Sin embargo, la plantilla Blade no tiene todavía un archivo histórico de versiones aplicado a cada emisión: cambios futuros de diseño pueden alterar la apariencia al regenerar reportes anteriores. El campo mostrado `Formato 1` es una etiqueta del renderizador, no un sistema completo de versionado de plantillas.

Las imágenes se referencian por rutas privadas del perfil y se incrustan al generar el PDF. Conservar los archivos originales es necesario para reproducir los recursos de reportes anteriores.

## 10. Publicación y QR

La publicación requiere una orden validada y permiso del validador. `PublishOrderReport` genera un token aleatorio de 64 caracteres ligado a la versión validada.

Se guarda el hash SHA-256 para búsqueda y una copia cifrada con Laravel para recuperar el enlace que se codifica en el QR. Ambos campos se ocultan de la serialización ordinaria del modelo y el token no se agrega a metadatos de auditoría.

La consulta pública funciona como enlace de portador: **cualquier persona que tenga el enlace o escanee el QR puede consultar y descargar el PDF**, sin login ni código adicional, según la decisión operativa adoptada.

Se comprueban estado publicado, versión, revocación y expiración cuando exista. La acción de publicación actual no establece vencimiento por defecto; disponer de `expires_at` no equivale a tener un formulario operativo para configurar vencimientos.

Al revocar se desactiva el enlace y la orden vuelve a validada. Al reabrir también se revocan enlaces. Publicar otra vez crea uno nuevo; el anterior no se reactiva.

Las rutas de descarga incluyen límites de solicitudes y encabezados que evitan almacenamiento normal en caché e indican a buscadores que no indexen ni archiven la respuesta. Son instrucciones de respuesta, no un mecanismo para retirar copias que otra persona ya haya descargado.

### Rutas principales

| Método | Ruta | Control |
| --- | --- | --- |
| GET | `/admin` y páginas internas | Login, usuario activo y políticas |
| GET | `/ordenes/{order}/reporte` | Login y `generateReport` |
| GET | `/ordenes/{order}/reporte?download=1` | Mismos controles, descarga |
| GET | `/consulta/{token}` | Token válido y `throttle:30,1` |
| GET | `/consulta/{token}?download=1` | Consulta pública con descarga |
| GET | `/plantillas/editor` | Login y `Manage:Template` |
| POST | `/plantillas/guardar` | Login, permiso y CSRF |
| GET | `/plantillas/pdf` | Login y `Manage:Template` |

## 11. Historial y búsquedas

La operación de historial se concentra en Órdenes. No se creó otro módulo que duplique su listado.

La búsqueda considera número y estado de la orden, datos del paciente, identificador, teléfono, nombre del examen, códigos/valores capturados y contenido de snapshots de validación. Admite varios términos. Los filtros por estado, examen y rango de fecha de orden se pueden combinar.

El listado se ordena por fecha descendente y ofrece páginas de 10, 25 o 50 registros. Se conservan revisiones de resultados y eventos de validación/reapertura, además de la consulta operativa de cada orden. Esto no implica que exista una pantalla especializada para comparar visualmente todas las versiones históricas de un PDF.

## 12. Configuración del laboratorio

Acceso: **Configuración → Laboratorio**, con `Manage:LabSettings`.

Campos disponibles:

- Nombre y texto secundario del reporte.
- Dirección, teléfono y correo.
- Registro sanitario DRACES.
- Número de emergencias y horario de atención.
- Logo principal, firma del técnico laboratorista, firma de la química bióloga y sello.
- Prefijo del correlativo y dígitos mínimos de secuencia.

Cada guardado crea una nueva versión de `LabProfile` y desactiva la anterior. Los nuevos reportes validados incorporan el perfil vigente; los ya validados conservan el snapshot previo.

### Imágenes privadas

| Recurso | Ruta relativa en disco `local` | Ubicación en PDF |
| --- | --- | --- |
| Logo principal | `lab-profile-logos/` | Pie central |
| Firma técnica | `lab-report-assets/` | Pie izquierdo |
| Firma química bióloga | `lab-report-assets/` | Pie derecho |
| Sello | `lab-report-assets/` | Pie central |

Se aceptan PNG/JPEG de hasta 2 MB. Filament genera nombres de almacenamiento; la acción valida ruta, existencia, MIME y tamaño. El renderizador comprueba nuevamente los recursos admitidos y los incrusta como datos de imagen. No se necesita un enlace público de almacenamiento para estos archivos.

Cambiar una imagen no añade esa imagen retroactivamente a una orden ya validada. Para comprobar nuevas configuraciones, usar una nueva orden ficticia y validarla después de guardar el perfil.

## 13. POC de GrapesJS

Se conserva como módulo separado del flujo operativo de órdenes. Permite arrastrar bloques, modificar estilos, guardar y recuperar el proyecto y generar un PDF de prueba con datos ficticios.

### Persistencia y lienzo

- GrapesJS exporta HTML con `getHtml()` y CSS con `getCss()`.
- El proyecto completo se guarda con `getProjectData()` y se recupera con `loadProjectData()`.
- Los archivos del proyecto se guardan en el disco privado como `poc/heces.html`, `poc/heces.css` y `poc/heces.json`.
- El lienzo muestra carta vertical con guías de márgenes de 15 mm.
- Las tablas nuevas utilizan un ancho porcentual respecto al área útil; se conserva la posibilidad de anchos personalizados.

### Componentes y datos

El componente `tabla-resultados-clinicos` vincula un grupo como `heces.macroscopico` o `heces.microscopico` y configura columnas visibles: parámetro, resultado, unidad y referencia. Su configuración forma parte del JSON del editor.

La vista previa utiliza tres filas ficticias representativas. El servidor genera las filas desde la estructura controlada y no toma el contenido editable como fuente de verdad clínica. También existe un componente especializado de matriz parasitológica y bloques de paciente, títulos, secciones y observaciones.

Las variables de ejemplo incluyen `[[paciente.nombre]]`, `[[orden.codigo]]`, `[[resultado.color]]` y `[[resultado.consistencia]]`; su sustitución utiliza escape de HTML.

### CSS y protección

HTMLPurifier sanea el HTML. El filtro CSS conserva un conjunto controlado de propiedades: colores, tipografía, alineación, dimensiones, márgenes, padding, bordes y sus variantes por lado, y colapso de bordes. Rechaza valores no admitidos y registra las propiedades descartadas para el diagnóstico del editor.

Los recursos mediante `url()`, importaciones y construcciones activas no se habilitan por el filtro. Flexbox, Grid, transformaciones y posicionamiento arbitrario no se añadieron al diseño exportable. El pie fijo del PDF operativo es CSS controlado del servidor y no amplía la lista de CSS editable aceptado por la POC.

El filtro limitado de CSS y el almacenamiento único de HECES siguen siendo elementos de POC; no equivalen a un gestor general de plantillas de producción.

### Prueba extensa cerrada

La POC conserva la opción `prueba_extensa=1` para 60 parámetros ficticios y `resultados_prueba=30` para 30. Las filas se identifican secuencialmente y el modo normal permanece separado. La prueba histórica de 60 filas se dio por cerrada; no es el diseño objetivo para el formato real.

No se implementaron múltiples plantillas clínicas productivas, un selector de plantilla activa por examen ni la conexión del JSON de GrapesJS al PDF de órdenes.

## 14. Modelo de datos

| Entidad / tabla | Responsabilidad y relaciones principales |
| --- | --- |
| `users` | Identidad, contraseña con hash, actividad; roles y permisos |
| Tablas de Spatie | Roles, permisos y asignaciones |
| `patients` | Datos del paciente; tiene órdenes |
| `exams` | Catálogo de exámenes |
| `exam_sections` | Secciones de un examen y configuración de estructura |
| `exam_parameters` | Parámetros de una sección, tipos y referencias |
| `exam_catalog_versions` | Snapshot numerado de catálogo y usuario publicador |
| `orders` | Paciente, número, fechas, estado, creador, médico y observaciones |
| `order_sequences` | Secuencia anual del correlativo |
| `order_exams` | Examen solicitado, versión/snapshot, estado y estados de matriz |
| `result_values` | Valores estructurados por código de parámetro y editor |
| `result_value_revisions` | Valor anterior y usuario que lo modificó |
| `order_validation_events` | Validación/reapertura, versión, actor, motivo y snapshot |
| `report_access_tokens` | Hash/copia cifrada del token, versión, creación y revocación |
| `lab_profiles` | Versiones de la configuración y rutas de recursos privados |
| `audit_logs` | Actor, acción, entidad, fecha y metadatos mínimos |

Las migraciones existentes construyen estas entidades y añaden estados de secciones, metadatos de validación, recursos del PDF y campos institucionales/médico. Las operaciones de retirada, anulación y reapertura conservan registros históricos; no usan la eliminación de resultados como mecanismo operativo.

## 15. Seguridad y auditoría

Controles presentes en código:

- Autenticación del panel, usuarios activos y permisos granulares.
- Políticas del servidor para registros y acciones; Filament usa autorización estricta.
- CSRF para formularios de aplicación.
- Validación de tipos, opciones, parámetros y estados.
- Escape de contenido dinámico en vistas.
- HTML/CSS filtrado para la POC.
- Almacenamiento privado de recursos y tokens cifrados en base de datos.
- Dompdf operativo con recursos remotos, PHP y JavaScript deshabilitados.
- Revocación de enlaces y vinculación con la versión validada.
- Límites de consulta pública y encabezados de privacidad.

`AuditObserver` registra creación, edición y eliminación de los modelos observados. Sus metadatos contienen nombres de campos cambiados, no sus valores completos. Acciones como validar, reabrir, publicar, revocar y crear un perfil generan eventos adicionales. También se registran cambios de asignaciones de roles/permisos.

La auditoría de aplicación no es un registro externo inalterable frente a administradores de la base. HTTPS, permisos del hosting, respaldos, restauración y revisión integral de seguridad siguen requiriendo verificación en el entorno de despliegue.

## 16. Operación del personal

### Administrador

1. Ingresar al panel con usuario activo.
2. Revisar usuarios y roles autorizados.
3. Completar Configuración → Laboratorio y cargar los recursos privados.
4. Revisar catálogo, secciones y parámetros; obtener validación clínica del laboratorio antes de aprobar referencias.
5. Buscar o registrar al paciente.
6. Crear una orden, elegir exámenes y registrar médico remitente cuando exista.
7. Capturar resultados por examen y completar explícitamente el estado de la matriz.
8. Consultar o corregir mientras la orden sea editable.

### Validador

1. Buscar la orden y revisar sus resultados.
2. Usar «Validar orden»; corregir los faltantes informados antes de completar la acción.
3. Abrir «Ver reporte PDF» o «Descargar PDF» una vez validada.
4. Publicar para generar el QR y el enlace público.
5. Si se requiere una corrección posterior, reabrir con motivo. El enlace anterior queda revocado.
6. Tras la corrección, validar y publicar otra vez.

### Técnico con rol inicial

Puede buscar, crear y editar pacientes. No dispone de órdenes ni captura con la asignación inicial actual. Cualquier ampliación debe hacerse como una decisión explícita de permisos.

### Comprobación manual recomendada

Usar exclusivamente datos ficticios:

1. Guardar DRACES, dirección, emergencias y horarios de prueba.
2. Cargar imágenes de prueba para logo, sello y ambas firmas.
3. Crear una orden HECES con fecha de nacimiento y médico de prueba.
4. Capturar todos los parámetros y ensayar tanto matriz con hallazgos como sin hallazgos.
5. Validar, publicar y descargar el PDF.
6. Revisar orden de campos, edad, legibilidad, filas completas y pie inferior fijo.
7. Escanear el QR desde un dispositivo que alcance el dominio de la aplicación.
8. Revocar y comprobar que el enlace deje de permitir acceso.
9. Verificar que un usuario sin permiso no pueda editar ni validar.

Un QR que apunta a un dominio local de Herd puede no ser accesible desde un teléfono sin configuración de red adicional. Eso no prueba por sí mismo un fallo en el token.

## 17. Desarrollo y mantenimiento local

### Archivos y comandos

```bash
composer show --direct
php artisan about --only=environment
php artisan migrate:status
php artisan route:list --except-vendor
php artisan test --compact
vendor/bin/pint --dirty --format agent
npm run build
```

En esta máquina PHP 8.4 se invoca mediante Herd si `php` no está en PATH:

```powershell
& 'C:\Users\Susa\.config\herd\bin\php84\php.exe' artisan test --compact
```

El proyecto define `composer run dev` delegando a `php artisan dev`. Para recursos frontend también dispone de `npm run dev`. Herd ya sirve el sitio: no es necesario iniciar otro servidor HTTP para navegar por el proyecto.

Para una instalación nueva, preparar dependencias, variables de entorno, clave y conexión antes de ejecutar migraciones. No ejecutar inicializaciones o seeders sobre una instalación existente sin revisar sus efectos. `HecesCatalogSeeder` usa `firstOrCreate`: no es una sincronización destructiva de todo el catálogo.

El comando `php artisan lab:create-administrator` crea un administrador de forma **interactiva**, con contraseña oculta y confirmación. No se debe colocar una contraseña conocida en seeders ni documentación.

### Conservación de información

Los respaldos deben contemplar base de datos, archivos privados y gestión segura de `APP_KEY`. La clave se necesita para descifrar los tokens existentes. No copiar secretos a documentación, repositorios públicos ni logs.

Este documento no indica que los respaldos estén automatizados ni que una restauración ya haya sido probada. Ambas tareas están pendientes del despliegue.

## 18. Pruebas y evidencias

Suite completa ejecutada durante esta revisión documental:

```powershell
& 'C:\Users\Susa\.config\herd\bin\php84\php.exe' artisan test --compact
```

**Resultado real del 11/11/2026: 79 pruebas aprobadas, 660 aserciones; duración informada de aproximadamente 43.8 segundos.** La configuración de pruebas usa SQLite en memoria y datos ficticios.

| Archivo de pruebas | Cobertura principal |
| --- | --- |
| `PhaseOneAccessTest.php` | Login/acceso, roles, pacientes y restricciones |
| `PhaseTwoExamCatalogTest.php` | Catálogo, estructura y versiones |
| `PhaseThreeOrdersTest.php` | Órdenes, correlativo, selección/retirada, médico, búsquedas y filtros |
| `PhaseFourResultCaptureTest.php` | Captura, validaciones y revisiones |
| `PhaseFiveOrderValidationTest.php` | Validación completa, bloqueos, estados y reapertura |
| `PhaseSixOrderReportTest.php` | PDF, snapshots, escape, una hoja HECES, pie fijo y compatibilidad estructural |
| `PhaseSevenPublicReportTest.php` | Publicación, QR, consulta pública y revocación |
| `PhaseNineLabSettingsTest.php` | Configuración versionada, recursos privados y conservación del perfil |
| `PlantillaPocControllerTest.php` | Persistencia, CSS filtrado, componentes y datos ficticios de POC |

Las pruebas de maquetación generan PDFs reales con Dompdf y consultan su número de páginas y coordenadas de los bloques. Cubren:

- Una página HECES con QR, firmas, logo, sello y contacto, con/sin hallazgos.
- Pie al borde inferior aun con cuerpo corto.
- Pie repetido en varias páginas y filas fuera de su región reservada.
- Tablas no HECES con texto, números, selección, positivo/negativo y observaciones.
- Varias estructuras de examen dentro de una orden y matrices de dos/seis columnas.
- Código estable de examen para evitar aplicar el formato HECES por coincidencia de nombre.

El último conjunto específico de PDF/configuración/publicación pasó 24 pruebas con 230 aserciones. Pint se ejecutó tras los cambios PHP y terminó correctamente.

Las verificaciones de páginas/coordenadas no sustituyen una revisión visual de impresión con los recursos reales del laboratorio. Tampoco prueban la compatibilidad de todos los formatos clínicos del Excel, MySQL en producción o condiciones del hosting. Durante la sesión se inspeccionó visualmente el PDF de referencia, pero la aprobación visual final del PDF operativo con imágenes reales sigue pendiente.

## 19. Estado de las fases

| Fase del plan original | Situación |
| --- | --- |
| 0 — Auditoría/preparación | Revisión e integración progresiva realizadas; hosting final pendiente |
| 1 — Acceso, roles y pacientes | Implementada con las restricciones de roles adoptadas |
| 2 — Catálogo | Motor configurable y piloto HECES implementados; aprobación clínica pendiente |
| 3 — Órdenes | Implementada |
| 4 — Captura | Implementada para las estructuras actuales |
| 5 — Validación | Implementada por orden completa; reapertura reservada al validador |
| 6 — PDF | Implementado y refinado con pie fijo; revisión visual final pendiente |
| 7 — QR/consulta | Implementada como enlace de portador sin código adicional |
| 8 — Historial | Cubierto dentro de Órdenes con búsqueda y filtros; sin módulo duplicado |
| 9 — Configuración | Perfil, correlativos y recursos implementados; selector de plantilla por examen pendiente |
| 10 — Pruebas integrales | Suite automatizada disponible; aceptación integral clínica y otros formatos pendiente |
| 11 — Hostinger | Despliegue y comprobación en destino pendientes |
| 12 — Aceptación/entrega | Este documento consolida el estado; aceptación formal y manuales finales pendientes |

## 20. Pendientes y límites

1. **Catálogo clínico:** confirmar parámetros, referencias y estructuras con el responsable del laboratorio. HECES es el piloto, no aprobación del resto del Excel.
2. **Otros formatos:** incorporar y validar ORINA y formatos especiales como cultivos. La compatibilidad del motor compartido no implica que su captura, catálogo y PDF particulares ya estén terminados.
3. **GrapesJS productivo:** definir versiones de plantillas, selección por examen e integración segura con snapshots si se autoriza conectar la POC al flujo operativo.
4. **Documento histórico exacto:** completar el versionado de diseño o archivo del PDF emitido si se exige reproducir exactamente la misma apariencia/binario en el futuro.
5. **Firmas:** validar recursos autorizados e identidad profesional. Las imágenes actuales son recursos del perfil del laboratorio, no firma criptográfica ni asociación individual automática.
6. **Imágenes históricas:** conservar archivos privados referenciados. No hay un gestor completo de retirada/archivo de recursos.
7. **Maquetación final:** revisar PDFs reales con los logos y firmas suministrados, nombres extensos, referencias largas y textos clínicos representativos. No garantizar una hoja con contenido ilimitado.
8. **Horario y fechas:** confirmar la zona horaria operativa; actualmente la aplicación local está en UTC. No confundir la zona de la máquina del operador con la de Laravel.
9. **Publicación:** no hay vencimiento automático predeterminado ni una política adicional de segundo factor; el enlace de portador es la decisión vigente.
10. **Hosting:** verificar PHP/extensiones, MySQL, rutas privadas, HTTPS, depuración desactivada, cookies/sesiones, permisos, compilación y rendimiento de las dos pasadas del PDF en Hostinger.
11. **Operación segura:** completar respaldos, restauración probada, monitoreo y revisión de seguridad antes de trabajar con información real.
12. **Repositorio:** la revisión encontró muchos archivos de la implementación todavía sin seguimiento de Git y modificaciones locales. Tenerlos en el directorio no significa que estén confirmados en Git o publicados en un remoto; su consolidación es una tarea separada.

No se implementaron facturación, cobros, inventario, integración con analizadores, WhatsApp/SMS, interpretación clínica automática, aplicación móvil, multiempresa ni otras funciones fuera del alcance autorizado.
