# PostgreSQL 17.7 - Desarrollo Local con Docker

Este directorio contiene la configuración de Docker para una instancia local de PostgreSQL 17.7 para desarrollo.

## 📋 Requisitos

- Docker Desktop instalado y ejecutándose
- PowerShell 5.0 o superior (en Windows)

## 📁 Estructura

```
dev_pgdb/
├── Dockerfile              # Imagen de PostgreSQL 17.7
├── docker-compose.yml      # Configuración de Docker Compose
├── .env                    # Variables de entorno
├── data/                   # Directorio para datos persistentes (generado)
└── scripts/
    ├── up.ps1             # Levantar PostgreSQL
    ├── down.ps1           # Apagar PostgreSQL
    ├── clean.ps1          # Limpiar datos
    └── rebuild.ps1        # Reconstruir imagen sin cache
```

## 🚀 Uso

### Levantar PostgreSQL

```powershell
.\scripts\up.ps1
```

Para compilar la imagen antes de levantar:

```powershell
.\scripts\up.ps1 -Build
```

### Apagar PostgreSQL

```powershell
.\scripts\down.ps1
```

### Limpiar todos los datos

```powershell
.\scripts\clean.ps1
```

Para limpiar sin confirmación:

```powershell
.\scripts\clean.ps1 -Force
```

### Reconstruir la imagen sin cache

```powershell
.\scripts\rebuild.ps1
```

## 📊 Configuración de conexión

- **Host**: `localhost`
- **Puerto**: `5432`
- **Base de datos**: `utamed`
- **Usuario**: `utamed`
- **Contraseña**: `utamed_dev_password`

### En Laravel (.env)

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=utamed
DB_USERNAME=utamed
DB_PASSWORD=utamed_dev_password
```

## 🔧 Personalizar configuración

Edita `dev_pgdb/.env` para cambiar:

```env
POSTGRES_DB=utamed
POSTGRES_USER=utamed
POSTGRES_PASSWORD=utamed_dev_password
DB_PORT=5432
```

## 💾 Datos persistentes

Los datos se almacenan en la carpeta `data/` que se crea automáticamente. Esta carpeta está en `.gitignore` y se sincroniza con el volumen de Docker.

## 🐛 Solución de problemas

### PostgreSQL no responde

1. Verifica que Docker esté ejecutándose:

    ```powershell
    docker ps
    ```

2. Revisa los logs:

    ```powershell
    docker-compose logs postgres
    ```

3. Reconstruye sin cache:
    ```powershell
    .\scripts\rebuild.ps1
    ```

### Puerto 5432 ya en uso

Cambia el puerto en `dev_pgdb/.env`:

```env
DB_PORT=5433
```

Luego actualiza `DB_PORT` en tu `.env` principal también.

## 🧹 Limpiar completamente

Para remover contenedor, imagen y datos:

```powershell
.\scripts\clean.ps1
docker rmi utamed-postgres:latest
```
