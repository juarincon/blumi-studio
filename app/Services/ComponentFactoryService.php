<?php
namespace App\Services;

use App\Repositories\ComponentFactoryRepository;
use InvalidArgumentException;
use RuntimeException;

final class ComponentFactoryService
{
    public function __construct(
        private readonly ComponentFactoryRepository $repo=new ComponentFactoryRepository(),
        private readonly AIComponentService $ai=new AIComponentService(),
        private readonly ComponentLibraryService $libraries=new ComponentLibraryService(),
        private readonly ComponentIntentService $intent=new ComponentIntentService()
    ) {}
    public function categories(): array { return $this->repo->categories(); }
    public function libraries(?int $projectId=null): array { return $this->libraries->all($projectId); }
    public function projects(): array { return $this->libraries->projects(); }
    public function library(?int $libraryId=null, ?int $categoryId=null): array { return $this->repo->all($libraryId,$categoryId); }
    public function categoryCounts(int $libraryId,bool $approvedOnly=false): array { return $this->libraries->categoryCounts($libraryId,$approvedOnly); }
    public function find(int $id): array { $c=$this->repo->find($id); if(!$c) throw new InvalidArgumentException('El componente no existe.'); return $c; }

    public function generate(array $file, int $libraryId, int $categoryId, string $componentName, string $instruction, int $userId): int
    {
        $library=$this->libraries->find($libraryId);
        $category=$this->repo->category($categoryId); if(!$category) throw new InvalidArgumentException('Selecciona una categoría válida.');
        $hasReference=(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_OK);
        if(!$hasReference && trim($instruction)==='') {
            throw new InvalidArgumentException('Sube una referencia o describe qué componente quieres crear.');
        }

        $publicPath=''; $absolutePath=null;
        if($hasReference){ [$publicPath,$absolutePath]=$this->storeReference($file); }

        $compiledIntent=$this->intent->compile($library,$category,$instruction,$hasReference);
        $generated=$this->ai->generateComponent($absolutePath,(string)$category['nombre'],$compiledIntent);
        $origin=$hasReference ? (trim($instruction)!==''?'ia_referencia_instruccion':'ia_referencia') : 'ia_descripcion';
        return $this->persistGenerated($generated,$category,null,$publicPath,$instruction,$userId,$origin,$libraryId,$componentName);
    }

    public function generateFromImage(array $file, int $libraryId, int $categoryId, string $componentName, string $instruction, int $userId): int
    {
        return $this->generate($file,$libraryId,$categoryId,$componentName,$instruction,$userId);
    }

    public function duplicateWithPrompt(int $sourceId,string $instruction,int $userId): int
    {
        if(trim($instruction)==='') throw new InvalidArgumentException('Escribe qué quieres cambiar en la variante.');
        $source=$this->find($sourceId);
        $category=$this->repo->category((int)$source['categoria_id']);
        if(!$category) throw new RuntimeException('La categoría del componente ya no está disponible.');
        $generated=$this->ai->createVariant($source,$instruction);
        return $this->persistGenerated($generated,$category,$source,(string)($source['reference_image_path']??''),$instruction,$userId,'ia_modificacion',(int)($source['library_id']??0),'');
    }

    public function saveCssDraft(int $id,string $css,int $userId): void
    {
        $component=$this->find($id);
        if(($component['estado']??'')==='aprobado' || (int)($component['is_locked']??0)===1){
            throw new InvalidArgumentException('El componente aprobado es inmutable. Duplica una variante para editar su CSS.');
        }
        $css=str_replace(["\r\n","\r"],"\n",trim($css));
        if($css==='') throw new InvalidArgumentException('El CSS no puede quedar vacío.');
        if(strlen($css)>250000) throw new InvalidArgumentException('El CSS supera el límite permitido.');
        if(preg_match('/<\/?(?:style|script|html|body|head)\b|<\?php|javascript\s*:/i',$css)){
            throw new InvalidArgumentException('El editor acepta únicamente CSS.');
        }
        if(substr_count($css,'{')!==substr_count($css,'}')){
            throw new InvalidArgumentException('El CSS tiene llaves desbalanceadas.');
        }
        $before=$this->extractCssClasses((string)($component['css']??''));
        $after=$this->extractCssClasses($css);
        if($before!==$after){
            $missing=array_values(array_diff($before,$after));
            $added=array_values(array_diff($after,$before));
            $parts=[];
            if($missing) $parts[]='faltan: '.implode(', ',array_slice($missing,0,8));
            if($added) $parts[]='nuevas: '.implode(', ',array_slice($added,0,8));
            throw new InvalidArgumentException('No cambies, agregues ni elimines nombres de clases CSS. Solo modifica propiedades y valores'.($parts?' ('.implode('; ',$parts).')':'').'.');
        }
        $this->repo->updateCssDraft($id,$css);
    }

    private function extractCssClasses(string $css): array
    {
        preg_match_all('/(?<![a-zA-Z0-9_-])\.([a-zA-Z_][a-zA-Z0-9_-]*)/',$css,$m);
        $classes=array_values(array_unique($m[1]??[]));
        sort($classes,SORT_STRING);
        return $classes;
    }

    public function approve(int $id,int $libraryId,int $categoryId,string $name,int $userId): void
    {
        $component=$this->find($id);
        $library=$this->libraries->find($libraryId);
        $category=$this->repo->category($categoryId);
        if(!$category) throw new InvalidArgumentException('Selecciona un tipo de componente válido.');
        $name=trim($name); if($name==='') throw new InvalidArgumentException('Escribe un nombre para el componente.');
        $this->libraries->assign($id,(int)$library['id']);
        $this->repo->approve($id,(int)$category['id'],mb_substr($name,0,160));
        $component=$this->find($id);
        if($component['estado']==='aprobado') {
            $this->repo->saveVersion($id,$component,$userId,(string)($component['source_prompt']??''));
            $this->writePackage($component);
        }
    }

    public function previewHtml(array $component): string
    {
        $fields=json_decode((string)$component['schema_campos'],true) ?: [];
        $html=$this->normalizeGeneratedMarkup((string)$component['html_template']);
        $html=preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',function($m) use($fields){
            if($m[1]==='site_logo') return 'assets/img/blumi-library/blumi-wordmark.svg';
            $def=$fields[$m[1]]??[]; $value=(string)($def['default']??'');
            if(($def['type']??'')==='image' && $value==='') $value='assets/img/blumi-component-placeholder.svg';
            return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        },$html) ?? '';
        $tokens=$this->previewTokens();
        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'.$tokens.'*{box-sizing:border-box}html,body{margin:0;min-height:100%;width:100%}body{overflow-x:hidden;font-family:var(--site-font-body);background:#f3f3f3;color:var(--site-text)}img{max-width:100%;display:block}.bl-page-section{width:100%;max-width:none;margin:0;padding:0}.bl-component-surface{width:100%;background:var(--site-background)}.bl-site-frame{width:100%;max-width:calc(var(--site-container) + (var(--site-gutter) * 2));margin:0 auto;padding-left:var(--site-gutter);padding-right:var(--site-gutter)}.bl-component-stage>*{width:100%!important;max-width:none!important;margin-left:0!important;margin-right:0!important}'.$this->normalizeGeneratedMarkup((string)($component['css']??'')).'</style></head><body><section class="bl-page-section"><div class="bl-component-surface"><div class="bl-site-frame"><div class="bl-component-stage">'.$html.'</div></div></div></section>'.($component['js']?'<script>'.$component['js'].'</script>':'').'</body></html>';
    }

    private function previewTokens(): string
    {
        return ':root{'.
            '--site-primary:#111;--site-secondary:#444;--site-accent:#777;--site-text:#111;--site-muted:#666;--site-surface:#fff;--site-background:#fff;'.
            '--site-font-heading:Inter,Arial,sans-serif;--site-font-body:Inter,Arial,sans-serif;--site-container-normal:1280px;--site-container-wide:1600px;--site-container:var(--site-container-wide);--site-gutter:32px;--site-section-space-base:96px;--site-section-space:var(--site-section-space-base);'.
            '--font-display-xl:clamp(72px,4.3vw,108px);--font-display:clamp(60px,3.55vw,88px);--font-h1:clamp(48px,2.9vw,72px);--font-h2:clamp(40px,2.4vw,60px);--font-h3:clamp(32px,1.9vw,48px);--font-h4:clamp(24px,1.45vw,34px);--font-body-lg:clamp(18px,.95vw,20px);--font-body:clamp(16px,.85vw,18px);--font-small:clamp(14px,.74vw,15px);--font-caption:12px;'.
            '--space-1:4px;--space-2:8px;--space-3:12px;--space-4:16px;--space-5:20px;--space-6:24px;--space-8:clamp(32px,2vw,44px);--space-10:clamp(40px,2.4vw,52px);--space-12:clamp(48px,2.8vw,60px);--space-16:clamp(64px,3.6vw,80px);--space-20:clamp(80px,4.4vw,96px);--space-24:clamp(96px,5.2vw,120px);'.
            '--radius-sm:8px;--radius-md:12px;--radius-lg:16px;--radius-xl:20px;--radius-pill:999px;--site-radius:12px;--container-width:1280px'.
        '}@media(min-width:1600px){:root{--site-gutter:48px;--site-section-space:calc(var(--site-section-space-base) + 8px)}}'.
        '@media(min-width:1900px){:root{--site-container-wide:1760px;--site-gutter:56px;--site-section-space:calc(var(--site-section-space-base) + 16px)}}'.
        '@media(min-width:2400px){:root{--site-container-wide:2080px;--site-gutter:64px;--site-section-space:calc(var(--site-section-space-base) + 32px)}}'.
        '@media(max-width:1199px){:root{--site-gutter:24px}}'.
        '@media(max-width:900px){:root{--font-display-xl:60px;--font-display:52px;--font-h1:42px;--font-h2:36px;--font-h3:28px;--font-h4:22px}}'.
        '@media(max-width:767px){:root{--site-gutter:14px}}'.
        '@media(max-width:600px){:root{--font-display-xl:44px;--font-display:40px;--font-h1:36px;--font-h2:32px;--font-h3:26px;--font-h4:20px;--font-body-lg:17px}}';
    }

    private function persistGenerated(array $g,array $category,?array $parent,string $reference,string $prompt,int $userId,string $origin,int $libraryId,string $requestedName=""): int
    {
        $html=$this->normalizeGeneratedMarkup((string)($g['html']??'')); $css=$this->normalizeFontWeights($this->normalizeGeneratedMarkup((string)($g['css']??''))); $js=trim((string)($g['js']??''));
        $fields=is_array($g['fields']??null)?$g['fields']:[];
        $this->validateGenerated($html,$css,$js,$fields,(string)($category['slug']??''),(string)($category['nombre']??''));
        $codigo=$this->repo->nextCode((string)$category['slug']);
        $data=[
            'codigo'=>$codigo,'nombre'=>mb_substr(trim($requestedName!==''?$requestedName:(string)($g['name']??$codigo)),0,160),'categoria_id'=>(int)$category['id'],
            'parent_component_id'=>$parent?(int)$parent['id']:null,'descripcion'=>mb_substr(trim((string)($g['description']??'')),0,1000),
            'html_template'=>$html,'css'=>$css,'js'=>$js,'schema_campos'=>json_encode($fields,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'schema_configuracion'=>json_encode([
                'responsive'=>(is_array($g['responsive']??null)?$g['responsive']:['desktop'=>'Layout base','tablet'=>'Adaptación <=900px','mobile'=>'Adaptación <=600px']),
                'style_controls'=>$this->normalizeStyleControls(is_array($g['style_controls']??null)?$g['style_controls']:[], (string)($category['slug']??'')),
                'interpretation'=>(is_array($g['interpretation']??null)?$g['interpretation']:null),
                'generation'=>['standard_version'=>'intent-compiler-1.0','input_mode'=>(string)($g['interpretation']['mode']??'legacy')]
            ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'thumbnail_path'=>$reference ?: null,'reference_image_path'=>$reference ?: null,'estado'=>'pendiente_revision','origen'=>$origin,
            'created_by'=>$userId,'is_locked'=>0,'source_prompt'=>$prompt ?: null,'ai_model'=>(string)($g['_model']??'') ?: null,
        ];
        $id=$this->repo->create($data);
        if($libraryId>0) $this->libraries->assign($id,$libraryId);
        $component=$this->find($id); $this->writePackage($component);
        return $id;
    }

    private function normalizeStyleControls(array $controls, string $categorySlug): array
    {
        $allowedKeys=['section_background','surface_color','text_color','link_color','active_link_color','button_background','button_text','section_radius','button_radius','section_shadow','layout_width'];
        $allowedTypes=['color','radius','shadow','layout_width'];
        $clean=[];
        foreach($controls as $key=>$def){
            if(!in_array((string)$key,$allowedKeys,true) || !is_array($def)) continue;
            $type=(string)($def['type']??'color');
            if(!in_array($type,$allowedTypes,true)) continue;
            $clean[(string)$key]=['type'=>$type,'label'=>mb_substr((string)($def['label']??$key),0,80)];
        }
        if($clean) return $clean;
        if(strtolower($categorySlug)==='navbar') return [
            'section_background'=>['type'=>'color','label'=>'Fondo exterior'],
            'surface_color'=>['type'=>'color','label'=>'Fondo del navbar'],
            'link_color'=>['type'=>'color','label'=>'Enlaces'],
            'active_link_color'=>['type'=>'color','label'=>'Enlace activo'],
            'button_background'=>['type'=>'color','label'=>'Fondo CTA'],
            'button_text'=>['type'=>'color','label'=>'Texto CTA'],
            'section_radius'=>['type'=>'radius','label'=>'Radio del navbar'],
            'button_radius'=>['type'=>'radius','label'=>'Radio CTA'],
            'section_shadow'=>['type'=>'shadow','label'=>'Sombra del navbar'],
        ];
        return [
            'section_background'=>['type'=>'color','label'=>'Fondo de sección'],
            'text_color'=>['type'=>'color','label'=>'Texto general'],
            'link_color'=>['type'=>'color','label'=>'Enlaces'],
            'button_background'=>['type'=>'color','label'=>'Fondo de botones'],
            'button_text'=>['type'=>'color','label'=>'Texto de botones'],
            'section_radius'=>['type'=>'radius','label'=>'Radio de sección'],
            'button_radius'=>['type'=>'radius','label'=>'Radio de botones'],
            'section_shadow'=>['type'=>'shadow','label'=>'Sombra de sección'],
        ];
    }

    private function normalizeGeneratedMarkup(string $value): string
    {
        $value=trim($value);
        // Algunos modelos devuelven secuencias \n/\r/\t doblemente escapadas dentro del JSON.
        // En HTML/CSS deben convertirse en whitespace real para que nunca aparezcan impresas en el preview.
        $value=str_replace(["\\r\\n","\\n","\\r","\\t"],["\n","\n","\n","\t"],$value);
        // Elimina cercas Markdown residuales si el modelo las incrustó dentro del valor.
        $value=preg_replace('/^```(?:html|css)?\s*|\s*```$/i','',$value) ?? $value;
        return trim($value);
    }

    private function normalizeFontWeights(string $css): string
    {
        return preg_replace_callback('/font-weight\s*:\s*([^;}{]+)/i', function(array $match): string {
            $raw=trim((string)$match[1]);
            $lower=strtolower($raw);

            // Conserva el token oficial si el modelo ya lo utilizó correctamente.
            if($lower==='var(--font-weight,400)') return 'font-weight:var(--font-weight,400)';

            $named=[
                'normal'=>400,
                'regular'=>400,
                'medium'=>500,
                'semibold'=>600,
                'semi-bold'=>600,
                'demibold'=>600,
                'demi-bold'=>600,
                'bold'=>700,
                'bolder'=>700,
                'lighter'=>400,
            ];

            if(isset($named[$lower])) {
                return 'font-weight:'.$named[$lower];
            }

            if(preg_match('/^([1-9]00)$/',$lower,$m)) {
                $weight=(int)$m[1];
                if($weight<=400) $normalized=400;
                elseif($weight<=500) $normalized=500;
                elseif($weight<=600) $normalized=600;
                else $normalized=700;
                return 'font-weight:'.$normalized;
            }

            // Si la IA devuelve una expresión desconocida, no cancelamos toda la generación:
            // usamos Regular como fallback seguro y consistente.
            return 'font-weight:400';
        },$css) ?? $css;
    }

    private function validateGenerated(string $html,string $css,string $js,array $fields,string $categorySlug='',string $categoryName=''): void
    {
        if($html==='') throw new RuntimeException('La IA devolvió HTML vacío.');
        $all=strtolower($html."\n".$css."\n".$js);
        foreach(['<?php','<script','@import','javascript:','eval(','new function','fetch(','xmlhttprequest','websocket','document.cookie','localstorage','sessionstorage','window.open','location.href'] as $bad) {
            if(str_contains($all,$bad)) throw new RuntimeException('El componente generado contiene una instrucción no permitida: '.$bad);
        }
        if(!preg_match('/\.bl-generated-[a-z0-9_-]+/i',$css)) throw new RuntimeException('El componente debe tener una clase raíz .bl-generated-.');
        if(!str_contains(strtolower($css),'@media')) throw new RuntimeException('El componente debe incluir adaptación responsive con @media.');

        preg_match_all('/font-size\s*:\s*([^;}{]+)/i',$css,$fontSizes);
        foreach(($fontSizes[1]??[]) as $value){
            if(!str_contains((string)$value,'var(--font-')) throw new RuntimeException('Blumi no permite tamaños de fuente arbitrarios. Usa tokens --font-*.');
        }
        // Los pesos se normalizan antes de validar. Aquí solo verificamos que el CSS resultante
        // haya quedado dentro de la escala Blumi 400/500/600/700.
        preg_match_all('/font-weight\s*:\s*([^;}{]+)/i',$css,$weights);
        foreach(($weights[1]??[]) as $value){
            $v=trim((string)$value);
            if(!in_array($v,['400','500','600','700','var(--font-weight,400)'],true)) {
                throw new RuntimeException('No se pudo normalizar un peso tipográfico del componente.');
            }
        }
        if(preg_match('/#[0-9a-f]{3,8}\b/i',$css) || preg_match('/\b(?:rgb|rgba|hsl|hsla)\s*\(/i',$css)) {
            throw new RuntimeException('El componente contiene colores fijos. Usa tokens --site-* para que sea reutilizable.');
        }
        foreach($fields as $name=>$def) {
            if(!preg_match('/^[a-zA-Z0-9_]+$/',(string)$name) || !is_array($def)) throw new RuntimeException('Schema de campos inválido.');
            if(!in_array((string)($def['type']??'text'),['text','textarea','url','image','number'],true)) throw new RuntimeException('Tipo de campo no permitido.');
        }

        $categoryKey=strtolower(trim($categorySlug.' '.$categoryName));
        if(str_contains($categoryKey,'navbar') || str_contains($categoryKey,'naveg') || str_contains($categoryKey,'header')) {
            foreach(['data-blumi-navbar','data-blumi-nav-desktop','data-blumi-nav-toggle','data-blumi-nav-mobile'] as $requiredAttr) {
                if(!str_contains(strtolower($html),$requiredAttr)) {
                    throw new RuntimeException('El Navbar generado no cumple el contrato responsive Blumi: falta '.$requiredAttr.'. Vuelve a generar el componente.');
                }
            }
            $cssLower=strtolower($css);
            if(!preg_match('/@media\s*\([^)]*max-width\s*:\s*600px[^)]*\)/i',$css)) {
                throw new RuntimeException('El Navbar debe definir explícitamente su comportamiento mobile en <=600px.');
            }
            if(!preg_match('/data-blumi-nav-toggle|aria-expanded|hidden/i',$js)) {
                throw new RuntimeException('El Navbar debe incluir JS funcional para abrir/cerrar el menú mobile.');
            }
            if(!str_contains($cssLower,'overflow')) {
                // No es obligatorio forzar overflow:hidden; solo dejamos una señal para el prompt/QA.
            }
        }
    }

    private function storeReference(array $file): array
    {
        $error=(int)($file['error']??UPLOAD_ERR_NO_FILE);
        if($error!==UPLOAD_ERR_OK){
            $message=match($error){
                UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE => 'La referencia supera el tamaño permitido por el servidor.',
                UPLOAD_ERR_PARTIAL => 'La imagen se subió de forma incompleta. Intenta nuevamente.',
                UPLOAD_ERR_NO_FILE => 'Sube una imagen de referencia.',
                default => 'No se pudo recibir la imagen de referencia. Intenta nuevamente.'
            };
            throw new InvalidArgumentException($message);
        }
        if((int)($file['size']??0)>8*1024*1024) throw new InvalidArgumentException('La referencia no puede superar 8 MB.');

        $tmp=(string)($file['tmp_name']??'');
        if($tmp==='' || !is_file($tmp)) throw new InvalidArgumentException('No se pudo leer la imagen de referencia.');

        // No confiamos solo en finfo: en Windows/XAMPP y algunos navegadores una imagen
        // valida puede llegar como application/octet-stream. getimagesize inspecciona el
        // contenido real y evita rechazar JPG/PNG/WebP correctos por su MIME declarado.
        $info=@getimagesize($tmp);
        $type=is_array($info)?(int)($info[2]??0):0;
        $mime='';
        try { $mime=(string)((new \finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: ''); } catch (\Throwable) {}

        $ext=match($type){
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            default => null
        };

        // Fallback por contenido MIME para instalaciones donde getimagesize no informa
        // correctamente el tipo pero fileinfo si lo reconoce.
        if($ext===null){
            $ext=['image/jpeg'=>'jpg','image/pjpeg'=>'jpg','image/png'=>'png','image/x-png'=>'png','image/webp'=>'webp'][$mime]??null;
        }

        $dir='uploads/component-references/'.date('Y/m');
        $abs=dirname(__DIR__,2).'/public/'.$dir;
        if(!is_dir($abs) && !mkdir($abs,0775,true) && !is_dir($abs)) throw new RuntimeException('No se pudo crear la carpeta de referencias.');

        if($ext!==null){
            $name=bin2hex(random_bytes(12)).'.'.$ext;
            $dest=$abs.'/'.$name;
            if(!move_uploaded_file($tmp,$dest)) throw new RuntimeException('No se pudo guardar la referencia.');
            return [$dir.'/'.$name,$dest];
        }

        // AVIF aparece con frecuencia al guardar referencias desde sitios modernos.
        // Si GD del servidor puede leerlo, lo normalizamos a WebP antes de enviarlo a IA.
        $isAvif=($mime==='image/avif') || (defined('IMAGETYPE_AVIF') && $type===constant('IMAGETYPE_AVIF'));
        if($isAvif){
            if(function_exists('imagecreatefromavif') && function_exists('imagewebp')){
                $img=@imagecreatefromavif($tmp);
                if($img!==false){
                    $name=bin2hex(random_bytes(12)).'.webp';
                    $dest=$abs.'/'.$name;
                    if(@imagewebp($img,$dest,92)){
                        imagedestroy($img);
                        return [$dir.'/'.$name,$dest];
                    }
                    imagedestroy($img);
                }
            }
            throw new InvalidArgumentException('La referencia es AVIF y este servidor no puede convertirla. Guárdala como JPG, PNG o WebP e intenta nuevamente.');
        }

        throw new InvalidArgumentException('No pudimos reconocer esta imagen. Usa JPG, PNG o WebP. Si la descargaste de una web, revisa que no sea AVIF o HEIC.');
    }

    private function framedPreview(string $document,string $width): string
    {
        $src='data:text/html;base64,'.base64_encode($document);
        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>html,body{margin:0;background:#ededed;font-family:Arial,sans-serif}.frame{width:min(100%,'.htmlspecialchars($width,ENT_QUOTES).');margin:0 auto;background:#fff;min-height:100vh}.frame iframe{width:100%;height:100vh;border:0;display:block}</style></head><body><div class="frame"><iframe src="'.htmlspecialchars($src,ENT_QUOTES).'"></iframe></div></body></html>';
    }

    private function writePackage(array $component): void
    {
        $base=dirname(__DIR__,2).'/storage/components/'.$component['codigo'];
        if(!is_dir($base)) mkdir($base,0775,true);
        file_put_contents($base.'/component.html',(string)$component['html_template']);
        file_put_contents($base.'/component.css',(string)($component['css']??''));
        file_put_contents($base.'/component.js',(string)($component['js']??''));
        file_put_contents($base.'/schema.json',json_encode(json_decode((string)$component['schema_campos'],true)?:[],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        file_put_contents($base.'/meta.json',json_encode(['id'=>(int)$component['id'],'codigo'=>$component['codigo'],'nombre'=>$component['nombre'],'categoria'=>$component['categoria_nombre'],'parent_component_id'=>$component['parent_component_id']? (int)$component['parent_component_id']:null,'estado'=>$component['estado'],'reference_image_path'=>$component['reference_image_path']??null,'standard_version'=>$component['standard_version']??'1.0','creative_profile'=>$component['creative_profile']??'blumi'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        $preview=$this->previewHtml($component);
        file_put_contents($base.'/index.html',$preview);
        file_put_contents($base.'/preview-desktop.html',$this->framedPreview($preview,'1440px'));
        file_put_contents($base.'/preview-tablet.html',$this->framedPreview($preview,'900px'));
        file_put_contents($base.'/preview-mobile.html',$this->framedPreview($preview,'390px'));
    }
}
