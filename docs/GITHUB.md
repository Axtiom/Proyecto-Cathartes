# GitHub: organización, ramas y commits

## 1. Crear la organización y los repositorios
1. En GitHub: *Your organizations* → *New organization* → plan gratuito, nombre `axtiom`
   (o el que esté libre).
2. Invitar a los tres integrantes como *Owners* o *Members*.
3. Crear el repositorio `Proyecto-Cathartes` dentro de la organización (privado o público
   según indique el docente) **sin** README inicial.
4. Subir el proyecto:
   ```bash
   cd Proyecto-Cathartes-main
   git init -b main
   git add .
   git commit -m "chore: versión inicial del proyecto"
   git remote add origin git@github.com:axtiom/Proyecto-Cathartes.git
   git push -u origin main
   ```
5. En *Settings → Branches*, proteger `main`: exigir Pull Request y 1 aprobación.
   Es lo que pide el Reglamento Interno (no se pushea directo a `main`).

## 2. Flujo de trabajo
- Una rama por tarea: `feat/api-camiones`, `fix/login-redireccion`, `docs/modelo-fisico`.
- Se trabaja en la rama, se hace commit seguido y se abre un Pull Request hacia `main`.
- Otro integrante revisa antes de aprobar y mergear.
- Cada cambio relevante se anota en `CHANGELOG.md` y se avisa al grupo.

## 3. Convención de commits
`tipo: descripción corta en presente`

| Tipo | Uso |
|---|---|
| `feat` | funcionalidad nueva |
| `fix` | corrección de un error |
| `docs` | documentación |
| `db` | cambios en el esquema o los datos de prueba |
| `refactor` | reorganizar código sin cambiar el comportamiento |
| `chore` | tareas de mantenimiento |

Ejemplos: `feat: endpoint POST /camiones`, `db: agrega dump de estructura`.

## 4. Qué no se sube
Los archivos `.env` (contraseñas y secreto de tokens) están en `.gitignore`.
Solo se versionan los `.env.example`, que **no** deben tener credenciales reales.
