<?php
namespace App\Services;

use App\Repositories\DesignRepository;
use InvalidArgumentException;

final class DesignService
{
    private const GOOGLE_FONTS = [
        'Inter','Manrope','Outfit','Poppins','Montserrat','DM Sans','Plus Jakarta Sans','Work Sans',
        'Space Grotesk','Archivo','Urbanist','Nunito Sans','Lora','Playfair Display','Libre Baskerville'
    ];

    public function __construct(private readonly DesignRepository $designs = new DesignRepository()) {}

    public function get(int $projectId): array
    {
        $design = $this->designs->get($projectId);
        if (!$design) {
            throw new InvalidArgumentException('No se encontró la identidad visual del proyecto.');
        }
        $design['style_tags'] = $this->decode($design['style_tags_json'] ?? '[]');
        return $design;
    }

    public function update(int $projectId, array $input): void
    {
        $current = $this->get($projectId);
        $colors = [];
        foreach (['primary_color','secondary_color','accent_color','background_color','surface_color','text_color','muted_color'] as $key) {
            $colors[$key] = $this->color((string)($input[$key] ?? $current[$key] ?? ''));
        }

        $headingFont = $this->fontName((string)($input['heading_font'] ?? $current['heading_font'] ?? 'Inter'));
        $bodyFont = $this->fontName((string)($input['body_font'] ?? $current['body_font'] ?? 'Inter'));
        $headingSource = $this->fontSource((string)($input['heading_font_source'] ?? 'system'));
        $bodySource = $this->fontSource((string)($input['body_font_source'] ?? 'system'));
        $headingUrl = $this->fontUrl((string)($input['heading_font_url'] ?? ''), $headingSource, $headingFont);
        $bodyUrl = $this->fontUrl((string)($input['body_font_url'] ?? ''), $bodySource, $bodyFont);
        $radius = $this->dimension((string)($input['border_radius'] ?? '12px'), '12px', 0, 40);
        $container = $this->dimension((string)($input['container_width'] ?? '1280px'), '1280px', 760, 1800);
        $spacing = $this->dimension((string)($input['section_spacing'] ?? '96px'), '96px', 32, 180);
        $allowedTags = ['minimal','editorial','bold','playful','soft','corporate','luxury','technical','organic','dark'];
        $tags = array_values(array_intersect($allowedTags, array_map('strval', (array)($input['style_tags'] ?? []))));

        $tokens = [
            'primary' => $colors['primary_color'], 'secondary' => $colors['secondary_color'], 'accent' => $colors['accent_color'],
            'background' => $colors['background_color'], 'surface' => $colors['surface_color'], 'text' => $colors['text_color'], 'muted' => $colors['muted_color'],
            'font_heading' => $headingFont, 'font_body' => $bodyFont,
            'border_radius' => $radius, 'container_width' => $container, 'section_spacing' => $spacing,
            'style_tags' => $tags,
        ];

        $this->designs->update($projectId, $colors + [
            'heading_font' => $headingFont,
            'heading_font_source' => $headingSource,
            'heading_font_url' => $headingUrl,
            'body_font' => $bodyFont,
            'body_font_source' => $bodySource,
            'body_font_url' => $bodyUrl,
            'border_radius' => $radius,
            'container_width' => $container,
            'section_spacing' => $spacing,
            'style_tags_json' => json_encode($tags, JSON_UNESCAPED_UNICODE),
            'tokens_json' => json_encode($tokens, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function readiness(int $projectId): array
    {
        $design = $this->get($projectId);
        $missing = [];
        if (empty($design['logo_asset_id']) || empty($design['logo_ruta'])) {
            $missing[] = 'logo_principal';
        }

        return [
            'ready' => $missing === [],
            'missing' => $missing,
        ];
    }

    public function assertReadyForExport(int $projectId): void
    {
        $readiness = $this->readiness($projectId);
        if (!$readiness['ready']) {
            throw new InvalidArgumentException('Antes de generar o publicar el sitio debes subir un logo principal.');
        }
    }

    public function fonts(int $projectId): array { return $this->designs->fonts($projectId); }
    public function references(int $projectId): array { return $this->designs->references($projectId); }
    public function googleFonts(): array { return self::GOOGLE_FONTS; }

    public function addFont(int $projectId, array $uploadData): int
    {
        return $this->designs->addFont(['proyecto_id' => $projectId] + $uploadData);
    }

    public function addReference(int $projectId, int $assetId, string $type, int $userId): void
    {
        $type = in_array($type, ['logo','referencia','favicon'], true) ? $type : 'referencia';
        $this->designs->addReference($projectId, $assetId, $type, $type === 'logo', $userId);
    }

    public function fontFaceCss(array $fonts): string
    {
        $css = [];
        foreach ($fonts as $font) {
            $name = str_replace(["'", '"'], '', (string)$font['nombre']);
            $path = str_replace([')', '"', "'"], '', (string)$font['ruta']);
            $format = $font['formato'] === 'woff' ? 'woff' : 'woff2';
            $css[] = "@font-face{font-family:'{$name}';src:url('{$path}') format('{$format}');font-weight:" . (int)$font['peso'] . ";font-style:" . ($font['estilo'] === 'italic' ? 'italic' : 'normal') . ";font-display:swap;}";
        }
        return implode("\n", $css);
    }

    public function googleFontUrls(array $design): array
    {
        $urls = [];
        foreach ([['source'=>'heading_font_source','url'=>'heading_font_url'], ['source'=>'body_font_source','url'=>'body_font_url']] as $field) {
            if (($design[$field['source']] ?? '') === 'google' && !empty($design[$field['url']])) {
                $urls[] = (string)$design[$field['url']];
            }
        }
        return array_values(array_unique($urls));
    }

    private function color(string $value): string
    {
        $value = strtoupper(trim($value));
        if (preg_match('/^#[0-9A-F]{6}$/', $value)) return $value;
        if (preg_match('/^#[0-9A-F]{3}$/', $value)) {
            return '#' . $value[1].$value[1].$value[2].$value[2].$value[3].$value[3];
        }
        throw new InvalidArgumentException('Uno de los colores no tiene un formato válido.');
    }

    private function fontName(string $value): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > 120) throw new InvalidArgumentException('Nombre de fuente inválido.');
        return preg_replace('/[^\pL\pN ._+\-]/u', '', $value) ?: 'Inter';
    }

    private function fontSource(string $value): string
    {
        return in_array($value, ['system','google','local'], true) ? $value : 'system';
    }

    private function fontUrl(string $url, string $source, string $fontName): ?string
    {
        $url = trim($url);
        if ($source !== 'google') return null;
        if ($url === '') {
            $family = str_replace('%20', '+', rawurlencode($fontName));
            return 'https://fonts.googleapis.com/css2?family=' . $family . ':wght@400;500;600;700&display=swap';
        }
        $parts = parse_url($url);
        $host = strtolower((string)($parts['host'] ?? ''));
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if ($scheme !== 'https' || !in_array($host, ['fonts.googleapis.com','fonts.gstatic.com'], true)) {
            throw new InvalidArgumentException('La URL de Google Fonts debe pertenecer a fonts.googleapis.com o fonts.gstatic.com y usar HTTPS.');
        }
        return $url;
    }

    private function dimension(string $value, string $default, int $min, int $max): string
    {
        if (!preg_match('/^(\d{1,4})px$/', trim($value), $match)) return $default;
        $number = max($min, min($max, (int)$match[1]));
        return $number . 'px';
    }

    private function decode(string $json): array
    {
        $value = json_decode($json, true);
        return is_array($value) ? $value : [];
    }
}
