<?php
namespace App\Services;

use App\Repositories\DesignRepository;

final class ComponentIntentService
{
    public function __construct(private readonly DesignRepository $design=new DesignRepository()) {}

    public function compile(array $library, array $category, string $instruction, bool $hasReference): string
    {
        $instruction=trim($instruction);
        $mode=$hasReference
            ? ($instruction!=='' ? 'reference_plus_instruction' : 'reference_only')
            : 'instruction_only';

        $projectContext=$this->projectContext($library);
        $userIntent=$instruction!=='' ? $instruction : 'No hay instrucciones adicionales. Reconstruye la referencia respetando su intención visual y estructura.';

        return <<<TXT
CAPA DE INTERPRETACIÓN BLUMI — PROMPT COMPILER

MODO DE ENTRADA: {$mode}
CATEGORÍA SELECCIONADA: {$category['nombre']}

INTENCIÓN DEL USUARIO:
{$userIntent}

CONTEXTO DEL PROYECTO/LIBRERÍA:
{$projectContext}

Antes de generar código, interpreta internamente la intención como lo haría un diseñador web senior. El usuario puede hablar de manera muy simple, por ejemplo: “genera esto”, “quita esta parte”, “haz las fotos más grandes”, “quiero algo más editorial” o incluso no escribir nada si existe una referencia visual.

Tu trabajo es convertir esa intención sencilla en una especificación completa SIN exigir al usuario vocabulario técnico. Debes inferir cuando corresponda:
- propósito del componente;
- estructura y jerarquía;
- elementos que deben conservarse o eliminarse;
- proporciones y ritmo visual;
- comportamiento Desktop / Tablet / Mobile;
- imágenes y su función (contenido, fondo, cover, contain, foco visual);
- campos que deben ser editables;
- interacción y animaciones realmente necesarias;
- comportamiento de enlaces como campos editables;
- modo de ancho recomendado (normal, wide o full_bleed) sin hardcodear layout global;
- accesibilidad básica;
- adaptación al design system del proyecto.

REGLAS DE INTERPRETACIÓN:
1. Si hay SOLO REFERENCIA, asume que el usuario quiere reconstruir la pieza con alta fidelidad estructural y adaptarla al contrato Blumi.
2. Si hay REFERENCIA + INSTRUCCIÓN, la referencia manda en todo lo que el usuario no haya pedido cambiar. La instrucción tiene prioridad únicamente sobre los cambios explícitos.
3. Si hay SOLO TEXTO, diseña el componente descrito sin inventar dependencias externas y manteniendo una composición profesional coherente con la categoría.
4. Traduce adjetivos vagos (“premium”, “editorial”, “limpio”, “cinematográfico”, “más aire”) a decisiones visuales observables, sin alterar arbitrariamente la función del componente.
5. No obligues al usuario a especificar CSS, breakpoints, object-fit, flex/grid, JS o accesibilidad. Eso es responsabilidad de Blumi.
6. No copies identidad visual de una referencia ajena. Conserva estructura, proporciones, tratamiento e interacción, pero usa tokens y contenido reemplazable.
7. Los componentes aprobados son conceptualmente reutilizables; evita decisiones que los aten a una sola página.

Incluye en la respuesta JSON una clave adicional llamada "interpretation" con esta forma:
{
  "summary":"Resumen corto de lo que entendiste",
  "mode":"{$mode}",
  "keep":["..."],
  "change":["..."],
  "editable":["..."],
  "responsive":"Resumen corto",
  "recommended_width":"normal|wide|full_bleed"
}

La clave interpretation es informativa para revisión y NO reemplaza html/css/js/fields/style_controls/responsive.
TXT;
    }

    private function projectContext(array $library): string
    {
        $projectId=(int)($library['proyecto_id']??0);
        if($projectId<=0){
            return 'Librería general de Blumi. Usa únicamente los tokens oficiales y una identidad neutral reutilizable.';
        }

        $design=$this->design->get($projectId) ?: [];
        $name=(string)($library['proyecto_nombre']??'Proyecto');
        $tokens=[
            'primary'=>$design['primary_color']??null,
            'secondary'=>$design['secondary_color']??null,
            'accent'=>$design['accent_color']??null,
            'background'=>$design['background_color']??null,
            'surface'=>$design['surface_color']??null,
            'text'=>$design['text_color']??null,
            'muted'=>$design['muted_color']??null,
            'heading_font'=>$design['heading_font']??null,
            'body_font'=>$design['body_font']??null,
            'radius'=>$design['border_radius']??null,
            'container'=>$design['container_width']??null,
            'section_spacing'=>$design['section_spacing']??null,
        ];
        $tokens=array_filter($tokens,static fn($v)=>$v!==null && $v!=='');
        $json=json_encode($tokens,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        return "Proyecto: {$name}. Tokens disponibles: {$json}. Usa estos valores solo a través de variables/tokens Blumi; no los hardcodees en el componente.";
    }
}
