# Backend INCADEV - BACKEND DEL GRUPO 1

---

## Instalación y Configuración

### 1. Clonar el repositorio

```bash
git clone <URL_DEL_REPOSITORIO>
cd backend-incadev
```

### 2. Instalar dependencias

```bash
composer install
```

### 3. Configurar el archivo .env

**IMPORTANTE**: Usa la misma `APP_KEY` compartida en el grupo de WhatsApp de Jefes.

```env
APP_KEY=base64:+ND2YGIDh/angHUmvvU0fjP0WYB7rsoI+uahYK+x0/E=
```

### 4. Ejecutar migraciones

```bash
php artisan migrate
```
---

### 5. Ejecutar seed

```bash
php artisan db:seed --class="IncadevUns\CoreDomain\Database\Seeders\IncadevSeeder"
```

### 6. Ejecutar el servidor

```bash
php artisan serve
```
