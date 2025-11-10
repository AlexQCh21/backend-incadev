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

### 5. Ejecutar seed

```bash
php artisan db:seed --class="IncadevUns\CoreDomain\Database\Seeders\IncadevSeeder"
```

### 6. Ejecutar el servidor

```bash
php artisan serve
```

---

## Probar la correcta integración con Techproc-backend

### PRUEBA DE LOGIN

Probar con el siguiente endpoint para verificar la autenticación

```bash
http://localhost:8000/api/test
```

### PRUEBA DE ROLES

1. Inicia sesión con:

```bash
{
  "email": "elena.viewer@incadev.com",
  "password": "password",
  "role": "system_viewer"
}
```

en el endpoint:

```bash
http://localhost:8001/api/auth/login
```

2. Probar la siguiente ruta tipo GET:

```bash
http://localhost:8000/api/dashboard/groups
```