<?php
namespace App\Services;

use RuntimeException;

final class AIComponentService
{
    public function generateComponent(?string $imageFile, string $category, string $compiledIntent=''): array
    {
        $prompt = $this->basePrompt($category, $compiledIntent, null);
        return $this->request($prompt, $imageFile);
    }

    public function generateFromReference(string $imageFile, string $category, string $instruction=''): array
    {
        return $this->generateComponent($imageFile,$category,$instruction);
    }

    public function createVariant(array $source, string $instruction): array
    {
        $context = "COMPONENTE BASE HTML:\n".$source['html_template']."\n\nCSS:\n".($source['css']??'')."\n\nJS:\n".($source['js']??'')."\n\nSCHEMA:\n".($source['schema_campos']??'{}');
        $prompt = $this->basePrompt((string)$source['categoria_nombre'], $instruction, $context);
        return $this->request($prompt, null);
    }


    /**
     * Propone un ajuste visual local sobre targets ya existentes.
     * Nunca devuelve HTML/CSS libre: solo una interpretación y operaciones estructuradas.
     *
     * @param array<int,string> $targets
     */
    public function proposeCustomization(array $instance, array $targets, string $instruction): array
    {
        $instruction = trim($instruction);
        if ($instruction === '') throw new RuntimeException('Describe qué quieres ajustar.');
        if ($targets === []) throw new RuntimeException('Selecciona al menos un elemento del componente.');

        $allowedTargets = implode("\n- ", array_map('strval', $targets));
        $html = (string)($instance['html_template'] ?? '');
        $css = (string)($instance['css'] ?? '');
        $category = (string)($instance['categoria_nombre'] ?? $instance['categoria_slug'] ?? 'Componente');
        $isGlobal = in_array(strtolower((string)($instance['categoria_slug'] ?? '')), ['navbar','footer'], true);
        $scopeNote = $isGlobal
            ? 'ALCANCE GLOBAL: este Navbar/Footer pertenece al layout global del proyecto. El cambio aprobado se verá en todas las páginas. Mantén intacta su función de navegación y modifica únicamente los targets seleccionados.'
            : 'ALCANCE LOCAL: el cambio afecta solo esta instancia de página.';

        $prompt = <<<TXT
Eres el motor de PERSONALIZAR CON BLUMI. No estás creando un componente nuevo.
Debes proponer únicamente ajustes visuales locales sobre elementos YA EXISTENTES del componente seleccionado.

ALCANCE DE ESTA PERSONALIZACIÓN:
{$scopeNote}

REGLAS DE NEGOCIO OBLIGATORIAS:
- Conserva estrictamente la identidad, intención visual y estructura del componente.
- NO rehagas, rediseñes ni reinventes el componente.
- NO agregues nodos, texto, imágenes, botones, cards, secciones, funcionalidades ni JavaScript.
- NO elimines nodos. Puedes ocultar visualmente un target solo si la instrucción lo pide de forma clara.
- NO cambies contenido textual ni URLs.
- NO cambies font-family ni introduzcas tipografías.
- NO introduzcas colores arbitrarios. Si el usuario pide color, usa exclusivamente var(--site-primary), var(--site-secondary), var(--site-accent), var(--site-background), var(--site-surface), var(--site-text) o var(--site-muted).
- Trabaja solo sobre los TARGETS AUTORIZADOS listados abajo. No inventes selectores.
- Respeta los breakpoints de Blumi: base, desktop, tablet, mobile. Desktop >=1200px, tablet 768-1199px, mobile <=767px.
- Si el usuario menciona una pantalla concreta, aplica el cambio al breakpoint correspondiente. Si no, usa base.
- Prioriza spacing, alineación, tamaño, ancho, posición y distribución usando lo que ya existe.
- Un ajuste como "más aire", "alinea con el navbar", "muévelo a la derecha" o "centra esto" debe traducirse a cambios visuales mínimos y razonables.
- No uses !important, url(), @import, valores de color HEX/RGB/HSL ni CSS libre.
- Si la petición requiere un componente nuevo o una modificación conceptual, devuelve requires_variant=true y operations vacío.

PROPIEDADES PERMITIDAS:
margin, margin-top, margin-right, margin-bottom, margin-left,
padding, padding-top, padding-right, padding-bottom, padding-left,
gap, row-gap, column-gap, width, min-width, max-width, height, min-height, max-height,
display, flex-direction, flex-wrap, justify-content, align-items, align-content, align-self, justify-self,
order, grid-template-columns, grid-template-rows, grid-column, grid-row,
position, top, right, bottom, left, transform, border-radius, box-shadow, opacity,
object-fit, object-position, text-align, line-height, letter-spacing, font-size, font-weight,
color, background, background-color, border-color, visibility.

TARGETS AUTORIZADOS:
- {$allowedTargets}

CATEGORÍA:
{$category}

HTML DEL COMPONENTE (solo contexto, NO lo reescribas):
{$html}

CSS ACTUAL DEL COMPONENTE (solo contexto):
{$css}

INSTRUCCIÓN DEL USUARIO:
{$instruction}

Devuelve SOLO JSON válido con esta forma exacta:
{
  "requires_variant": false,
  "interpretation": {
    "summary": "Una frase breve de lo que entendiste",
    "will_change": ["cambio 1", "cambio 2"],
    "will_keep": ["contenido", "paleta", "tipografía", "estructura"],
    "scope": "base|desktop|tablet|mobile|mixto"
  },
  "patch": {
    "operations": [
      {"target":"TARGET EXACTO", "breakpoint":"base", "property":"padding-left", "value":"var(--space-6)"}
    ]
  }
}

Si requiere variante, devuelve requires_variant=true, explica el motivo en interpretation.summary y usa "operations": [].
TXT;
        return $this->request($prompt, null);
    }

    private function basePrompt(string $category, string $instruction, ?string $existing): string
    {
        $placeholder='assets/img/blumi-component-placeholder.svg';
        $creative=require dirname(__DIR__,2).'/config/blumi_creative.php';
        $copies=implode(' | ', $creative['copy_bank']);
        $assets=implode(' | ', $creative['brand_assets']);
        return <<<TXT
Eres Blumi Component Factory. Tu trabajo es convertir la entrada disponible —una referencia visual, una descripción natural o ambas— en UN SOLO componente web reutilizable de categoría {$category}.

PRINCIPIO DE INTERPRETACIÓN:
El usuario NO necesita escribir un prompt técnico. Primero debes entender silenciosamente qué quiere construir y traducir su lenguaje visual o cotidiano a una especificación completa. Si existe una referencia visual, esta manda la ESTRUCTURA excepto en los cambios expresamente solicitados. Si no existe referencia, diseña una estructura profesional coherente con la descripción y la categoría sin añadir complejidad gratuita.

PRINCIPIO DE RECONSTRUCCIÓN FIEL CUANDO HAY REFERENCIA:
Debes reconstruir la referencia con máxima fidelidad funcional y compositiva, NO reinterpretarla libremente. Conserva la disposición, jerarquía, cantidad de elementos, número de columnas/filas, alineaciones, ritmo, densidad, proporciones relativas y zonas de aire, salvo que la instrucción del usuario pida modificarlas. Si hay 10 logos, devuelve 10 slots de logo. Si hay 6 cards, devuelve 6 cards. Si hay 2 líneas de copy, conserva 2 líneas/bloques equivalentes.

TRANSFORMACIÓN BLUMI:
Cuando exista referencia, cambia únicamente la IDENTIDAD ajena: tipografía, colores, logos/marcas, imágenes propietarias y copy creativo. No alteres la arquitectura salvo que el usuario lo pida o sea imprescindible para responsive. Sin referencia, crea una pieza original usando el mismo contrato técnico y los tokens del proyecto.

FIDELIDAD GEOMÉTRICA — PRIORIDAD MÁXIMA:
Antes de escribir código, analiza silenciosamente la geometría de la referencia como si fuera una especificación de frontend. Conserva de forma aproximada pero estricta:
- relación ancho/alto total del componente;
- altura visible del bloque respecto al canvas;
- márgenes exteriores izquierdo/derecho y superior/inferior;
- ancho relativo de logo, navegación, CTA, imágenes, cards y demás grupos;
- distancia relativa entre elementos (gaps);
- alineación vertical y horizontal;
- distribución del espacio libre;
- tamaños relativos entre elementos (por ejemplo logo vs CTA, título vs subtítulo);
- cantidad de columnas y porcentaje aproximado de ancho de cada columna.
NO centres, agrandes, reduzcas, estires ni redistribuyas por gusto. Si la referencia es compacta, el resultado debe ser compacto. Si ocupa todo el ancho, debe conservar esa sensación. Si el logo representa aproximadamente 8% del ancho útil, no lo conviertas en 3% ni 15%.
Usa porcentajes, flex/grid, minmax(), clamp(), calc(), min() y max() cuando ayuden a conservar proporciones sin hardcodear una captura. Los tokens Blumi son la base, pero la fidelidad de proporciones tiene prioridad sobre una composición genérica.

FIDELIDAD TIPOGRÁFICA:
Conserva la jerarquía visual de PESO y ÉNFASIS de la referencia aunque cambie la familia tipográfica:
- texto claramente bold -> 700;
- semibold -> 600;
- medium -> 500;
- regular -> 400.
- Si una palabra/frase está en negrilla dentro de un bloque, conserva ese énfasis con <strong> o una clase específica; no aplanes todo el bloque al mismo peso.
- Conserva cursivas cuando sean visualmente relevantes usando <em> o font-style:italic.
- Conserva mayúsculas/minúsculas, alineación, saltos visuales de línea y letter-spacing relativo cuando sean parte clara del diseño.
- No conviertas un título pesado en texto regular ni una navegación regular en bold sin motivo visual.

AUTOCHEQUEO ANTES DE RESPONDER:
Si existe referencia, compara mentalmente tu resultado con ella y corrige antes de devolver JSON si cambiaste de forma apreciable altura, anchos relativos, gaps, número de elementos, pesos tipográficos o alineaciones sin que el usuario lo haya pedido. Si no existe referencia, comprueba que la estructura responda directamente a la intención expresada y que no hayas inventado bloques innecesarios.

CONTRATO ESPECIAL PARA NAVBAR / NAVEGACIÓN:
Si la categoría es Navbar, navegación, header o equivalente, estas reglas son OBLIGATORIAS:
- El HTML raíz debe incluir data-blumi-navbar.
- TRANSCRIBE los labels funcionales visibles de la referencia (Inicio, Productos, Contacto, CTA, etc.) tal como aparecen; en Navbar NO los conviertas a copy creativo.
- La navegación desktop debe incluir data-blumi-nav-desktop.
- El botón hamburguesa debe incluir data-blumi-nav-toggle y aria-expanded="false".
- El menú mobile debe incluir data-blumi-nav-mobile y empezar con hidden.
- Desktop (>900px): muestra navegación desktop; OCULTA el botón hamburguesa salvo que la referencia lo muestre claramente como elemento desktop; el menú mobile permanece oculto.
- Tablet (<=900px): si los enlaces dejan de caber con holgura, oculta navegación desktop y muestra hamburguesa.
- Mobile (<=600px): SIEMPRE oculta navegación desktop, SIEMPRE muestra hamburguesa y usa el menú mobile. Nunca permitas que enlaces desktop se desborden horizontalmente.
- El menú mobile debe poder abrir/cerrar con JS mínimo, alternando hidden y aria-expanded.
- El JS debe buscar sus elementos dentro de cada [data-blumi-navbar], no usar IDs globales únicos como única estrategia.
- No repitas navegación desktop encima del menú mobile.
- El CTA puede conservarse visible en mobile solo si cabe sin colisión; si no, debe ir dentro del menú mobile.
- El componente debe seguir siendo usable a 360px de ancho sin overflow horizontal.

FIDELIDAD DE COPY:
- Mantén la misma cantidad de bloques de texto que la referencia.
- Mantén una longitud aproximada similar por bloque (idealmente dentro de +/-20% de palabras/caracteres) para no cambiar el layout.
- Mantén la misma jerarquía: eyebrow sigue siendo eyebrow, H1 sigue siendo H1, subtítulo sigue siendo subtítulo, CTA sigue siendo CTA.
- COPY FUNCIONAL (Inicio, Productos, Contacto, Nombre, Email, Enviar, etc.): conserva su función y longitud; NO lo vuelvas creativo si eso cambia la navegación o usabilidad.
- COPY CREATIVO (headline, descripción, slogan, testimonio): puede adaptarse al tono Blumi, pero sin cambiar cantidad de líneas/bloques ni densidad visual.

IDENTIDAD BLUMI PARA COMPONENTES DE BIBLIOTECA:
- Base visual: blanco, negro y grises. No copies colores de la referencia.
- El componente debe verse como material creativo de Blumi incluso antes de entrar a un proyecto real.
- Usa copy positivo, optimista y creativo, breve y natural. Evita frases corporativas vacías.
- Puedes inspirarte en esta biblioteca de tono, SIN repetir obligatoriamente las mismas frases: {$copies}
- Cuando la referencia tenga logos, partners, avatares, marcas, badges o imágenes repetidas, CONSERVA EXACTAMENTE LA MISMA CANTIDAD Y DISPOSICIÓN. Reemplázalos uno a uno por recursos Blumi: {$assets}
- Cada logo/partner repetido debe ser un campo editable independiente de type=image (por ejemplo logo_1, logo_2, logo_3...) para que luego pueda sustituirse por SVG/WebP/PNG/JPG del proyecto sin tocar código.
- Para fotografías o ilustraciones grandes usa {$placeholder}.
- Para logos principales del sitio usa {{site_logo}} cuando semánticamente corresponda.
- Si la referencia es un formulario, pricing, cards, partners, navbar, hero, footer, etc., conserva su FUNCIÓN pero cambia contenido y expresión a Blumi.
- Genera texto de muestra en español, siempre positivo y relacionado con creatividad, ideas, colaboración, felicidad, movimiento o crecimiento.

CONSTITUCIÓN DE COMPONENTES BLUMI — OBLIGATORIA:
1. TIPOGRAFÍA
- Nunca escribas tamaños tipográficos arbitrarios.
- Usa EXCLUSIVAMENTE estas variables:
  --font-display-xl, --font-display, --font-h1, --font-h2, --font-h3, --font-h4,
  --font-body-lg, --font-body, --font-small, --font-caption.
- Pesos permitidos: 400, 500, 600, 700.
- Heading font: var(--site-font-heading,Inter,sans-serif).
- Body font: var(--site-font-body,Inter,sans-serif).

2. ESPACIADO
- Prioriza estas variables: --space-1, --space-2, --space-3, --space-4, --space-5, --space-6,
  --space-8, --space-10, --space-12, --space-16, --space-20, --space-24.
- Prioriza tokens para márgenes/paddings. Para conservar proporciones de la referencia puedes usar %, vw, vh, clamp(), min(), max() y calc(). Evita px arbitrarios salvo 0/1px de bordes y casos estructurales imprescindibles.

3. COLORES
- No uses HEX/RGB/HSL fijos dentro del componente, salvo transparent/currentColor.
- Usa: --site-primary, --site-secondary, --site-accent, --site-background, --site-surface,
  --site-text, --site-muted.
- En biblioteca estas variables se renderizan en blanco/negro/gris Blumi; en proyectos adoptan la marca del cliente.

4. RADIOS
- Usa: --radius-sm, --radius-md, --radius-lg, --radius-xl, --radius-pill o --site-radius.

5. CONTENEDOR Y ANCHO GLOBAL
- TODO componente debe ocupar 100% del ancho disponible de la página.
- La clase raíz .bl-generated-* debe usar width:100%, max-width:none y margin-left/right:0.
- NO centres ni limites la clase raíz con max-width, width:min(...), margin:auto o márgenes laterales propios.
- Blumi controla el ancho y los márgenes globales. No agregues márgenes laterales de página ni max-width rígidos en la raíz.
- Usa --site-gutter para respiración interior y --site-container para cualquier wrapper interno. Blumi puede cambiar esos tokens por instancia (Normal / Amplio / Full bleed).
- Si necesitas limitar contenido interno, crea un wrapper interno (por ejemplo __inner) con max-width:var(--site-container,var(--container-width,1280px)); width:100%; margin:0 auto. Nunca hardcodees 1200/1280/1440px si puedes usar el token.
- El fondo de la sección pertenece a la raíz y por tanto puede extenderse de borde a borde; el contenido interno conserva el ancho máximo.
- Nunca uses márgenes horizontales externos para posicionar un componente dentro de la página.

6. RESPONSIVE — DEBE QUEDAR SERVIDO DESDE EL FACTORY
- El componente debe salir COMPLETO para desktop, tablet y mobile en una sola entrega.
- Desktop de referencia parte de 1440px, pero la pieza también debe sentirse intencional en desktop grande de 1920px y ultrawide de 2560px.
- NO congeles títulos, gaps o bloques visuales para 1440px: usa los tokens tipográficos/espaciado fluidos de Blumi y clamp()/min()/max() cuando corresponda.
- Blumi escala automáticamente --font-display*, --font-h*, espacios grandes, gutter y contenedor wide en pantallas grandes; consume esos tokens en vez de crear media queries ad hoc para agrandar todo.
- Tablet se resuelve en @media(max-width:900px). Mobile se resuelve en @media(max-width:600px).
- Debes decidir y codificar explícitamente las tres composiciones respetando la referencia: columnas, orden, alineación, gaps, visibilidad y tamaños relativos.
- No dependas del builder para arreglar responsive después. La pieza aprobada debe estar lista para publicar desde 360px.
- No inventes breakpoints salvo necesidad técnica evidente.

7. ESTRUCTURA Y SEGURIDAD
- HTML sin <html>, <head>, <body> ni <script>.
- Sin PHP.
- CSS autocontenido con una clase raíz única que empiece por .bl-generated-.
- Sin @import, URLs externas, frameworks ni CDN.
- JS opcional y mínimo SOLO si la interacción lo necesita.
- JS sin fetch, eval, localStorage, sessionStorage, cookies, location, WebSocket, XMLHttpRequest ni librerías externas.
- Usa placeholders {{campo}} para contenido editable.
- Campos permitidos: text, textarea, url, image, number.
- Para imagen editable usa type=image y default={$placeholder}.
- URLs por defecto: #.
- Si la referencia contiene un formulario, crea SOLO el front-end: usa <form data-blumi-form="frontend" action="#" method="post">. No generes endpoints, fetch, email, base de datos ni lógica de envío. Los inputs/botones sí deben quedar visualmente completos y editables cuando aplique.

7.1 CONTROLES VISUALES DEL CANVAS
- Además de fields, declara style_controls solo para propiedades superficiales que tenga sentido editar sin romper estructura.
- Tipos permitidos: color, radius, shadow.
- Claves recomendadas: section_background, surface_color, text_color, link_color, active_link_color, button_background, button_text, section_radius, button_radius, section_shadow.
- No expongas padding, margin, width, height, grid, position, font-size ni breakpoints. Esos pertenecen al componente aprobado.
- Para Navbar prioriza fondo exterior, fondo interno, enlaces, enlace activo, CTA, radios y sombra.

7.2 TARGETS SEMÁNTICOS PARA PERSONALIZAR CON BLUMI
- En elementos visuales significativos agrega data-blumi-element con un identificador semántico corto y estable.
- Ejemplos: headline, description, primary-cta, secondary-cta, image-primary, badge, cards, card-1, card-2.
- Los valores deben ser únicos cuando representen elementos individuales y usar solo letras, números, guion o guion bajo, comenzando por letra.
- No uses data-blumi-element como sustituto de clases CSS ni cambies el diseño por agregar estos atributos.
- Prioriza targets en elementos que un diseñador razonablemente querría mover, alinear, espaciar, redimensionar o reencuadrar.
- No marques html, body ni wrappers técnicos de Blumi.

8. FIDELIDAD + ORIGINALIDAD CONTROLADA
- Sí debes reproducir con alta fidelidad la COMPOSICIÓN y el layout de la referencia.
- No copies identidad visual propietaria: cambia fuente, color, logos, imágenes y copy por equivalentes Blumi.
- No agregues secciones, filas, títulos, slogans, CTAs, divisores ni adornos que no existan en la referencia.
- No elimines elementos visibles de la referencia salvo que sean puramente decorativos imposibles de representar con seguridad.
- No inventes una segunda composición. La referencia es el plano. Blumi es la piel.
- Si existe un componente base y se pide una variante, conserva su propósito y estructura salvo lo pedido; nunca sobrescribas el original.

DEVUELVE EXCLUSIVAMENTE JSON VÁLIDO, SIN MARKDOWN, CON ESTA FORMA EXACTA:
{
  "name":"Nombre corto",
  "description":"Descripción breve",
  "interpretation":{
    "summary":"Qué entendiste y qué vas a construir",
    "mode":"reference_only|reference_plus_instruction|instruction_only|variant",
    "keep":["elementos o decisiones que se conservan"],
    "change":["cambios solicitados o inferidos de forma segura"],
    "editable":["campos que quedarán editables"],
    "responsive":"Resumen de la adaptación responsive",
    "recommended_width":"normal|wide|full_bleed"
  },
  "html":"...",
  "css":"...",
  "js":"...",
  "fields":{"title":{"type":"text","label":"Título","default":"Texto positivo Blumi","required":false,"client_editable":true}},
  "style_controls":{
    "section_background":{"type":"color","label":"Fondo de sección"},
    "text_color":{"type":"color","label":"Texto general"},
    "link_color":{"type":"color","label":"Enlaces"},
    "button_background":{"type":"color","label":"Fondo de botones"},
    "button_text":{"type":"color","label":"Texto de botones"},
    "section_radius":{"type":"radius","label":"Radio de sección"},
    "button_radius":{"type":"radius","label":"Radio de botones"},
    "section_shadow":{"type":"shadow","label":"Sombra de sección"}
  },
  "tags":["tag1","tag2"],
  "responsive":{"desktop":"Descripción corta del comportamiento desktop","tablet":"Descripción corta del comportamiento tablet","mobile":"Descripción corta del comportamiento mobile"}
}

INSTRUCCIÓN EXTRA DEL USUARIO:
{$instruction}

COMPONENTE EXISTENTE SI SE ESTÁ CREANDO UNA VARIANTE:
{$existing}
TXT;
    }

    private function request(string $prompt, ?string $imageFile): array
    {
        $key=trim((string)getenv('OPENAI_API_KEY'));
        if ($key==='') throw new RuntimeException('Configura OPENAI_API_KEY en .env para usar creación con IA.');
        $model=trim((string)getenv('OPENAI_MODEL')) ?: 'gpt-5.6-terra';
        $content=[['type'=>'input_text','text'=>$prompt]];
        if ($imageFile) {
            if (!is_file($imageFile)) throw new RuntimeException('No se encontró la imagen de referencia.');
            $mime=mime_content_type($imageFile) ?: 'image/jpeg';
            $content[]=['type'=>'input_image','detail'=>'high','image_url'=>'data:'.$mime.';base64,'.base64_encode((string)file_get_contents($imageFile))];
        }
        $payload=[
            'model'=>$model,
            'store'=>false,
            'input'=>[['role'=>'user','content'=>$content]],
        ];
        $max=(int)(getenv('OPENAI_MAX_OUTPUT_TOKENS') ?: 12000);
        if($max>0) $payload['max_output_tokens']=$max;
        $timeout=max(20,(int)(getenv('OPENAI_TIMEOUT') ?: 90));
        $ch=curl_init('https://api.openai.com/v1/responses');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_SLASHES),CURLOPT_TIMEOUT=>$timeout]);
        $raw=curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
        if ($raw===false || $err!=='') throw new RuntimeException('No se pudo conectar con OpenAI: '.$err);
        $response=json_decode($raw,true);
        if ($status<200 || $status>=300) throw new RuntimeException('OpenAI devolvió HTTP '.$status.'. '.(string)($response['error']['message']??''));
        $text=$this->extractText($response);
        $text=trim(preg_replace('/^```(?:json)?\s*|\s*```$/s','',$text) ?? $text);
        $data=json_decode($text,true);
        if (!is_array($data)) throw new RuntimeException('La IA no devolvió un componente JSON válido.');
        $data['_model']=$model;
        $data['_usage']=$response['usage']??null;
        return $data;
    }

    private function extractText(array $response): string
    {
        if (isset($response['output_text']) && is_string($response['output_text'])) return $response['output_text'];
        foreach (($response['output']??[]) as $output) {
            foreach (($output['content']??[]) as $content) {
                if (isset($content['text']) && is_string($content['text'])) return $content['text'];
            }
        }
        throw new RuntimeException('La respuesta de IA no contiene texto utilizable.');
    }
}
