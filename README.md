# Formularios de transferencias bancarias — 6 países
**PHP 8 · Azure App Service (Linux) · preparado para Azure SQL / SQL Server**

> **Etapa actual: estructura SIN base de datos.**
> Todo funciona (formulario, login por país, filtros, checklist) guardando en
> archivos JSON. Cuando se conecte SQL Server sólo se cambia una variable
> (`DATA_DRIVER=sqlsrv`); el resto del código no se toca.

| País | Idioma | Moneda |
|---|---|---|
| Honduras | Español | HNL |
| El Salvador | Español | USD |
| Nicaragua | Español | NIO |
| Guyana | Inglés | GYD |
| Suriname | Inglés | SRD |
| St. Lucia | Inglés | XCD |

---

## 1. Estructura

```
transferencias/                    ← esto es lo que se despliega en /home/site/wwwroot
├── index.php                      redirige a /frontend/
├── frontend/                      ← lo que ve el usuario
│   ├── config.php                 URL del backend (api_base) y países
│   ├── index.php                  selección de país
│   ├── formulario.php?pais=...    formulario (ES / EN según país)
│   ├── panel/login.php            login del panel
│   ├── panel/index.php            panel: filtros + checklist
│   ├── lang/es.php · en.php       textos del formulario
│   └── assets/css · assets/js
├── backend/                       ← API en PHP (responde JSON)
│   ├── api/
│   │   ├── countries.php · banks.php
│   │   ├── submit.php             recibe formulario + comprobante
│   │   ├── auth/ login · me · logout
│   │   └── transfers/ list · check · receipt · export
│   ├── config/
│   │   ├── config.php             todo se configura con variables de entorno
│   │   └── catalog.php            países y bancos (modo sin BD)
│   ├── lib/
│   │   ├── bootstrap.php · auth.php · transfers.php
│   │   └── Repositories/
│   │       ├── TransferRepository.php   contrato (interfaz) de datos
│   │       ├── JsonRepository.php       ← ACTUAL: sin base de datos
│   │       └── SqlServerRepository.php  ← FUTURO: Azure SQL (ya escrito)
│   ├── lang/                      mensajes de validación ES / EN
│   ├── database/
│   │   ├── schema.sql             tablas + triggers para SQL Server (siguiente etapa)
│   │   └── permissions.sql        usuario de la app con permisos mínimos
│   ├── scripts/create_user.php    crear usuarios del panel
│   └── storage/                   datos y comprobantes en ambiente local
└── deploy/                        ← configuración de Azure App Service
    ├── startup.sh                 comando de inicio
    ├── nginx/default              bloquea carpetas internas, límite 12 MB
    └── php/custom.ini             límites de subida de PHP
```

### Cómo está separada la base de datos
Todos los endpoints usan `repo()`, que devuelve la implementación según `DATA_DRIVER`:

| DATA_DRIVER | Dónde guarda | Estado |
|---|---|---|
| `json` (por defecto) | `users.json`, `transfers.json`, `audit.log` en `DATA_DIR` | **En uso** |
| `sqlsrv` | Azure SQL / SQL Server | Listo, sin conectar |

---

## 2. Probar en tu computadora (sin base de datos)
Requiere PHP 8.1+ con `fileinfo` y `mbstring`.
```bash
cd transferencias
php backend/scripts/create_user.php --country=honduras --username=hn.admin --name="Admin Honduras"
php -S localhost:8000
```
- Formulario: http://localhost:8000/frontend/
- Panel: http://localhost:8000/frontend/panel/login.php

> El servidor `php -S` es sólo para desarrollo: no aplica los bloqueos de carpetas.

---

## 3. Despliegue en Azure App Service (Linux)

1. **Crear el App Service**: Linux, pila **PHP 8.3** (o 8.2). Activar **Sólo HTTPS**.
2. **Configuración > Variables de entorno (App settings):**

   | Nombre | Valor |
   |---|---|
   | `DATA_DRIVER` | `json` |
   | `PHP_INI_SCAN_DIR` | `/usr/local/etc/php/conf.d:/home/site/wwwroot/deploy/php` |
   | `APP_TZ` | `America/Tegucigalpa` |

   (`DATA_DIR`, `UPLOAD_DIR` y `SESSION_PATH` toman por defecto `/home/data/transferencias/...`)
3. **Configuración > Comando de inicio:**
   ```
   bash /home/site/wwwroot/deploy/startup.sh
   ```
4. **Desplegar** el contenido de la carpeta `transferencias/` (sus archivos en la raíz del zip) con
   Deployment Center (GitHub/Azure DevOps), VS Code o:
   ```bash
   cd transferencias && zip -r ../deploy.zip . -x "*.git*" "backend/storage/data/*" "backend/storage/uploads/*"
   az webapp deploy -g <grupo> -n <app> --src-path ../deploy.zip --type zip
   ```
5. **Crear usuarios del panel** desde SSH (Portal > Herramientas de desarrollo > SSH):
   ```bash
   php /home/site/wwwroot/backend/scripts/create_user.php --country=honduras    --username=hn.admin --name="Admin Honduras"
   php /home/site/wwwroot/backend/scripts/create_user.php --country=el-salvador --username=sv.admin --name="Admin El Salvador"
   php /home/site/wwwroot/backend/scripts/create_user.php --country=nicaragua   --username=ni.admin --name="Admin Nicaragua"
   php /home/site/wwwroot/backend/scripts/create_user.php --country=guyana      --username=gy.admin --name="Guyana Admin"
   php /home/site/wwwroot/backend/scripts/create_user.php --country=suriname    --username=sr.admin --name="Suriname Admin"
   php /home/site/wwwroot/backend/scripts/create_user.php --country=st-lucia    --username=lc.admin --name="St. Lucia Admin"
   ```

### Puntos importantes de App Service
- **Datos y comprobantes en `/home/data/...`**: es almacenamiento persistente y compartido entre
  instancias, y está fuera de `wwwroot`, así que un nuevo despliegue **no** los borra.
- **Nginx**: App Service Linux usa Nginx, por eso los `.htaccess` no aplican; la protección de
  carpetas está en `deploy/nginx/default` (se activa con el comando de inicio).
- **Subidas de 10 MB**: Nginx (`client_max_body_size 12M`) y PHP (`custom.ini`) ya están ajustados.
- **HTTPS / IP real**: el código lee `X-Forwarded-Proto` y `X-Forwarded-For` del balanceador de Azure.

---

## 4. Siguiente etapa: conectar Azure SQL
1. Ejecutar `backend/database/schema.sql` con SSMS / Azure Data Studio / sqlcmd.
2. Ejecutar `backend/database/permissions.sql` (recomendado: Managed Identity).
3. En **Redes** del servidor SQL permitir el App Service (VNet / endpoint privado o "Permitir servicios de Azure").
4. Cambiar App settings:

   | Nombre | Valor |
   |---|---|
   | `DATA_DRIVER` | `sqlsrv` |
   | `DB_HOST` | `<servidor>.database.windows.net` |
   | `DB_NAME` | `<base de datos>` |
   | `DB_AUTH` | `msi` (Managed Identity) o `sql` |
   | `DB_USER` / `DB_PASS` | sólo con `sql` — usar referencia a Key Vault |

5. Volver a crear los usuarios del panel con `create_user.php` (ya quedarán en SQL).
   Los países y bancos tienen los mismos IDs en `catalog.php` y en `schema.sql`.

En SQL Server las reglas se refuerzan con **triggers**: no se puede borrar una transferencia,
el check no se puede quitar, los datos del formulario no se pueden editar y la bitácora es de sólo lectura.

---

## 5. Funcionalidad

### Formulario (10 preguntas, todas obligatorias)
1 Correo registrado en Apostemos · 2 Nombre completo · 3 DNI · 4 Código de depósito ·
5 Banco (con mensaje *"No se pudo cargar la lista de bancos…"* y botón Reintentar) ·
6 Fecha (no futura) · 7 Monto (2 decimales) · 8 Referencia bancaria ·
9 Comprobante (1 archivo PDF/imagen, 10 MB, validado por tipo real) · 10 Titularidad.
Entrega un **folio** (ej. `HN-000123`) y bloquea referencias duplicadas por banco y país.

### Panel por país
- Cada usuario pertenece a **un país** y sólo ve ese país (validado en el servidor).
- Idioma del panel según el país del usuario.
- Filtros: referencia bancaria, código de depósito, DNI, nombre/correo, folio, banco,
  estado, monto y rango de fechas. Ver comprobante, paginación, contadores y exportar a CSV.

### Checklist irreversible
- La pantalla pide confirmación; luego la casilla queda bloqueada con quién y cuándo verificó.
- La API sólo tiene "verificar": no hay endpoints para desmarcar, editar ni eliminar (responde 409 si ya estaba verificada).
- Ambos repositorios (JSON y SQL) sólo permiten pasar de pendiente a verificada.

### Seguridad
Contraseñas con `password_hash`, bloqueo tras 5 intentos (15 min), sesión `HttpOnly` con expiración
de 30 min, token CSRF, comprobantes con nombre aleatorio fuera del sitio público, bitácora de acciones.

---

## 6. Pendientes con el negocio
- Confirmar los bancos de El Salvador, Nicaragua, Guyana, Suriname y St. Lucia (`config/catalog.php`).
- Formato de DNI por país (hoy: 4–30 letras, números o guiones).
- Evaluar Azure Blob Storage para los comprobantes cuando el volumen crezca.
#   D e p o s i t o B a n c a r i o  
 