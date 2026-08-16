<?php

namespace App\Services;

use App\Exceptions\BusinessLogicException;
use App\Models\Document;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Storage;

class PdfExportService
{
    public function __construct(
        protected QrCodeService $qrCodeService,
    ) {}

    /**
     * Margin default (px), HARUS SAMA PERSIS dengan DEFAULT_MARGIN di
     * resources/js/jodit.js — itu sumber kebenaran untuk margin editor &
     * print. PHP tidak bisa import langsung dari file JS, jadi nilainya
     * disalin manual di sini. Kalau DEFAULT_MARGIN di jodit.js berubah,
     * nilai ini harus ikut disesuaikan.
     */
    private const DEFAULT_MARGIN = [
        'top' => 48,
        'right' => 56,
        'bottom' => 48,
        'left' => 56,
    ];

    /**
     * Path executable Chrome/Edge untuk headless print-to-pdf.
     * Coba beberapa lokasi umum (Windows + Linux).
     */
    private const CHROME_CANDIDATES = [
        'C:\Program Files\Google\Chrome\Application\chrome.exe',
        'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
        'C:\Program Files\Microsoft\Edge\Application\msedge.exe',
        'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
        '/usr/bin/google-chrome',
        '/usr/bin/chromium',
        '/usr/bin/chromium-browser',
        '/snap/bin/chromium',
    ];

    /**
     * Pemetaan font-family Jodit (FONT_LIST di jodit.js) ke font yang
     * tersedia sebagai font SISTEM (Windows/Linux) — browser render pakai
     * font asli, jadi metrik karakter identik dengan editor. Key = versi
     * TERNORMALISASI (tanpa kutip, tanpa spasi ekstra).
     *
     * Google Fonts (Roboto/Open Sans/dst) diarahkan ke pengganti metrik
     * terdekat yang umum tersedia sebagai font sistem. PENTING: karena kita
     * substitusi ke font sistem (bukan @import Google Fonts), tidak ada
     * proses download font async saat export — layout langsung stabil
     * begitu DOM ter-parse, jadi script pagination (lihat
     * buildPaginationScript()) tidak perlu menunggu document.fonts.ready
     * sama sekali.
     */
    private const PDF_SAFE_FONT_MAP = [
        'Arial,Helvetica,sans-serif' => 'Arial, sans-serif',
        'Georgia,serif' => 'Georgia, serif',
        'Times New Roman,Times,serif' => 'Times New Roman, serif',
        'Courier New,Courier,monospace' => 'Courier New, monospace',
        'Roboto,sans-serif' => 'Arial, sans-serif',
        'Open Sans,sans-serif' => 'Arial, sans-serif',
        'Merriweather,serif' => 'Georgia, serif',
        'Poppins,sans-serif' => 'Arial, sans-serif',
        'Lora,serif' => 'Georgia, serif',
        'Source Code Pro,monospace' => 'Courier New, monospace',
    ];

    /**
     * Batas waktu (ms) Chrome headless menunggu sebelum printToPDF
     * dieksekusi — cukup untuk 'load' event (gambar file:// + script
     * pagination inline) selesai jalan lebih dulu, bahkan pada dokumen
     * panjang/banyak gambar.
     */
    private const VIRTUAL_TIME_BUDGET_MS = 8000;

    /**
     * Build a PDF for a document's display content via headless Chrome
     * print-to-pdf — engine render SAMA dengan browser, jadi hasilnya
     * konsisten dengan print di editor Jodit (font asli, CSS @page,
     * pagination browser).
     *
     * @param string|null $paperSizeOverride Ukuran kertas opsional (A4/A5/
     *        A3/Letter/Legal) dari form export di halaman show — override
     *        HANYA untuk export ini, tidak mengubah $document->paper_size.
     *        Kalau null, pakai $document->paper_size (fallback 'A4').
     *
     * @throws BusinessLogicException if the document has no exportable content
     */
    public function export(Document $document, User $user, ?string $paperSizeOverride = null): array
    {
        $display = $document->displayVersion();

        if (!$display || !trim(strip_tags($display->content))) {
            throw new BusinessLogicException('No content available to export.');
        }

        $chrome = $this->findChrome();
        if (!$chrome) {
            throw new BusinessLogicException('Chrome/Edge tidak ditemukan di server untuk generate PDF.');
        }

        $content = app(\App\Services\SignatureResolverService::class)->resolve($display->content, $document, $user, true);
        $content = $this->normalizeContentFonts($content);
        $content = $this->qrCodeService->injectPlaceholder($content, $document);
        $html = $this->buildHtml($document, $this->resolveImagePaths($content), $paperSizeOverride);

        $filename = $this->filename($document);
        $path = 'exports/' . $filename;

        // File HTML temp + output PDF, keduanya di disk private.
        $htmlPath = storage_path('app/private/exports/tmp_' . uniqid() . '.html');
        $pdfPath = storage_path('app/private/' . $path);

        try {
            if (!is_dir(dirname($htmlPath))) {
                @mkdir(dirname($htmlPath), 0755, true);
            }
            file_put_contents($htmlPath, $html);

            // --virtual-time-budget dikasih margin cukup (ms) supaya Chrome
            // headless benar-benar menunggu 'load' event (gambar via
            // file:// + script pagination inline) selesai SEBELUM
            // printToPDF dieksekusi. Tanpa ini, pada dokumen dengan banyak
            // gambar ada risiko printToPDF terpicu sebelum script
            // pagination sempat memasang break-before, sehingga hasil
            // balik lagi mengandalkan heuristik native yang selisih dengan
            // editor.
            $cmd = sprintf(
                '%s --headless=new --disable-gpu --no-pdf-header-footer --virtual-time-budget=%d --print-to-pdf=%s %s 2>&1',
                escapeshellarg($chrome),
                self::VIRTUAL_TIME_BUDGET_MS,
                escapeshellarg($pdfPath),
                escapeshellarg($htmlPath)
            );

            exec($cmd, $output, $exitCode);

            if ($exitCode !== 0 || !is_file($pdfPath) || filesize($pdfPath) === 0) {
                report(new \RuntimeException('Chrome print-to-pdf gagal: ' . implode("\n", $output)));

                throw new BusinessLogicException('PDF generation failed.');
            }
        } finally {
            @unlink($htmlPath);
        }

        return [
            'filename' => $filename,
            'path' => $path,
        ];
    }

    private function findChrome(): ?string
    {
        foreach (self::CHROME_CANDIDATES as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $which = @exec('which google-chrome chromium chromium-browser 2>/dev/null');

        return $which ?: null;
    }

    /**
     * Normalisasi semua deklarasi font-family di dalam konten ke font
     * sistem (lihat PDF_SAFE_FONT_MAP). Parsing DOM (bukan str_replace
     * di seluruh HTML mentah) supaya perubahan hanya menyentuh atribut
     * style, dan pencocokan kebal variasi kutip/spasi serialisasi browser.
     *
     * Kalau parsing gagal, konten asli dikembalikan.
     */
    private function normalizeContentFonts(string $content): string
    {
        if (trim($content) === '') {
            return $content;
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML(
            '<?xml encoding="utf-8"?><div id="__pdf_font_root__">' . $content . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        if (!$loaded) {
            return $content;
        }

        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//*[@style]') as $el) {
            /** @var \DOMElement $el */
            $original = $el->getAttribute('style');
            $normalized = $this->replaceFontFamilyInStyle($original);

            if ($normalized !== $original) {
                $el->setAttribute('style', $normalized);
            }
        }

        $root = $dom->getElementById('__pdf_font_root__');
        if (!$root) {
            return $content;
        }

        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }

        return $html;
    }

    /**
     * Ganti deklarasi "font-family: ..." di dalam SATU string style="...".
     * Bandingkan versi ternormalisasi terhadap PDF_SAFE_FONT_MAP; kalau
     * tidak ada yang cocok, nilai asli dibiarkan.
     */
    private function replaceFontFamilyInStyle(string $style): string
    {
        return preg_replace_callback(
            '/font-family\s*:\s*([^;]+)/i',
            function (array $m): string {
                $raw = trim($m[1]);
                $key = $this->normalizeFontFamilyValue($raw);
                $safe = self::PDF_SAFE_FONT_MAP[$key] ?? null;

                return 'font-family: ' . ($safe ?? $raw);
            },
            $style
        );
    }

    /**
     * Normalisasi nilai font-family untuk pencocokan: buang kutip
     * (literal ' " dan HTML entity), rapikan spasi di sekitar koma.
     */
    private function normalizeFontFamilyValue(string $value): string
    {
        $value = str_replace(['&#39;', '&#039;', '&quot;', '"', "'"], '', $value);
        $parts = array_map('trim', explode(',', $value));

        return implode(',', $parts);
    }

    /**
     * Ganti semua src gambar lokal (relatif /storage/...) menjadi URL
     * file:/// absolut supaya headless Chrome (yang membuka HTML dari
     * disk) bisa memuatnya. URL eksternal/data URI/anchor dibiarkan.
     * Sekaligus skala gambar besar ke lebar konten (preserve aspect ratio).
     */
    private function resolveImagePaths(string $content): string
    {
        return preg_replace_callback(
            '/<img\b[^>]*>/i',
            function (array $m): string {
                $tag = $m[0];

                if (!preg_match('/src=["\']([^"\']+)["\']/i', $tag, $srcM)) {
                    return $tag;
                }
                $src = $srcM[1];

                // URL absolut / data URI / anchor / file — biarkan apa adanya
                if (
                    preg_match('#^(https?:)?//#i', $src)
                    || str_starts_with($src, 'data:')
                    || str_starts_with($src, '#')
                    || str_starts_with($src, 'file://')
                ) {
                    return $tag;
                }

                // Path publik relatif, mis. /storage/jodit-uploads/x.png
                $path = public_path(ltrim($src, '/'));
                if (!is_file($path)) {
                    return $tag;
                }

                // 1) ganti src ke URL file:/// absolut (format Chrome)
                $fileUrl = 'file:///' . str_replace('\\', '/', $path);
                $tag = str_replace($src, $fileUrl, $tag);

                // 2) skala gambar agar tidak melebihi lebar konten
                $size = @getimagesize($path);
                if ($size === false) {
                    return $tag;
                }
                [$nativeW, $nativeH] = $size;

                $targetW = preg_match('/\bwidth=["\']?(\d+)["\']?/i', $tag, $wm)
                    ? min((int) $wm[1], self::MAX_IMG_WIDTH_PX)
                    : min($nativeW, self::MAX_IMG_WIDTH_PX);

                $targetH = (int) round($targetW * $nativeH / $nativeW);

                $tag = preg_replace('/\s(width|height)=["\'][^"\']*["\']/i', '', $tag);
                $tag = preg_replace('/\b(width|height)\s*:\s*[^;"\']+/i', '', $tag);

                return preg_replace(
                    '/\s*\/?>/i',
                    ' width="' . $targetW . '" height="' . $targetH . '" />',
                    $tag,
                    1
                );
            },
            $content
        );
    }

    /**
     * Lebar konten (px) — buffer untuk dokumen A4 portrait dengan margin
     * default kiri+kanan (56+56px).
     */
    private const MAX_IMG_WIDTH_PX = 690;

    /**
     * Ruang tulis minimum per halaman (px) — SAMA PERSIS dengan
     * MIN_PAGE_CONTENT_PX di resources/js/jodit.js. Dipakai untuk mengecek
     * apakah margin dokumen muat di dalam kertas.
     */
    private const MIN_PAGE_CONTENT_PX = 60;

    /**
     * Ukuran kertas (px @96dpi) — SAMA PERSIS dengan PAPER_SIZES di
     * resources/js/jodit.js (sumber kebenaran ukuran kertas editor).
     * Dipakai untuk mengecek apakah margin dokumen muat di dalam kertas.
     */
    private const PAPER_SIZES_PX = [
        'A4' => ['width' => 794, 'height' => 1123],
        'A5' => ['width' => 559, 'height' => 794],
        'A3' => ['width' => 1123, 'height' => 1587],
        'Letter' => ['width' => 816, 'height' => 1056],
        'Legal' => ['width' => 816, 'height' => 1344],
    ];

    private function filename(Document $document): string
    {
        $title = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $document->title) ?: 'document';
        $division = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $document->division?->code ?? 'no_division') ?: 'no_division';
        $date = now()->format('Y-m-d');

        return "{$title}_{$division}_{$date}.pdf";
    }

    /**
     * Konversi px ke in untuk unit @page (96px = 1in) — SAMA PERSIS
     * dengan konversi doPrint() di jodit.js (px/96).
     */
    private function pxToIn(float $px): float
    {
        return round($px / 96, 4);
    }

    /**
     * Ambil margin dokumen (px), fallback ke DEFAULT_MARGIN kalau dokumen
     * belum pernah menyimpan pengaturan margin (48/56/48/56 — sama dengan
     * DEFAULT_MARGIN di jodit.js).
     */
    private function resolveMargin(Document $document): array
    {
        $m = $document->paper_margin ?? [];

        return [
            'top' => $m['top'] ?? self::DEFAULT_MARGIN['top'],
            'right' => $m['right'] ?? self::DEFAULT_MARGIN['right'],
            'bottom' => $m['bottom'] ?? self::DEFAULT_MARGIN['bottom'],
            'left' => $m['left'] ?? self::DEFAULT_MARGIN['left'],
        ];
    }

    /**
     * Bangun script pagination inline yang dijalankan DI DALAM HTML export
     * (bukan lagi mengandalkan heuristik native break-inside:avoid milik
     * Chromium, yang terbukti bisa selisih beberapa baris/elemen dari
     * hasil hitungan JS editor).
     *
     * INI ADALAH PORT LANGSUNG dari algoritma paginateContainer/paginateList
     * di resources/js/jodit.js — tujuannya supaya export menghitung titik
     * potong halaman dengan RUMUS YANG SAMA PERSIS dengan yang dipakai
     * editor & preview (repaginateEditor/repaginatePreview), sehingga hasil
     * PDF dijamin identik, bukan cuma "kemungkinan besar mirip".
     *
     * Beda pendekatan dari editor:
     * - Editor menyisipkan <div data-page-spacer> (elemen visual asli) untuk
     *   mensimulasikan jeda antar halaman di layar, makanya nextBoundary di
     *   sana ikut menambahkan `gap` (tinggi spacer) supaya koordinat elemen
     *   berikutnya (yang secara fisik terdorong oleh spacer) tetap sinkron.
     * - Script ini TIDAK memasang elemen apa pun ke DOM (tidak ada spacer),
     *   ia hanya MENANDAI elemen/<li> yang harus mulai halaman baru dengan
     *   `break-before: page`. Karena tidak ada elemen yang benar-benar
     *   digeser secara fisik, seluruh perhitungan tetap berada di sistem
     *   koordinat "flat" (rata, non-tergeser) dari awal sampai akhir — jadi
     *   TIDAK PERLU menambahkan `gap` ke nextBoundary sama sekali. Titik
     *   potong yang dihasilkan (elemen/<li> mana yang jatuh di halaman
     *   keberapa) tetap identik dengan hasil hitungan editor, karena "flat
     *   layout dipotong tiap kelipatan contentPerPage" itulah yang
     *   sebenarnya MENENTUKAN pembagian halaman di editor — spacer di sana
     *   cuma representasi visualnya, bukan penentu pembagiannya.
     * - List (<ul>/<ol>) TIDAK perlu dipecah jadi dua elemen terpisah
     *   (seperti paginateList di editor, yang butuh trik atribut `start`
     *   supaya nomor tetap nyambung) — karena break-before bisa langsung
     *   ditempel ke <li> tanpa mengubah struktur DOM, nomor urut otomatis
     *   tetap benar (masih satu <ol> yang sama).
     *
     * $contentPerPage: SAMA PERSIS dengan `size.height - margin.top -
     * margin.bottom` yang dipakai repaginateEditor di jodit.js. Dihitung di
     * buildHtml() dari margin & ukuran kertas yang SUDAH di-clamp, supaya
     * konsisten dengan nilai yang dipakai @page di sana.
     */
    private function buildPaginationScript(int $contentPerPage): string
    {
        return <<<JS
        <script>
        (function () {
            var CONTENT_PER_PAGE = {$contentPerPage};

            function crosses(relTop, relBottom, boundary) {
                return (relBottom > boundary && relTop < boundary)
                    || (relTop >= boundary && relTop < boundary + CONTENT_PER_PAGE);
            }

            function markBreak(el) {
                el.style.breakBefore = 'page';
                el.style.pageBreakBefore = 'always';
            }

            function paginate() {
                var container = document.querySelector('.paper');
                if (!container || !container.firstElementChild) return;

                var containerTop = container.getBoundingClientRect().top;
                var nextBoundary = CONTENT_PER_PAGE;
                var child = container.firstElementChild;

                while (child) {
                    if (child.tagName === 'OL' || child.tagName === 'UL') {
                        var items = Array.prototype.filter.call(child.children, function (el) {
                            return el.tagName === 'LI';
                        });

                        for (var i = 0; i < items.length; i++) {
                            var li = items[i];
                            var rect = li.getBoundingClientRect();
                            var relTop = rect.top - containerTop;
                            var relBottom = relTop + rect.height;
                            var tallerThanPage = rect.height > CONTENT_PER_PAGE;

                            while (crosses(relTop, relBottom, nextBoundary)) {
                                markBreak(li);
                                nextBoundary += CONTENT_PER_PAGE;
                                if (tallerThanPage) break;
                            }
                        }

                        child = child.nextElementSibling;
                        continue;
                    }

                    var rect2 = child.getBoundingClientRect();
                    var relTop2 = rect2.top - containerTop;
                    var relBottom2 = relTop2 + rect2.height;
                    var tallerThanPage2 = rect2.height > CONTENT_PER_PAGE;

                    while (crosses(relTop2, relBottom2, nextBoundary)) {
                        markBreak(child);
                        nextBoundary += CONTENT_PER_PAGE;
                        if (tallerThanPage2) break;
                    }

                    child = child.nextElementSibling;
                }
            }

            if (document.readyState === 'complete') {
                paginate();
            } else {
                window.addEventListener('load', paginate);
            }
        })();
        </script>
        JS;
    }

    private function buildHtml(Document $document, string $content, ?string $paperSizeOverride = null): string
    {
        $margin = $this->resolveMargin($document);
        // FIX: paperSizeOverride (dari form export halaman show) menang
        // atas paper_size tersimpan di dokumen — tapi HANYA untuk
        // rendering export ini, $document->paper_size sendiri tidak diubah.
        $paperSize = $paperSizeOverride ?? $document->paper_size ?? 'A4';

        // Clamp margin ke ukuran kertas: margin total (atas+bawah / kiri+kanan)
        // tidak boleh melebihi ukuran kertas. Tanpa ini, @page margin yang lebih
        // besar dari halaman membuat Chrome/Edge headless JATUH ke ukuran kertas
        // default (mis. Letter) dan margin diabaikan total — konten yang di
        // editor kelihatan di bawah halaman (margin besar) malah muncul di paling
        // atas halaman saat export. Nilai ini SAMA PERSIS dengan
        // clampMarginToPage() di resources/js/jodit.js — termasuk saat paperSize
        // di-override dari request export (bukan cuma dari $document->paper_size),
        // dua-duanya sekarang memakai $paperSize yang sudah final di atas.
        $page = self::PAPER_SIZES_PX[$paperSize] ?? self::PAPER_SIZES_PX['A4'];
        if ($margin['top'] + $margin['bottom'] > $page['height'] - self::MIN_PAGE_CONTENT_PX) {
            $margin['top'] = max(0, $page['height'] - self::MIN_PAGE_CONTENT_PX - $margin['bottom']);
        }
        if ($margin['left'] + $margin['right'] > $page['width'] - self::MIN_PAGE_CONTENT_PX) {
            $margin['left'] = max(0, $page['width'] - self::MIN_PAGE_CONTENT_PX - $margin['right']);
        }

        $topIn = $this->pxToIn($margin['top']);
        $rightIn = $this->pxToIn($margin['right']);
        $bottomIn = $this->pxToIn($margin['bottom']);
        $leftIn = $this->pxToIn($margin['left']);

        // Lebar tulis (px) — SAMA PERSIS dengan lebar konten editor:
        // body.style.width = size.width (box-sizing:border-box, padding
        // kiri+kanan termasuk di dalamnya) → lebar konten aktual =
        // size.width - margin.left - margin.right. Di export, .paper TIDAK
        // punya padding (margin fisik sudah ditangani @page), jadi kita
        // beri width eksplisit = lebar konten itu langsung (content-box).
        // WAJIB eksplisit (bukan mengandalkan lebar viewport headless
        // Chrome yang bisa beda-beda) supaya word-wrap & tinggi tiap
        // elemen persis sama dengan yang dihitung editor — tanpa ini,
        // perhitungan pagination JS di buildPaginationScript() bisa salah
        // walau rumusnya sama, karena teksnya membungkus di lebar berbeda.
        $contentWidth = $page['width'] - $margin['left'] - $margin['right'];

        // contentPerPage — SAMA PERSIS dengan `size.height - margin.top -
        // margin.bottom` di repaginateEditor (jodit.js). Ini nilai tunggal
        // yang menentukan tinggi satu halaman dari sisi konten (tanpa
        // margin), dipakai oleh script pagination di bawah.
        $contentPerPage = max($page['height'] - $margin['top'] - $margin['bottom'], 1);

        $paginationScript = '';

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&family=Open+Sans:ital,wght@0,300..800;1,300..800&family=Merriweather:ital,wght@0,300;0,400;0,700;0,900;1,400&family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=Lora:ital,wght@0,400..700;1,400..700&family=Source+Code+Pro:ital,wght@0,400;0,700;1,400&display=swap');
                /* Ukuran kertas & margin fisik — SAMA dengan @page yang
                   dibangun doPrint() di jodit.js (in, px/96). Browser
                   (Chrome headless) menerapkan margin ini ke SETIAP halaman,
                   jadi konsisten dengan print editor. */
                @page {
                    size: {$paperSize} portrait;
                    margin: {$topIn}in {$rightIn}in {$bottomIn}in {$leftIn}in;
                }
                html, body { margin: 0; padding: 0; }
                /* WYSIWYG fix: base typography identical to buildIframeStyle() in jodit.js
                   and .doku-paper in _paper.blade.php — color changed #111 → #000. */
                body { font-family: 'Times New Roman', Times, serif; font-weight: normal; font-size: 16px; line-height: normal; color: #000; overflow-wrap: break-word; word-break: break-word; orphans: 1; widows: 1; }
                .paper {
                    width: {$contentWidth}px;
                    box-sizing: content-box;
                }
                table { width: 100%; border: none; border-collapse: collapse; empty-cells: show; max-width: 100%; }
                /* WYSIWYG fix: vertical-align:top matches editor (th, td { vertical-align:top !important })
                   and preview (.doku-paper th/td { vertical-align: top }) — was missing in export. */
                table th, table td { border: 1px solid #ccc; padding: 2px 5px; vertical-align: top; }
                img { max-width: 100%; height: auto; }
                /* WYSIWYG fix: extend word-break to all content elements (matches buildIframeStyle). */
                p, div, td, th, li, h1, h2, h3, h4, h5, h6 { overflow-wrap: break-word; word-break: break-word; }
            </style>
        </head>
        <body>
            <div class="paper">{$content}</div>
            {$paginationScript}
        </body>
        </html>
        HTML;
    }
}