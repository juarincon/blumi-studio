# Blumi Studio — Contrato MVP de componentes y build

## Regla principal

Un componente aprobado debe estar listo para usarse y publicarse. El builder de páginas no es un diseñador del componente: es un ensamblador de piezas.

## Component Factory

La IA recibe una referencia y devuelve una sola pieza con:

- HTML del módulo.
- CSS completo.
- JS únicamente cuando la interacción lo requiere.
- Schema de campos reemplazables.
- Responsive desktop/tablet/mobile ya resuelto.

Breakpoints:

- Desktop: base.
- Tablet: `max-width: 900px`.
- Mobile: `max-width: 600px`.

## Builder de páginas

En una instancia solo se cambian campos declarados por el módulo, por ejemplo:

- copy;
- texto de botón;
- URL;
- imagen;
- logo;
- número.

La estructura, responsive y comportamiento pertenecen al componente aprobado.

## Formularios

Durante el MVP los formularios son visuales. Deben tener:

```html
<form data-blumi-form="frontend" action="#" method="post">
```

No se crea procesamiento PHP, correo, API, base de datos ni fetch.

## Salida de una página

Blumi genera una carpeta autónoma:

```text
index.html
assets/
  css/site.css
  js/site.js
  img/...
  fonts/...
uploads/...  (solo cuando un recurso referenciado vive allí)
```

El ZIP contiene esa misma estructura y puede subirse a un hosting estático o servir de base para integración posterior.
