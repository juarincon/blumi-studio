# Blumi Component Standard v1

Todo componente creado por Blumi AI debe cumplir estas reglas antes de aprobarse.

## Identidad de biblioteca
- Blanco, negro y grises como presentación neutral.
- Copy de muestra positivo, creativo y en español.
- Recursos ajenos se sustituyen por placeholders o recursos Blumi.
- La referencia define función y composición, no identidad literal.

## Tipografía
Tokens permitidos:
`--font-display-xl`, `--font-display`, `--font-h1`, `--font-h2`, `--font-h3`, `--font-h4`, `--font-body-lg`, `--font-body`, `--font-small`, `--font-caption`.

Pesos: 400, 500, 600, 700.

Escala base desktop / tablet / mobile:
- display-xl: 72 / 60 / 44
- display: 60 / 52 / 40
- h1: 48 / 42 / 36
- h2: 40 / 36 / 32
- h3: 32 / 28 / 26
- h4: 24 / 22 / 20
- body-lg: 18 / 18 / 17
- body: 16 / 16 / 16
- small: 14 / 14 / 14
- caption: 12 / 12 / 12

## Spacing
Usar `--space-1`, `--space-2`, `--space-3`, `--space-4`, `--space-5`, `--space-6`, `--space-8`, `--space-10`, `--space-12`, `--space-16`, `--space-20`, `--space-24`.

## Colores
Usar exclusivamente tokens `--site-*`. Un componente de biblioteca no debe traer colores de marca incrustados.

## Radios
`--radius-sm`, `--radius-md`, `--radius-lg`, `--radius-xl`, `--radius-pill`, `--site-radius`.

## Responsive
- 360px mínimo.
- Tablet: 900px.
- Mobile: 600px.
- Todo componente generado debe incluir adaptación `@media`.

## Código
- Sin PHP.
- Sin dependencias externas.
- Sin CDN.
- JS solo cuando la función lo requiera.
- Los componentes aprobados son inmutables.
- Para modificar: duplicar + prompt -> nueva variante.


## Modo referencia estricta (v0.5.2)

Para componentes creados desde imagen, la referencia es el plano estructural.

Reglas obligatorias:

- conservar cantidad de elementos, filas, columnas y bloques de texto;
- conservar jerarquía y alineación;
- conservar proporciones y densidad visual de forma aproximada;
- no agregar contenido estructural que no exista en la referencia;
- reemplazar identidad visual ajena por identidad Blumi;
- mantener copy con longitud aproximada equivalente (+/-20% como objetivo);
- cada logo o imagen repetida debe convertirse en un campo `image` independiente;
- esos campos deben poder reemplazarse en el builder con SVG, WebP, PNG o JPG;
- la referencia define estructura; Blumi define piel visual y contenido de muestra.
