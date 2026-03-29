# Backend IMAF - Sistema de Gestión de Cursos y Estudiantes

## Descripción del Proyecto

Sistema de gestión de cursos y estudiantes para el **Instituto de la Mujer, Atención a la Familia y Formación para el Trabajo (IMAF)**. Desarrollado por un equipo de estudiantes de la UPTYAB como parte de un proyecto sociotecnológico.

Este repositorio contiene el backend o lado servidor del sistema de gestión de cursos y estudiantes del IMAF. El sistema proporciona una plataforma completa para la administración de cursos educativos, gestión de usuarios y seguimiento del progreso académico, así como la gestión de inscripción, seguimiento y administración de cursos impartidos por el instituto, facilitando la interacción entre administradores, instructores y estudiantes.

### Roles del Sistema

El sistema cuenta con tres roles principales:

#### 🎓 **Estudiante**
- Usuario que se registra en la plataforma
- Puede inscribirse en los cursos disponibles
- Solo puede cursar los cursos después de realizar el pago correspondiente
- Accede al contenido educativo y seguimiento de su progreso

#### 👨‍🏫 **Instructor** (profesor)
- Profesor a cargo de los cursos
- Gestiona a los alumnos inscritos en sus cursos
- Define y registra el estado de aprobación de los estudiantes
- Administra el contenido y evaluaciones del curso

#### 👤 **Administrador**
- Gestión general del sistema
- Creación y administración de cursos
- Creación y gestión de instructores
- Validación de comprobantes de pago de los estudiantes
- Supervisión general de la plataforma

## Tecnologías Utilizadas

- **PHP 8.x** - Lenguaje de programación principal
- **Laravel 10.x** - Framework PHP para el desarrollo del backend
- **MySQL** - Sistema de gestión de base de datos
- **Composer** - Gestor de dependencias de PHP
- **JWT (JSON Web Tokens)** - Autenticación y autorización
- **Eloquent ORM** - Mapeo objeto-relacional
- **Laravel Sanctum** - Autenticación de API
- **Swagger/OpenAPI** - Documentación de API
- **PHPUnit** - Pruebas unitarias

## Requisitos Previos

- PHP 8.1 o superior
- Composer instalado
- MySQL/MariaDB
- Git

## Instalación y Configuración

### 1. Clonar el Repositorio

```bash
git clone https://github.com/tu-usuario/backend-imaf.git
cd backend-imaf
```

### 2. Instalar Dependencias

```bash
composer install
```

### 3. Configurar Variables de Entorno

```bash
cp .env.example .env
php artisan key:generate
```

Edita el archivo `.env` y configura las siguientes variables:

```env
DB_DATABASE=imaf_db
DB_USERNAME=root
DB_PASSWORD=tu_contraseña

JWT_SECRET=tu_jwt_secret_secreto
```

### 4. Configurar Base de Datos

Crea una base de datos MySQL con el nombre configurado en `.env` y ejecuta las migraciones:

```bash
php artisan migrate
```

### 5. Ejecutar Seeders (Datos Iniciales)

```bash
php artisan db:seed
```

### 6. Iniciar el Servidor de Desarrollo

```bash
php artisan serve
```

El servidor estará disponible en `http://localhost:8000`

## Endpoints Principales

### Autenticación
- `POST /api/auth/login` - Inicio de sesión
- `POST /api/auth/register` - Registro de usuarios
- `POST /api/auth/logout` - Cierre de sesión

### Administración
- `GET /api/admin/courses` - Listar cursos
- `POST /api/admin/courses` - Crear curso
- `PUT /api/admin/courses/{id}` - Actualizar curso
- `GET /api/admin/users` - Listar usuarios
- `POST /api/admin/instructors` - Crear instructor

### Estudiantes
- `GET /api/student/courses` - Cursos disponibles
- `POST /api/student/enroll/{courseId}` - Inscribirse en curso
- `POST /api/student/payment` - Subir comprobante de pago

### Instructores
- `GET /api/instructor/my-courses` - Mis cursos
- `GET /api/instructor/students/{courseId}` - Alumnos del curso
- `PUT /api/instructor/student-status/{studentId}` - Actualizar estado

## Estructura del Proyecto

```
backend-imaf/
├── app/
│   ├── Http/Controllers/     # Controladores
│   ├── Models/              # Modelos Eloquent
│   ├── Http/Middleware/     # Middleware
│   └── Jobs/                # Trabajos en cola
├── database/
│   ├── migrations/          # Migraciones de BD
│   └── seeders/             # Datos de prueba
├── routes/
│   └── api.php              # Rutas de la API
├── tests/                   # Pruebas unitarias
└── .env                     # Variables de entorno
```