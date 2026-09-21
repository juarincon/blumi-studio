# Contexto para auditoría técnica

## Objetivo

Revisar Blumi Studio como producto en desarrollo, no como sistema terminado. La implementación ha evolucionado rápidamente y se utilizó IA intensivamente para acelerar prototipado e implementación.

La revisión buscada es técnica: arquitectura, mantenibilidad, seguridad, deuda, escalabilidad y claridad de responsabilidades.

## Áreas que interesa revisar

1. **Separación de responsabilidades**
   - Controllers vs Services vs Repositories.
   - Lógica de dominio que haya quedado demasiado cerca de Views o Controllers.

2. **RenderService y pipeline de render**
   - Acoplamiento entre Canvas, preview, build y publicación.
   - Posibles responsabilidades excesivas.

3. **Builder en JavaScript vanilla**
   - Estado del editor.
   - Complejidad acumulada.
   - Umbral a partir del cual convendría modularizar más el frontend.

4. **Persistencia de componentes**
   - Maestro vs instancia.
   - Inmutabilidad de componentes aprobados.
   - Variantes y versionado.

5. **Component Factory / IA**
   - Separación entre intención, generación, validación y persistencia.
   - Robustez frente a respuestas inválidas.
   - Límites de seguridad del HTML/CSS/JS generado.

6. **Assets**
   - Duplicación entre uploads, builds y sitios publicados.
   - Ciclo de vida y limpieza.
   - Integridad referencial entre DB y filesystem.

7. **Build y publicación**
   - Atomicidad.
   - Resolución de rutas y Smart Links.
   - Consistencia entre preview y resultado publicado.

8. **Seguridad**
   - Auth, roles y autorización por operación.
   - CSRF.
   - Uploads.
   - XSS / sanitización.
   - Integración con OpenAI.
   - Actualizador interno.

9. **Base de datos**
   - Relaciones por ID/FK.
   - Uso de JSON vs columnas normalizadas.
   - Soft delete.
   - Migraciones y evolución del esquema.

10. **Testing**
    - Actualmente no existe una suite automatizada formal suficiente.
    - Identificar qué pruebas deberían priorizarse: unitarias, integración, render snapshots, build y seguridad.

## Deuda técnica conocida / hipótesis a validar

- El Builder ha crecido bastante sobre JavaScript vanilla y puede requerir una estrategia de modularización más explícita.
- RenderService y BuildService son candidatos naturales a revisión por tamaño y cantidad de responsabilidades.
- El filesystem de componentes/builds/assets ha evolucionado por fases y necesita validar su modelo de ciclo de vida.
- Las validaciones del código generado por IA deben considerarse una frontera de seguridad importante.
- La documentación histórica conserva decisiones de fases anteriores y no siempre representa por sí sola el diseño actual.
- La cobertura de pruebas automatizadas todavía es insuficiente.

## Pregunta central

> Si Blumi tuviera que crecer de prototipo funcional a producto mantenible por un equipo, ¿qué responsabilidades, límites y pruebas deberían cambiar primero sin reescribir innecesariamente lo que ya funciona?
