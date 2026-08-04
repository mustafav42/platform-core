<?php
declare(strict_types=1);

final class UIAuditService
{
    public function __construct(private string $root) {}

    /** @return array<string,mixed> */
    public function report(): array
    {
        $pages = [];
        foreach (['admin', 'admin-v2'] as $dir) {
            $base = $this->root . '/' . $dir;
            if (!is_dir($base)) continue;
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') continue;
                $path = str_replace('\\', '/', substr($file->getPathname(), strlen($this->root) + 1));
                if (str_contains($path, '/update/') || str_contains($path, '/payload/')) continue;
                $pages[] = $this->inspectPage($file->getPathname(), $path);
            }
        }

        $css = $this->countExt('css');
        $js = $this->countExt('js');
        $inlineStyle = count(array_filter($pages, fn(array $p): bool => $p['inline_style'] > 0));
        $inlineScript = count(array_filter($pages, fn(array $p): bool => $p['inline_script'] > 0));
        $legacy = count(array_filter($pages, fn(array $p): bool => in_array('legacy-admin', $p['shells'], true)));
        $cc = count(array_filter($pages, fn(array $p): bool => in_array('control-center', $p['shells'], true)));
        $mixed = count(array_filter($pages, fn(array $p): bool => count($p['shells']) > 1));

        usort($pages, fn(array $a, array $b): int => ($b['risk_score'] <=> $a['risk_score']) ?: strcmp($a['path'], $b['path']));
        return [
            'generated_at' => date('Y-m-d H:i:s'),
            'summary' => [
                'php_pages' => count($pages), 'css_files' => $css, 'js_files' => $js,
                'inline_style_pages' => $inlineStyle, 'inline_script_pages' => $inlineScript,
                'legacy_shell_pages' => $legacy, 'control_center_pages' => $cc, 'mixed_shell_pages' => $mixed,
            ],
            'pages' => $pages,
            'components' => self::componentRegistry(),
        ];
    }

    /** @return array<string,mixed> */
    private function inspectPage(string $absolute, string $path): array
    {
        $text = (string) @file_get_contents($absolute);
        preg_match_all('/(?:href|src)=["\']([^"\']+\.(?:css|js)(?:\?[^"\']*)?)["\']/i', $text, $m);
        $assets = array_values(array_unique(array_map(fn(string $x): string => preg_replace('/\?.*$/', '', $x) ?: $x, $m[1] ?? [])));
        $shells = [];
        if (preg_match('/enterprise\/(?:_header|bootstrap)|ControlCenter|ch-shell|control-center/i', $text)) $shells[] = 'control-center';
        if (preg_match('/admin-v2|AdminV2/i', $text)) $shells[] = 'admin-v2';
        if (preg_match('/admin-premium|dashboard-v2|partials\/header|include[^\n]+header/i', $text)) $shells[] = 'legacy-admin';
        if (preg_match('/qr-experience\/partials\/layout/i', $text)) $shells[] = 'qr-experience';
        $inlineStyle = preg_match_all('/<style\b/i', $text);
        $inlineScript = preg_match_all('/<script\b(?![^>]*src=)/i', $text);
        preg_match_all('/\b(?:btn|button|ch-button)[\w_-]*\b/i', $text, $bm);
        $buttonTokens = array_values(array_unique(array_map('strtolower', $bm[0] ?? [])));
        $risk = ($inlineStyle * 3) + ($inlineScript * 2) + max(0, count($assets) - 4) + (count($shells) > 1 ? 8 : 0) + (in_array('legacy-admin', $shells, true) ? 4 : 0) + max(0, count($buttonTokens) - 5);
        $status = in_array('control-center', $shells, true) && !in_array('legacy-admin', $shells, true) ? 'Control Center' : (in_array('legacy-admin', $shells, true) ? 'Eski kabuk' : 'Belirsiz/bağımsız');
        return [
            'path' => $path, 'bytes' => @filesize($absolute) ?: 0, 'assets' => $assets,
            'asset_count' => count($assets), 'inline_style' => $inlineStyle, 'inline_script' => $inlineScript,
            'shells' => $shells, 'button_tokens' => $buttonTokens, 'button_token_count' => count($buttonTokens),
            'risk_score' => $risk, 'status' => $status,
        ];
    }

    private function countExt(string $ext): int
    {
        $count = 0;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) if ($file->isFile() && strtolower($file->getExtension()) === $ext) $count++;
        return $count;
    }

    /** @return array<int,array<string,string>> */
    public static function componentRegistry(): array
    {
        return [
            ['code'=>'CHButton','status'=>'Taslak','rule'=>'Tüm eylemler primary, secondary, success, danger veya ghost varyantlarından birini kullanır.'],
            ['code'=>'CHCard','status'=>'Taslak','rule'=>'Ortak yüzey, radius, border, shadow ve padding tokenları.'],
            ['code'=>'CHForm','status'=>'Planlandı','rule'=>'Input, select, textarea, switch ve validation tek standarda bağlanır.'],
            ['code'=>'CHTable','status'=>'Planlandı','rule'=>'Liste, toplu seçim, durum ve satır aksiyonları ortak davranır.'],
            ['code'=>'CHToolbar','status'=>'Taslak','rule'=>'Başlık, arama, filtre ve ana eylem aynı hizada.'],
            ['code'=>'CHTabs','status'=>'Taslak','rule'=>'Workspace sekmeleri tek görsel ve erişilebilirlik standardında.'],
            ['code'=>'CHDrawer','status'=>'Planlandı','rule'=>'Ekle/düzenle panelleri ortak header, body ve sticky footer kullanır.'],
            ['code'=>'CHModal','status'=>'Planlandı','rule'=>'Onay, uyarı ve kısa işlemler için ortak modal.'],
            ['code'=>'CHBadge','status'=>'Taslak','rule'=>'Etkin, kapalı, uyarı ve hata durumları ortak renklerle gösterilir.'],
            ['code'=>'CHEmptyState','status'=>'Planlandı','rule'=>'Boş liste ve ilk kurulum durumları ortak anlatım ve eylem sunar.'],
        ];
    }
}
