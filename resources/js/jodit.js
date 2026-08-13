import { Jodit } from 'jodit';
import 'jodit/es2021/jodit.min.css';
import 'jodit/esm/plugins/all.js'; // registrasi semua plugin bawaan (wajib biar icon & fungsi lengkap)

// Daftar Google Fonts yang dipakai di dokumen — SATU sumber kebenaran,
// dipakai baik untuk import ke iframe MAUPUN untuk isi dropdown toolbar.
// key   = value CSS font-family (harus match persis dgn nama di Google Fonts)
// label = teks yang tampil di dropdown toolbar
const GOOGLE_FONTS_URL =
    'https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900' +
    '&family=Open+Sans:ital,wght@0,300..800;1,300..800' +
    '&family=Merriweather:ital,wght@0,300;0,400;0,700;0,900;1,400' +
    '&family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400' +
    '&family=Lora:ital,wght@0,400..700;1,400..700' +
    '&family=Source+Code+Pro:ital,wght@0,400;0,700;1,400' +
    '&display=swap';

const FONT_LIST = {
    'Default': 'Default',
    'Arial,Helvetica,sans-serif': 'Arial',
    'Georgia,serif': 'Georgia',
    '"Times New Roman",Times,serif': 'Times New Roman',
    '"Courier New",Courier,monospace': 'Courier New',
    // --- Google Fonts ---
    'Roboto,sans-serif': 'Roboto',
    '"Open Sans",sans-serif': 'Open Sans',
    'Merriweather,serif': 'Merriweather',
    'Poppins,sans-serif': 'Poppins',
    'Lora,serif': 'Lora',
    '"Source Code Pro",monospace': 'Source Code Pro',
};

// Ukuran kertas (px @96dpi). key = label yang tampil di dropdown.
// PENTING: nilai height di sini adalah SATU-SATUNYA sumber kebenaran untuk
// tinggi halaman — dipakai baik untuk simulasi page-break di editor
// (repaginateEditor) MAUPUN untuk memaksa ukuran halaman fisik saat print/
// export (lihat controls.print.exec di bawah, yang set @page { size }).
// Karena dua-duanya baca dari objek yang sama, keduanya dijamin selalu sinkron.
//
// PENTING JUGA: nilai px di sini WAJIB SAMA PERSIS dengan PAPER_SIZES_PX di
// app/Services/PdfExportService.php — kalau salah satu berubah, yang lain
// harus ikut disesuaikan manual (PHP tidak bisa import dari file JS ini).
const PAPER_SIZES = {
    'A4': { width: 794, height: 1123 },
    'A5': { width: 559, height: 794 },
    'A3': { width: 1123, height: 1587 },
    'Letter': { width: 816, height: 1056 },
    'Legal': { width: 816, height: 1344 },
};

// Margin default (px), match dengan padding lama "48px 56px" (atas/bawah 48,
// kanan/kiri 56). Ini SATU-SATUNYA sumber kebenaran untuk margin halaman —
// dipakai di iframeStyle (editor & preview) dan dibaca lagi saat print/export.
// UI margin menampilkan cm (dikonversi), nilai internal tetap px.
//
// PENTING: nilai ini WAJIB SAMA PERSIS dengan DEFAULT_MARGIN di
// app/Services/PdfExportService.php.
const DEFAULT_MARGIN = { top: 48, right: 56, bottom: 48, left: 56 };

// Ruang tulis minimum per halaman (px) — SAMA PERSIS dengan
// MIN_PAGE_CONTENT_PX di app/Services/PdfExportService.php. Dipakai oleh
// clampMarginToPage() di bawah untuk membatasi margin supaya tidak
// "memakan" seluruh halaman.
const MIN_PAGE_CONTENT_PX = 60;

// Clamp margin ke ukuran kertas: margin gabungan (top+bottom / left+right)
// tidak boleh melebihi ukuran kertas dikurangi ruang tulis minimum.
function clampMarginToPage(size, margin) {
    const clamped = { ...margin };
    if (clamped.top + clamped.bottom > size.height - MIN_PAGE_CONTENT_PX) {
        clamped.top = Math.max(0, size.height - MIN_PAGE_CONTENT_PX - clamped.bottom);
    }
    if (clamped.left + clamped.right > size.width - MIN_PAGE_CONTENT_PX) {
        clamped.left = Math.max(0, size.width - MIN_PAGE_CONTENT_PX - clamped.right);
    }
    return clamped;
}

// Cari nama key ukuran kertas (A4/A5/...) dari objek size — dipakai untuk
// menyimpan pilihan kertas ke localStorage supaya halaman preview tahu
// ukuran mana yang aktif di editor. Pencocokan by reference aman karena
// semua pemanggil selalu meneruskan objek dari PAPER_SIZES yang sama.
function findPaperKey(size) {
    for (const key of Object.keys(PAPER_SIZES)) {
        if (PAPER_SIZES[key] === size) return key;
    }
    return null;
}

// Bangun CSS iframeStyle dari ukuran kertas + margin. Dipakai saat init
// editor DAN saat preview/print (yang regenerate dokumen via iframeStyle).
function buildIframeStyle(size, margin = DEFAULT_MARGIN) {
    const padding = `${margin.top}px ${margin.right}px ${margin.bottom}px ${margin.left}px`;
    return [
        `@import url('${GOOGLE_FONTS_URL}');`,
        'html { margin:0; padding:0; background:#e5e7eb; }',
        'body {',
        '  box-sizing:border-box;',
        `  width:${size.width}px;`,
        '  margin:0 auto;',
        `  padding:${padding};`,
        '  background:#fff;',
        `  min-height:${size.height}px;`,
        '  box-shadow:0 1px 3px rgba(0,0,0,0.1);',
        '}',
        'table { width:100%; border:none; border-collapse:collapse; empty-cells:show; max-width:100%; }',
        'th, td { padding:2px 5px; border:1px solid #ccc; vertical-align:top !important; }',
        'body, p, div, td, th, li, h1, h2, h3, h4, h5, h6 { overflow-wrap:break-word; word-break:break-word; }',
    ].join('\n');
}

// Terapkan ukuran kertas + margin ke editor yang sudah hidup: update
// iframeStyle (biar preview/print konsisten) + inline style body (biar live
// editor langsung berubah tanpa reload) + tinggi container mengikuti kertas.
// size dan margin sama-sama opsional — kalau tidak dikasih, pakai yang lagi
// aktif di editor (atau default kalau belum pernah diset sama sekali).
function applyPaperSize(editor, size, margin) {
    size = size || editor.currentPaperSize || PAPER_SIZES['A4'];
    margin = clampMarginToPage(size, margin || editor.currentMargin || DEFAULT_MARGIN);

    editor.o.iframeStyle = buildIframeStyle(size, margin);
    editor.currentPaperSize = size;
    editor.currentMargin = margin;

    const storageKey = editor.element?.dataset?.liveStorage;
    if (storageKey) {
        const paperKey = findPaperKey(size);
        try {
            localStorage.setItem(storageKey + ':paper', JSON.stringify({
                size: paperKey || size,
                margin,
            }));
        } catch (e) { /* localStorage penuh / private mode — abaikan */ }
    }

    const body = editor.editor;
    if (!body) return;
    Object.assign(body.style, {
        width: size.width + 'px',
        padding: `${margin.top}px ${margin.right}px ${margin.bottom}px ${margin.left}px`,
    });
    const containerH = size.height + margin.top + margin.bottom + 64 + 15;
    editor.container.style.minHeight = containerH + 'px';
    editor.e.fire('resize');
    editor.e.fire('afterResize');
    requestAnimationFrame(() => {
        requestAnimationFrame(() => repaginateEditor(editor));
    });
}

// Bangun elemen spacer visual antar "halaman" di editor/preview.
// CATATAN: spacer di sini MURNI VISUAL/PERKIRAAN, tidak menentukan titik
// potong halaman saat cetak — itu diserahkan ke native pagination browser
// (lihat doPrint() + buildPrintStyle()).
function buildSpacerElement(margin, gap, extraAttrs) {
    const spacer = document.createElement('div');
    spacer.setAttribute('data-page-spacer', 'true');
    spacer.setAttribute('contenteditable', 'false');
    if (extraAttrs) {
        Object.entries(extraAttrs).forEach(([k, v]) => spacer.setAttribute(k, v));
    }
    Object.assign(spacer.style, {
        margin: `0 -${margin.right}px 0 -${margin.left}px`,
        pointerEvents: 'none',
        userSelect: 'none',
    });

    // FIX QR (print): saat forPrint, ganti placeholder QR (lihat control
    // "qrCode" di atas) jadi <img> QR asli yang di-fetch dari server
    // (route documents.qrcode, lihat data-qr-image-url di textarea) —
    // supaya QR beneran ke-print & bisa di-scan. Saat forPrint=false
    // (disimpan ke DB / draft localStorage), placeholder TETAP teks biasa
    // — QR baru "hidup" saat konten ditampilkan lewat halaman show/preview
    // (lihat QrCodeService::injectPlaceholder di server) atau saat print
    // ini sendiri, bukan disimpan sebagai gambar beku.
    if (forPrint) {
        const qrImageUrl = editor.element?.dataset?.qrImageUrl;
        if (qrImageUrl) {
            // Ukuran QR di-baca dari TEKS placeholder ("[QR CODE DOKUMEN
            // 150px]"), BUKAN dari atribut data-qr-size — Jodit membuang
            // semua atribut data-* saat clean-html/save, jadi atribut tidak
            // bisa diandalkan untuk menyimpan ukuran. Teks tidak pernah kena
            // strip, jadi ukuran dijamin selalu ikut walau atribut
            // data-qr-placeholder sendiri ikut hilang.
            doc.querySelectorAll('[data-qr-placeholder], span[style*="dashed"]').forEach((el) => {
                const match = el.textContent.match(/\[QR CODE DOKUMEN\s*(\d+)px\]/i);
                if (!match) return;
                const size = Math.max(40, Math.min(400, parseInt(match[1], 10) || 120));
                const img = doc.createElement('img');
                img.src = qrImageUrl;
                img.alt = 'QR Code Dokumen';
                img.style.cssText = `width:${size}px;height:${size}px;vertical-align:middle;`;
                el.replaceWith(img);
            });
        }
    }

    doc.querySelectorAll('[data-page-spacer]').forEach((el) => {
        if (forPrint) {
            const pageBreak = doc.createElement('div');
            pageBreak.setAttribute('data-page-break', 'true');
            pageBreak.style.cssText = 'height:0;margin:0;padding:0;border:0;' +
                'break-after:page;page-break-after:always;';
            el.replaceWith(pageBreak);
        } else {
            el.remove();
        }
    });
    const gapBandHeight = Math.max(2, Math.min(24, Math.round(gap * 0.3)));
    const remaining = gap - gapBandHeight;
    const beforeHeight = gap > 0 ? Math.round(remaining * (margin.bottom / gap)) : 0;
    const afterHeight = remaining - beforeHeight;

    const endPart = document.createElement('div');
    Object.assign(endPart.style, {
        height: beforeHeight + 'px',
        background: '#fff',
        boxShadow: '0 2px 4px -1px rgba(0,0,0,0.15)',
    });

    const gapLine = document.createElement('div');
    Object.assign(gapLine.style, {
        height: gapBandHeight + 'px',
        background: '#cbd5e1',
        borderTop: '1px solid #94a3b8',
        borderBottom: '1px solid #94a3b8',
        boxSizing: 'border-box',
    });

    const startPart = document.createElement('div');
    Object.assign(startPart.style, {
        height: afterHeight + 'px',
        background: '#fff',
    });

    spacer.appendChild(endPart);
    spacer.appendChild(gapLine);
    spacer.appendChild(startPart);
    return spacer;
}

// Sebelum menghitung ulang pagination (atau sebelum ekstrak nilai bersih
// buat disimpan), gabungkan lagi list yang sebelumnya sempat dipecah jadi
// dua elemen oleh paginateList() — supaya perhitungan/penyimpanan selalu
// mulai dari struktur dokumen yang FLAT (satu list utuh), bukan dari hasil
// pecahan iterasi sebelumnya yang sudah basi.
function mergeSplitLists(container) {
    container.querySelectorAll(':scope > [data-page-spacer][data-list-continuation]').forEach((spacer) => {
        const prevList = spacer.previousElementSibling;
        const nextList = spacer.nextElementSibling;
        if (
            prevList && nextList &&
            prevList.tagName === nextList.tagName &&
            (prevList.tagName === 'OL' || prevList.tagName === 'UL')
        ) {
            while (nextList.firstChild) prevList.appendChild(nextList.firstChild);
            nextList.remove();
        }
        spacer.remove();
    });
}

// Proses satu <ol>/<ul>: cari <li> pertama yang kepotong batas halaman,
// pecah list di situ (item sebelumnya tetap di list asli, item itu dst
// pindah ke list baru yang nomornya nyambung), sisipkan spacer di antara
// keduanya, lalu lanjut evaluasi list baru itu terhadap batas halaman
// berikutnya (jaga-jaga kalau dia sendiri masih kepanjangan untuk 1 halaman).
function paginateList(list, containerTop, paddingTop, contentPerPage, gap, margin, startBoundary) {
    let nextBoundary = startBoundary;
    let current = list;

    while (current) {
        const items = Array.from(current.children).filter((el) => el.tagName === 'LI');
        if (items.length === 0) break;

        let splitAt = null;
        for (const li of items) {
            const rect = li.getBoundingClientRect();
            const relTop = rect.top - containerTop - paddingTop;
            const relBottom = relTop + rect.height;

            if (
                (relBottom > nextBoundary && relTop < nextBoundary) ||
                (relTop >= nextBoundary && relTop < nextBoundary + contentPerPage)
            ) {
                splitAt = li;
                const tallerThanPage = rect.height > contentPerPage;
                nextBoundary += contentPerPage + gap;
                // Jaga-jaga: kalau satu <li> ini sendiri masih melewati
                // batas halaman BARU juga (item pendek tapi ada banyak
                // halaman kosong dilompati sebelumnya), majukan terus
                // batasnya sampai benar-benar mengapit item ini.
                if (!tallerThanPage) {
                    let stillCrossing = true;
                    while (stillCrossing) {
                        const r2 = li.getBoundingClientRect();
                        const rt2 = r2.top - containerTop - paddingTop;
                        const rb2 = rt2 + r2.height;
                        stillCrossing = rb2 > nextBoundary && rt2 < nextBoundary;
                        if (stillCrossing) nextBoundary += contentPerPage + gap;
                    }
                }
                break;
            }
        }

        if (!splitAt) break; // sisa item di list ini semua muat di halaman sekarang

        if (splitAt === items[0]) {
            // Item pertama SEGMEN INI saja sudah harus pindah halaman →
            // seluruh sisa list (current) didorong utuh, cukup 1 spacer.
            const spacer = buildSpacerElement(margin, gap);
            current.parentNode.insertBefore(spacer, current);
            break;
        }

        // Pecah: item sebelum splitAt tetap di `current`, splitAt dst
        // dipindah ke list baru yang meneruskan nomor urut (atribut
        // `start` untuk <ol>) supaya penomoran tidak reset ke 1.
        const newList = document.createElement(current.tagName);
        Array.from(current.attributes).forEach((attr) => newList.setAttribute(attr.name, attr.value));
        if (current.tagName === 'OL') {
            const priorStart = parseInt(current.getAttribute('start') || '1', 10);
            const movedCount = items.indexOf(splitAt);
            newList.setAttribute('start', String(priorStart + movedCount));
        }

        let node = splitAt;
        while (node) {
            const nextNode = node.nextSibling;
            newList.appendChild(node);
            node = nextNode;
        }

        const spacer = buildSpacerElement(margin, gap, { 'data-list-continuation': 'true' });
        current.parentNode.insertBefore(spacer, current.nextSibling);
        current.parentNode.insertBefore(newList, spacer.nextSibling);

        current = newList; // lanjut evaluasi sisa item di segmen baru ini
    }

    return { nextBoundary, nextChild: current ? current.nextElementSibling : null };
}

// Jalankan pagination di satu container (body editor ATAU .doku-paper
// preview) — dipakai bareng oleh repaginateEditor & repaginatePreview supaya
// logikanya SATU tempat, tidak dobel kode dan tidak bisa saling melenceng.
function paginateContainer(container, contentPerPage, gap, margin) {
    const paddingTop = parseFloat(getComputedStyle(container).paddingTop) || 0;
    const containerTop = container.getBoundingClientRect().top;
    let nextBoundary = contentPerPage;
    let child = container.firstElementChild;

    while (child) {
        if (child.tagName === 'OL' || child.tagName === 'UL') {
            const result = paginateList(child, containerTop, paddingTop, contentPerPage, gap, margin, nextBoundary);
            nextBoundary = result.nextBoundary;
            child = result.nextChild;
            continue;
        }

        const rect = child.getBoundingClientRect();
        const relTop = rect.top - containerTop - paddingTop;
        const relBottom = relTop + rect.height;
        const elementTallerThanPage = rect.height > contentPerPage;

        while (
            (relBottom > nextBoundary && relTop < nextBoundary) ||
            (relTop >= nextBoundary && relTop < nextBoundary + contentPerPage)
        ) {
            const spacer = buildSpacerElement(margin, gap);
            child.parentNode.insertBefore(spacer, child);
            nextBoundary += contentPerPage + gap;
            if (elementTallerThanPage) break;
        }

        child = child.nextElementSibling;
    }
}

// Ambil HTML editor tanpa elemen spacer pagination — WAJIB dipakai tiap kali
// konten mau disimpan/di-preview, supaya jeda visual antar halaman di editor
// TIDAK ikut kesimpen sebagai bagian dari dokumen asli.
//
// forPrint (opsional, default false): HANYA dipakai oleh doPrint() untuk
// mengganti placeholder QR ("[QR CODE DOKUMEN 120px]") jadi <img> QR asli
// yang di-fetch dari server (lihat data-qr-image-url di textarea), supaya
// QR beneran ke-print & bisa discan. Saat forPrint=false (disimpan ke DB /
// draft localStorage), placeholder TETAP teks biasa — QR baru "hidup" saat
// konten ditampilkan lewat halaman show/preview (server-side, lihat
// QrCodeService::injectPlaceholder) atau saat print via jalur ini sendiri,
// bukan disimpan sebagai gambar beku.
//
// PENTING: forPrint TIDAK mengubah pagination sama sekali — arsitektur
// print/export TIDAK LAGI memaksakan titik potong dari spacer editor (lihat
// catatan di buildPrintStyle()); ini murni penggantian placeholder QR.
function getCleanValue(editor, forPrint = false) {
    const raw = editor.value;
    const doc = new DOMParser().parseFromString(raw, 'text/html');
    mergeSplitLists(doc.body);
    doc.querySelectorAll('[data-page-spacer]').forEach((el) => el.remove());

    if (forPrint) {
        const qrImageUrl = editor.element?.dataset?.qrImageUrl;
        if (qrImageUrl) {
            // Ukuran QR di-baca dari TEKS placeholder ("[QR CODE DOKUMEN
            // 150px]"), BUKAN dari atribut data-qr-size — Jodit membuang
            // semua atribut data-* saat clean-html/save, jadi atribut tidak
            // bisa diandalkan untuk menyimpan ukuran. Teks tidak pernah kena
            // strip, jadi ukuran dijamin selalu ikut walau atribut
            // data-qr-placeholder sendiri ikut hilang.
            doc.querySelectorAll('[data-qr-placeholder], span[style*="dashed"]').forEach((el) => {
                const match = el.textContent.match(/\[QR CODE DOKUMEN\s*(\d+)px\]/i);
                if (!match) return;
                const size = Math.max(40, Math.min(400, parseInt(match[1], 10) || 120));
                const img = doc.createElement('img');
                img.src = qrImageUrl;
                img.alt = 'QR Code Dokumen';
                img.style.cssText = `width:${size}px;height:${size}px;vertical-align:middle;`;
                el.replaceWith(img);
            });
        }
    }

    return doc.body.innerHTML;
}

// Simulasikan margin nyata antar "halaman visual" di editor: begitu konten
// nyampe/ngelewatin batas satu halaman (size.height), sisipkan elemen jeda
// (non-editable) setinggi margin.bottom + margin.top TEPAT SEBELUM elemen
// yang jatuh ke halaman berikutnya — supaya elemen itu betul-betul terdorong
// turun sejauh margin, bukan cuma ada garis penanda doang.
//
// CATATAN: spacer di sini MURNI VISUAL/PERKIRAAN untuk kenyamanan menulis di
// editor. Dia TIDAK menentukan titik potong halaman saat cetak/export — itu
// diserahkan ke browser native pagination (lihat doPrint()). Jadi kalaupun
// perkiraan di sini meleset dikit dari hasil cetak sungguhan, itu TIDAK akan
// membuat hasil cetak amburadul.
//
// Batasan yang disadari (approximation, bukan pagination sungguhan kayak
// Word/Google Docs):
// - Elemen non-list (paragraf, gambar, tabel, dst) TIDAK pernah dipecah di
//   tengah; kalau melewati batas halaman, seluruh elemen itu utuh didorong
//   ke halaman berikutnya.
// - <ol>/<ul> BOLEH dipecah antar <li> (lihat paginateList) — satu item list
//   tetap tidak pernah dipecah di tengah, tapi listnya sendiri bisa
//   bersambung lintas halaman seperti dokumen normal.
// - Elemen yang lebih tinggi dari satu halaman penuh (tabel/gambar raksasa)
//   TIDAK otomatis terpotong di sini — dia cuma didorong ke halaman
//   berikutnya dan dibiarkan "meluber" turun.
function repaginateEditor(editor) {
    const body = editor.editor;
    if (!body || editor._isRepaginating) return;

    const size = editor.currentPaperSize || PAPER_SIZES['A4'];
    const margin = editor.currentMargin || DEFAULT_MARGIN;
    const gap = margin.top + margin.bottom;

    editor._isRepaginating = true; // cegah rekursi dari 'change' yang terpicu oleh mutasi kita sendiri
    editor._suppressSpacerObserver = true;
    try {
        // 1. Gabungkan lagi list yang sempat dipecah, LALU buang semua
        //    spacer lama → perhitungan selalu mulai dari kondisi "flat"
        //    (posisi & struktur asli elemen tanpa jeda/pecahan buatan).
        mergeSplitLists(body);
        body.querySelectorAll(':scope > [data-page-spacer]').forEach((el) => el.remove());

        if (!body.firstElementChild) {
            editor.synchronizeValues();
            return;
        }

        const contentPerPage = Math.max(size.height - margin.top - margin.bottom, 1);
        paginateContainer(body, contentPerPage, gap, margin);

        editor.synchronizeValues();
    } finally {
        editor._isRepaginating = false;
        queueMicrotask(() => { editor._suppressSpacerObserver = false; });
    }
}

// Throttle berbasis requestAnimationFrame supaya repaginateEditor tidak jalan
// dobel-dobel di tiap keystroke, TAPI tetap terasa instan.
function scheduleRepaginate(editor) {
    if (editor._repaginateRAF) return;
    editor._repaginateRAF = requestAnimationFrame(() => {
        editor._repaginateRAF = null;
        repaginateEditor(editor);
    });
}

// ─── Pagination untuk halaman PREVIEW (non-editor) ────────────────────────
// Preview (show / preview / preview-version) menampilkan konten sebagai satu
// kotak kertas statis (.doku-paper) tanpa batas antar halaman. Fungsi ini
// menyisipkan spacer pembatas halaman yang SAMA PERSIS dengan yang dipakai
// editor (repaginateEditor via paginateContainer), termasuk pemecahan
// <ol>/<ul> per item, supaya preview dan editor selalu konsisten SECARA
// VISUAL. (Cetak/export tidak lagi bergantung pada spacer ini.)
function repaginatePreview(paperEl, size, margin) {
    if (!paperEl) return;
    size = size || PAPER_SIZES['A4'];
    margin = clampMarginToPage(size, margin || DEFAULT_MARGIN);
    const gap = margin.top + margin.bottom;

    paperEl.style.width = size.width + 'px';
    paperEl.style.minHeight = size.height + 'px';
    paperEl.style.padding = `${margin.top}px ${margin.right}px ${margin.bottom}px ${margin.left}px`;

    mergeSplitLists(paperEl);
    paperEl.querySelectorAll(':scope > [data-page-spacer]').forEach((el) => el.remove());

    if (!paperEl.firstElementChild) return;

    const contentPerPage = Math.max(size.height - margin.top - margin.bottom, 1);
    paginateContainer(paperEl, contentPerPage, gap, margin);
}

// Baca ukuran kertas & margin yang disimpan editor untuk dokumen ini.
// storageKey = nilai data-live-storage (mis. "doc-preview-3").
function readStoredPaper(storageKey) {
    try {
        const raw = localStorage.getItem(storageKey + ':paper');
        if (!raw) return null;
        const data = JSON.parse(raw);
        const size = typeof data.size === 'string' && PAPER_SIZES[data.size]
            ? PAPER_SIZES[data.size]
            : (data.size && data.size.width ? data.size : null);
        const margin = data.margin && data.margin.top != null ? data.margin : null;
        if (!size || !margin) return null;
        return { size, margin };
    } catch (e) {
        return null;
    }
}

// Inisialisasi pagination untuk halaman preview: cari .doku-paper, baca
// ukuran kertas dari localStorage, lalu sisipkan spacer.
function initPreviewPagination(scopeSelector = '.doku-paper-scope') {
    const scope = typeof scopeSelector === 'string'
        ? document.querySelector(scopeSelector)
        : scopeSelector;
    const paper = scope?.querySelector('.doku-paper');
    if (!paper) return;

    const storageKey = scope.dataset?.liveStorage;
    const stored = storageKey ? readStoredPaper(storageKey) : null;
    let size = stored?.size || null;
    let margin = stored?.margin || null;
    if (!size && scope.dataset.paperSize && PAPER_SIZES[scope.dataset.paperSize]) {
        size = PAPER_SIZES[scope.dataset.paperSize];
    }
    if (!margin && scope.dataset.paperMargin) {
        try {
            const m = JSON.parse(scope.dataset.paperMargin);
            if (m && m.top != null) margin = m;
        } catch (e) { /* abaikan */ }
    }
    size = size || PAPER_SIZES['A4'];
    margin = margin || DEFAULT_MARGIN;

    repaginatePreview(paper, size, margin);

    if (document.fonts?.ready) {
        document.fonts.ready
            .then(() => repaginatePreview(paper, size, margin))
            .catch(() => { /* font gagal load — biarkan, jangan block apa pun */ });
    }

    const select = scope.querySelector('[data-paper-size-select]');
    if (select) {
        select.value = findPaperKey(size) || 'A4';
        select.addEventListener('change', () => {
            const key = select.value;
            const newSize = PAPER_SIZES[key];
            if (!newSize) return;
            repaginatePreview(paper, newSize, margin);
            if (storageKey) {
                try {
                    localStorage.setItem(storageKey + ':paper', JSON.stringify({ size: key, margin }));
                } catch (e) { /* abaikan */ }
            }
        });
    }
}


// Margin internal disimpan dalam px (DEFAULT_MARGIN). Popup menampilkan
// dan menerima nilai dalam cm: 1cm = 96/2.54 px.
const PX_PER_CM = 96 / 2.54;

// Popup tombol "Sisip QR Code": pilih ukuran QR (px) lalu insert placeholder
// ke posisi kursor. Ukuran dipakai sebagai width/height <img> saat render
// final (server: QrCodeService::injectPlaceholder; print: getCleanValue
// dengan forPrint=true).
function buildQrPopup(editor, close) {
    const wrapper = document.createElement('div');
    wrapper.style.cssText = 'padding:12px; display:flex; flex-direction:column; gap:8px; min-width:220px; background:#fff;';

    const title = document.createElement('div');
    title.textContent = 'Ukuran QR Code';
    title.style.cssText = 'font-weight:600; margin-bottom:4px; color:#1a1a1a;';
    wrapper.appendChild(title);

    const input = document.createElement('input');
    input.type = 'number';
    input.min = '40';
    input.max = '400';
    input.step = '10';
    input.value = '120';
    input.style.cssText = 'width:100%; padding:6px 8px; border:1px solid #ccc; border-radius:4px; box-sizing:border-box;';
    wrapper.appendChild(input);

    const hint = document.createElement('div');
    hint.textContent = 'px (40–400)';
    hint.style.cssText = 'color:#6b7280; font-size:12px;';
    wrapper.appendChild(hint);

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = 'Sisipkan';
    btn.style.cssText = 'margin-top:6px; padding:6px 10px; cursor:pointer; border:1px solid #ccc; border-radius:4px; background:#f3f4f6;';
    btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        let size = parseInt(input.value, 10);
        if (!Number.isFinite(size)) size = 120;
        size = Math.max(40, Math.min(400, size));

        // FIX: ukuran di-encode LANGSUNG di teks placeholder (bukan atribut
        // data-qr-size) — Jodit membuang semua atribut data-* saat clean-html
        // saat save (lihat catatan di getCleanValue di atas), jadi kalau
        // ukuran disimpan sebagai atribut, nilainya selalu hilang & fallback
        // ke default. Teks konten tidak pernah kena strip, jadi aman.
        editor.s.insertHTML(
            '<span data-qr-placeholder="true" contenteditable="false" ' +
            'style="display:inline-block;padding:4px 10px;margin:0 2px;' +
            'border:1px dashed #94a3b8;border-radius:4px;background:#f1f5f9;' +
            'font-family:monospace;font-size:12px;color:#475569;' +
            'vertical-align:middle;user-select:none;">[QR CODE DOKUMEN ' + size + 'px]</span>'
        );
        if (typeof close === 'function') close();
        editor.e.fire('closeAllPopups');
    });
    wrapper.appendChild(btn);

    return wrapper;
}

function buildMarginPopup(editor, close) {
    const current = editor.currentMargin || DEFAULT_MARGIN;
    const fields = [
        { key: 'top', label: 'Atas' },
        { key: 'right', label: 'Kanan' },
        { key: 'bottom', label: 'Bawah' },
        { key: 'left', label: 'Kiri' },
    ];

    const wrapper = document.createElement('div');
    wrapper.style.cssText = 'padding:12px; display:flex; flex-direction:column; gap:8px; min-width:220px; background:#fff;';

    const title = document.createElement('div');
    title.textContent = 'Margin Halaman (cm)';
    title.style.cssText = 'font-weight:600; margin-bottom:4px; color:#1a1a1a;';
    wrapper.appendChild(title);

    const inputs = {};
    fields.forEach(({ key, label }) => {
        const row = document.createElement('div');
        row.style.cssText = 'display:flex; align-items:center; justify-content:space-between; gap:10px;';

        const lbl = document.createElement('label');
        lbl.textContent = label;
        lbl.style.cssText = 'color:#1a1a1a; font-size:13px;';

        const input = document.createElement('input');
        input.type = 'number';
        input.min = '0';
        input.step = '0.1';
        input.value = (current[key] / PX_PER_CM).toFixed(2);
        input.style.cssText = 'width:70px; padding:4px 6px; border:1px solid #ccc; border-radius:4px;';

        inputs[key] = input;
        row.appendChild(lbl);
        row.appendChild(input);
        wrapper.appendChild(row);
    });

    const errorMsg = document.createElement('div');
    errorMsg.style.cssText = 'color:#b91c1c; font-size:12px; display:none;';
    errorMsg.textContent = 'Margin harus angka ≥ 0.';
    wrapper.appendChild(errorMsg);

    const infoMsg = document.createElement('div');
    infoMsg.style.cssText = 'color:#92400e; font-size:12px; display:none;';
    wrapper.appendChild(infoMsg);

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = 'Terapkan';
    btn.style.cssText = 'margin-top:6px; padding:6px 10px; cursor:pointer; border:1px solid #ccc; border-radius:4px; background:#f3f4f6;';
    btn.addEventListener('click', () => {
        const next = {};
        for (const { key } of fields) {
            const v = parseFloat(inputs[key].value);
            if (!Number.isFinite(v) || v < 0) {
                errorMsg.style.display = 'block';
                infoMsg.style.display = 'none';
                return;
            }
            next[key] = Math.round(v * PX_PER_CM);
        }
        errorMsg.style.display = 'none';

        const size = editor.currentPaperSize || PAPER_SIZES['A4'];
        const clamped = clampMarginToPage(size, next);
        const wasClamped = clamped.top !== next.top || clamped.left !== next.left;

        applyPaperSize(editor, editor.currentPaperSize, clamped);

        if (wasClamped) {
            infoMsg.textContent = `Margin disesuaikan otomatis (maks ~${((size.height - MIN_PAGE_CONTENT_PX) / PX_PER_CM).toFixed(1)}cm atas+bawah, ~${((size.width - MIN_PAGE_CONTENT_PX) / PX_PER_CM).toFixed(1)}cm kiri+kanan) supaya tetap muat di kertas.`;
            infoMsg.style.display = 'block';
            fields.forEach(({ key }) => {
                inputs[key].value = (clamped[key] / PX_PER_CM).toFixed(2);
            });
            return; // biarkan popup tetap terbuka biar user lihat pesannya
        }

        infoMsg.style.display = 'none';
        if (typeof close === 'function') close();
    });
    wrapper.appendChild(btn);

    return wrapper;
}

// Popup tombol "Sisipkan Tanda Tangan (TTD)": cari & pilih user, lalu insert
// placeholder TTD ke posisi kursor.
function buildSignaturePopup(editor, close) {
    const wrapper = document.createElement('div');
    wrapper.style.cssText = 'padding:12px; display:flex; flex-direction:column; gap:8px; min-width:240px; max-width:320px; background:#fff;';

    const title = document.createElement('div');
    title.textContent = 'Sisipkan Tanda Tangan (TTD)';
    title.style.cssText = 'font-weight:600; margin-bottom:4px; color:#1a1a1a; font-size:14px;';
    wrapper.appendChild(title);

    // --- Search input ---
    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.placeholder = 'Cari nama atau divisi...';
    searchInput.style.cssText = 'width:100%; padding:6px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:13px; color:#1f2937; outline:none; box-sizing:border-box; transition:border-color 0.15s;';
    searchInput.addEventListener('focus', () => { searchInput.style.borderColor = '#6366f1'; });
    searchInput.addEventListener('blur', () => { searchInput.style.borderColor = '#d1d5db'; });
    // Hide search until data is loaded
    searchInput.style.display = 'none';
    wrapper.appendChild(searchInput);

    const loading = document.createElement('div');
    loading.textContent = 'Memuat daftar pengguna...';
    loading.style.cssText = 'font-size:12px; color:#6b7280; padding:8px 0;';
    wrapper.appendChild(loading);

    const listContainer = document.createElement('div');
    listContainer.style.cssText = 'display:none; flex-direction:column; gap:4px; max-height:220px; overflow-y:auto; margin-top:4px; border:1px solid #e5e7eb; border-radius:6px; padding:4px;';
    wrapper.appendChild(listContainer);

    // --- Empty state message (hidden by default) ---
    const emptyState = document.createElement('div');
    emptyState.textContent = 'Tidak ada tanda tangan ditemukan';
    emptyState.style.cssText = 'display:none; font-size:12px; color:#9ca3af; text-align:center; padding:12px 0;';
    wrapper.appendChild(emptyState);

    fetch('/signatures/users', {
        headers: { 'Accept': 'application/json' }
    })
        .then(res => res.json())
        .then(data => {
            loading.style.display = 'none';
            listContainer.style.display = 'flex';
            const users = data.users || [];

            users.forEach(u => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.style.cssText = 'display:flex; align-items:center; justify-content:space-between; width:100%; padding:6px 10px; border:none; background:transparent; border-radius:4px; text-align:left; cursor:pointer; font-size:13px; color:#1f2937; transition:background 0.15s;';
                btn.onmouseover = () => btn.style.background = '#f3f4f6';
                btn.onmouseout = () => btn.style.background = 'transparent';

                const left = document.createElement('div');
                left.style.cssText = 'display:flex; flex-direction:column;';
                const name = document.createElement('span');
                name.style.cssText = 'font-weight:500;';
                name.textContent = u.is_me ? `✨ TTD Saya (${u.name})` : u.name;

                const role = document.createElement('span');
                role.style.cssText = 'font-size:11px; color:#6b7280;';
                role.textContent = `${u.role} - ${u.division}`;

                left.appendChild(name);
                left.appendChild(role);

                const badge = document.createElement('span');
                badge.style.cssText = 'font-size:11px; font-family:monospace; background:#e0e7ff; color:#3730a3; padding:2px 6px; border-radius:4px; font-weight:600;';
                badge.textContent = u.placeholder;

                btn.appendChild(left);
                btn.appendChild(badge);

                btn.addEventListener('click', () => {
                    editor.selection.insertHTML(` ${u.placeholder} `);
                    if (typeof close === 'function') close();
                });

                listContainer.appendChild(btn);
            });
        })
        .catch(err => {
            loading.textContent = 'Gagal memuat daftar pengguna.';
            loading.style.color = '#ef4444';
    .then(res => res.json())
    .then(data => {
        loading.style.display = 'none';
        listContainer.style.display = 'flex';
        searchInput.style.display = '';
        const users = data.users || [];

        // Keep references to each button and its searchable text
        const entries = [];

        users.forEach(u => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.style.cssText = 'display:flex; align-items:center; justify-content:space-between; width:100%; padding:6px 10px; border:none; background:transparent; border-radius:4px; text-align:left; cursor:pointer; font-size:13px; color:#1f2937; transition:background 0.15s;';
            btn.onmouseover = () => btn.style.background = '#f3f4f6';
            btn.onmouseout = () => btn.style.background = 'transparent';

            const left = document.createElement('div');
            left.style.cssText = 'display:flex; flex-direction:column;';
            const name = document.createElement('span');
            name.style.cssText = 'font-weight:500;';
            const displayName = u.is_me ? `✨ TTD Saya (${u.name})` : u.name;
            name.textContent = displayName;

            const role = document.createElement('span');
            role.style.cssText = 'font-size:11px; color:#6b7280;';
            const roleText = `${u.role} - ${u.division}`;
            role.textContent = roleText;

            left.appendChild(name);
            left.appendChild(role);

            const badge = document.createElement('span');
            badge.style.cssText = 'font-size:11px; font-family:monospace; background:#e0e7ff; color:#3730a3; padding:2px 6px; border-radius:4px; font-weight:600;';
            badge.textContent = u.placeholder;

            btn.appendChild(left);
            btn.appendChild(badge);

            btn.addEventListener('click', () => {
                editor.selection.insertHTML(` ${u.placeholder} `);
                if (typeof close === 'function') close();
            });

            listContainer.appendChild(btn);

            // Store searchable text (lowercase) alongside the button element
            entries.push({
                el: btn,
                searchText: `${displayName} ${roleText} ${u.placeholder}`.toLowerCase()
            });
        });

        // --- Wire up search/filter ---
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim().toLowerCase();
            let visibleCount = 0;

            entries.forEach(entry => {
                const matches = !query || entry.searchText.includes(query);
                entry.el.style.display = matches ? '' : 'none';
                if (matches) visibleCount++;
            });

            // Show/hide empty state
            emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
            listContainer.style.display = visibleCount === 0 ? 'none' : 'flex';
        });

    return wrapper;
}

// Popup tombol "print": pilih ukuran kertas fisik (A3/A4/A5/dst) lalu cetak.
function buildPrintPopup(editor, close) {
    const wrapper = document.createElement('div');
    wrapper.style.cssText = 'padding:12px; display:flex; flex-direction:column; gap:8px; min-width:200px; background:#fff;';

    const title = document.createElement('div');
    title.textContent = 'Cetak dengan Ukuran';
    title.style.cssText = 'font-weight:600; margin-bottom:4px; color:#1a1a1a;';
    wrapper.appendChild(title);

    const select = document.createElement('select');
    select.style.cssText = 'width:100%; padding:6px 8px; border:1px solid #ccc; border-radius:4px;';
    const currentKey = findPaperKey(editor.currentPaperSize || PAPER_SIZES['A4']);
    for (const key of Object.keys(PAPER_SIZES)) {
        const opt = document.createElement('option');
        opt.value = key;
        opt.textContent = key;
        if (key === currentKey) opt.selected = true;
        select.appendChild(opt);
    }
    wrapper.appendChild(select);

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = 'Cetak';
    btn.style.cssText = 'margin-top:6px; padding:6px 10px; cursor:pointer; border:1px solid #ccc; border-radius:4px; background:#f3f4f6;';
    btn.addEventListener('click', () => {
        const size = PAPER_SIZES[select.value] || PAPER_SIZES['A4'];
        if (typeof close === 'function') close();
        doPrint(editor, size);
    });
    wrapper.appendChild(btn);

    return wrapper;
}

// Tunggu font & gambar selesai load sebelum print() dipanggil — supaya
// browser mengukur tinggi konten pakai metrik font ASLI (bukan fallback)
// saat menghitung native page-break-nya sendiri.
function waitForFontsAndImages(win, callback) {
    let done = false;
    const finish = () => {
        if (done) return;
        done = true;
        callback();
    };

    const imgs = Array.from(win.document.querySelectorAll('img'));
    let imagesReady = imgs.length === 0;
    let remainingImgs = imgs.length;

    const fontsReady = win.document.fonts && win.document.fonts.ready
        ? win.document.fonts.ready
        : Promise.resolve();
    let fontsSettled = false;

    const checkAndFinish = () => {
        if (imagesReady && fontsSettled) finish();
    };

    fontsReady.then(() => { fontsSettled = true; checkAndFinish(); })
        .catch(() => { fontsSettled = true; checkAndFinish(); });

    if (imagesReady) {
        checkAndFinish();
    } else {
        imgs.forEach((img) => {
            if (img.complete && img.naturalWidth > 0) {
                remainingImgs--;
                if (remainingImgs === 0) { imagesReady = true; checkAndFinish(); }
                return;
            }
            img.addEventListener('load', () => {
                remainingImgs--;
                if (remainingImgs === 0) { imagesReady = true; checkAndFinish(); }
            });
            img.addEventListener('error', () => {
                remainingImgs--;
                if (remainingImgs === 0) { imagesReady = true; checkAndFinish(); }
            });
        });
    }

    // Jaring pengaman: font di jaringan lambat/gagal → tetap print setelah 4 detik.
    setTimeout(finish, 4000);
}

// Bangun style tag untuk iframe print: @page menentukan ukuran fisik +
// margin, dan `break-inside: avoid` pada elemen level atas mencegah browser
// memotong satu elemen jadi dua halaman. TIDAK ADA lagi forced page-break
// dari spacer editor — browser menghitung sendiri kapan pindah halaman
// berdasarkan tinggi konten aktual di iframe print ini, jadi selalu akurat
// berapa pun besar margin-nya.
function buildPrintStyle(win, size, margin) {
    const widthIn = (size.width / 96).toFixed(4);
    const heightIn = (size.height / 96).toFixed(4);
    const mTopIn = (margin.top / 96).toFixed(4);
    const mRightIn = (margin.right / 96).toFixed(4);
    const mBottomIn = (margin.bottom / 96).toFixed(4);
    const mLeftIn = (margin.left / 96).toFixed(4);
    const style = win.document.createElement('style');
    style.innerHTML = `
        @page {
            size: ${widthIn}in ${heightIn}in;
            /* Margin WAJIB lewat @page, bukan padding body — @page margin
               otomatis diulang di SETIAP halaman fisik saat browser
               memotong konten yang lebih panjang dari satu halaman. */
            margin: ${mTopIn}in ${mRightIn}in ${mBottomIn}in ${mLeftIn}in;
        }
        @media print {
            html {
                background: #fff !important;
            }
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                box-shadow: none !important;
                /* Body tidak boleh punya padding/width sendiri saat print —
                   area konten sudah otomatis dikurangi margin oleh @page. */
                width: 100% !important;
                min-height: 0 !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            /* FIX UTAMA: jangan paksa titik potong dari spacer editor.
               Biarkan browser menghitung native page-break sendiri
               berdasarkan tinggi konten aktual di iframe ini — satu-satunya
               cara supaya hasil cetak selalu akurat berapa pun margin-nya.
               Yang kita jaga cuma: elemen "daun" (paragraf, heading, tabel,
               dst) tidak boleh terpotong di tengah (mis. satu paragraf pecah
               jadi 2 halaman) — browser otomatis mendorong elemen yang tidak
               muat ke halaman baru secara penuh, bukan menyisakan sepotong
               kalimat nyangkut.

               <ol>/<ul> DIBIARKAN boleh terpotong ANTAR <li> oleh browser
               (persis seperti list di Word/Google Docs), sementara tiap
               <li> individual tetap dikunci "break-inside: avoid" supaya
               isi satu item tidak pernah terpotong di tengah kalimat. */
            body > p,
            body > h1, body > h2, body > h3, body > h4, body > h5, body > h6,
            body > table, body > blockquote, body > figure, body > pre {
                break-inside: avoid;
                page-break-inside: avoid;
            }
            li {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
    `;
    win.document.head.appendChild(style);
}

// Inti logika cetak: bangun iframe, isi konten bersih (tanpa spacer, dengan
// QR placeholder sudah diganti jadi <img> lewat getCleanValue(jodit, true)),
// set @page { size + margin } sesuai argumen, tunggu font & gambar load,
// lalu panggil print — pagination dihitung native oleh browser.
function doPrint(jodit, size) {
    const iframe = jodit.create.element('iframe');
    Object.assign(iframe.style, {
        position: 'fixed',
        right: 0,
        bottom: 0,
        width: 0,
        height: 0,
        border: 0,
    });
    jodit.container.appendChild(iframe);

    const afterFinishPrint = () => {
        jodit.e.off(jodit.ow, 'mousemove', afterFinishPrint);
        iframe.remove();
    };

    const myWindow = iframe.contentWindow;
    if (!myWindow) return;

    jodit.e
        .on(myWindow, 'onbeforeunload onafterprint', afterFinishPrint)
        .on(jodit.ow, 'mousemove', afterFinishPrint);

    jodit.e.fire('generateDocumentStructure.iframe', myWindow.document, jodit);
    // getCleanValue(jodit, true): buang spacer DAN ganti placeholder QR jadi
    // <img> asli. Tidak ada titik potong halaman yang dipaksakan — browser
    // yang menghitung sendiri.
    myWindow.document.body.innerHTML = getCleanValue(jodit, true);

    const margin = jodit.currentMargin || DEFAULT_MARGIN;
    buildPrintStyle(myWindow, size, margin);

    waitForFontsAndImages(myWindow, () => {
        myWindow.focus();
        myWindow.print();
    });
}

export function initJoditEditor(selector, overrides = {}) {
    const ta = document.querySelector(selector);
    if (!ta) return null;

    const uploadUrl = ta.dataset.uploadUrl;
    const csrfToken = ta.dataset.csrfToken;
    const storageKey = ta.dataset.liveStorage;

    // ─── FIX MARGIN HILANG SAAT EDIT ────────────────────────────────────
    // Sebelumnya editor SELALU di-init dengan PAPER_SIZES['A4'] +
    // DEFAULT_MARGIN, tidak peduli apa yang tersimpan di dokumen. Preview
    // (show) sudah benar membaca paper_size/paper_margin dari DB lewat
    // _paper.blade.php, tapi begitu masuk Edit Document, editor "lupa"
    // margin yang sudah pernah di-set — kelihatan seperti balik ke default.
    //
    // Sekarang: prioritas nilai awal ukuran kertas & margin adalah
    //   1. Draft yang tersimpan di localStorage (readStoredPaper) — kalau
    //      user sempat ubah margin di draft yang belum di-save, itu tidak
    //      boleh hilang saat editor reload/reinit.
    //   2. Dataset di textarea (data-paper-size / data-paper-margin), yang
    //      diisi blade dari kolom paper_size/paper_margin dokumen di DB.
    //   3. Fallback A4 + DEFAULT_MARGIN kalau dua-duanya tidak ada
    //      (dokumen baru yang belum pernah diatur kertasnya).
    const storedPaper = storageKey ? readStoredPaper(storageKey) : null;

    let initialSize = PAPER_SIZES['A4'];
    let initialMargin = DEFAULT_MARGIN;

    if (storedPaper) {
        initialSize = storedPaper.size;
        initialMargin = storedPaper.margin;
    } else {
        if (ta.dataset.paperSize && PAPER_SIZES[ta.dataset.paperSize]) {
            initialSize = PAPER_SIZES[ta.dataset.paperSize];
        }
        if (ta.dataset.paperMargin) {
            try {
                const m = JSON.parse(ta.dataset.paperMargin);
                if (m && m.top != null) initialMargin = m;
            } catch (e) { /* dataset kosong/invalid — pakai default */ }
        }
    }
    initialMargin = clampMarginToPage(initialSize, initialMargin);
    // ─────────────────────────────────────────────────────────────────────

    const editor = Jodit.make(ta, {
        height: '40vh',
        width: '100%',
        language: 'id',
        toolbarButtonSize: 'middle',
        toolbarAdaptive: false,   // jangan sembunyikan tombol ke menu "…" — semua tombol selalu tampil
        toolbarSticky: false,

        iframeStyle: buildIframeStyle(initialSize, initialMargin),

        link: {
            followOnDblClick: true,
        },

        iframe: true,                               // isolasi area konten dari CSS Tailwind/daisyUI
        styleValues: { 'color-text': '#1a1a1a' },    // paksa warna teks UI Jodit (termasuk popup Link) jadi gelap
        textIcons: false,                            // WAJIB false → toolbar tampil ICON, bukan teks nama tombol

        buttons: [
            'source', '|',
            'bold', 'italic', 'underline', 'strikethrough', '|',
            'superscript', 'subscript', '|',
            'ul', 'ol', 'indent', 'outdent', '|',
            'font', 'fontsize', 'brush', 'paragraph', 'lineHeight', '|',
            'image', 'video', 'file', 'table', 'link', 'hr', 'qrCode', 'signature', '|',
            // 'image', 'video', 'file', 'table', 'link', 'hr', 'qrCode', '|',
            'align', '|',
            'paperSize', 'margin', '|',
            'undo', 'redo', 'eraser', 'copyformat', '|',
            'symbol', 'speechRecognize', '|',
            'cut', 'copy', 'paste', 'selectall', 'find', '|',
            'preview', 'print', 'fullsize', 'about',
        ],

        controls: {
            // Daftar font custom (Google Fonts) yang muncul di dropdown toolbar "font"
            font: {
                list: Jodit.atom(FONT_LIST),
            },

            paperSize: {
                name: 'paperSize',
                tooltip: 'Ukuran Kertas',
                icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
                list: Jodit.atom(PAPER_SIZES),
                childTemplate: (editor, key) => {
                    const s = PAPER_SIZES[key];
                    return s ? `${key} (${s.width}×${s.height}px)` : key;
                },
                exec: (editor, _, { control }) => {
                    const key = control.args?.[0];
                    const size = PAPER_SIZES[key];
                    if (size) applyPaperSize(editor, size);
                },
            },

            margin: {
                name: 'margin',
                tooltip: 'Margin Halaman',
                icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="1"/><rect x="7" y="7" width="10" height="10" stroke-dasharray="2 2"/></svg>',
                popup: (editor, _current, _self, close) => buildMarginPopup(editor, close),
            },

            // Tombol "Sisip QR Code Dokumen": buka popup pilih ukuran QR,
            // lalu insert placeholder (bukan gambar asli) ke posisi kursor —
            // teks-format ini yang tersimpan ke DB, supaya QR selalu "hidup"
            // mengikuti URL dokumen terkini, bukan snapshot beku.
            // Placeholder diganti jadi <img> QR asli HANYA saat render final:
            // - server-side: QrCodeService::injectPlaceholder() (show/preview/
            //   preview-version/PDF export)
            // - client-side: getCleanValue(jodit, true), dipakai tombol
            //   "print" di toolbar ini sendiri (lihat doPrint()).
            qrCode: {
                name: 'qrCode',
                tooltip: 'Sisip QR Code Dokumen',
                icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3zM14 20h3M20 14v3M20 20h.01"/></svg>',
                popup: (editor, _current, _self, close) => buildQrPopup(editor, close),
            },

            // Tombol "Sisipkan Tanda Tangan (TTD)": buka popup cari/pilih
            // pengguna, lalu insert placeholder TTD ke posisi kursor.
            signature: {
                name: 'signature',
                tooltip: 'Sisipkan Tanda Tangan (TTD)',
                icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 19h6"/><path d="M19 16v6"/><path d="M12 4a4 4 0 0 1 4 4c0 4-5 6-5 6s-5-2-5-6a4 4 0 0 1 4-4z"/><path d="M17.8 13.9 14 21.5 10 18l-2 3.5"/></svg>',
                popup: (editor, _current, _self, close) => buildSignaturePopup(editor, close),
            },

            image: {
                name: 'image',
                tooltip: 'Sisipkan Gambar',
                icon: 'image',
                exec: (jodit) => {
                    if (!uploadUrl) {
                        alert('Upload URL belum diset (data-upload-url kosong).');
                        return;
                    }

                    const input = document.createElement('input');
                    input.type = 'file';
                    input.accept = 'image/jpeg,image/png,image/gif,image/webp';

                    input.onchange = async () => {
                        const file = input.files?.[0];
                        if (!file) return;

                        const formData = new FormData();
                        formData.append('files[]', file);

                        try {
                            const res = await fetch(uploadUrl, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': csrfToken,
                                    'Accept': 'application/json',
                                },
                                body: formData,
                            });

                            const raw = await res.text();
                            let json;
                            try {
                                json = JSON.parse(raw);
                            } catch {
                                console.error('Response bukan JSON valid:', raw);
                                alert('Server tidak mengembalikan JSON.');
                                return;
                            }

                            const files = json?.data?.files;
                            if (Array.isArray(files) && files.length > 0) {
                                files.forEach((url) => jodit.s.insertImage(url, null, 400));
                            } else {
                                console.error('Format response tidak sesuai:', json);
                                alert('Upload gagal: ' + (json?.data?.msg || JSON.stringify(json)));
                            }
                        } catch (err) {
                            console.error('Fetch error:', err);
                            alert('Request upload gagal (network/CORS/419).');
                        }
                    };

                    input.click();
                },
            },

            file: {
                name: 'file',
                tooltip: 'Sisipkan File',
                icon: 'file',
                exec: (jodit) => {
                    if (!uploadUrl) {
                        alert('Upload URL belum diset.');
                        return;
                    }

                    const input = document.createElement('input');
                    input.type = 'file';
                    input.accept = '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar';

                    input.onchange = async () => {
                        const file = input.files?.[0];
                        if (!file) return;

                        const formData = new FormData();
                        formData.append('files[]', file);

                        try {
                            const res = await fetch(uploadUrl, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': csrfToken,
                                    'Accept': 'application/json',
                                },
                                body: formData,
                            });

                            const raw = await res.text();
                            let json;
                            try { json = JSON.parse(raw); } catch {
                                console.error('Response bukan JSON valid:', raw);
                                alert('Server tidak mengembalikan JSON.');
                                return;
                            }

                            const files = json?.data?.files;
                            if (Array.isArray(files) && files.length > 0) {
                                files.forEach((url) => {
                                    const fileName = file.name;
                                    jodit.s.insertHTML(`<a href="${url}" target="_blank" rel="noopener noreferrer">${fileName}</a>`);
                                });
                            } else {
                                alert('Upload gagal: ' + (json?.data?.msg || JSON.stringify(json)));
                            }
                        } catch (err) {
                            console.error('Fetch error:', err);
                            alert('Request upload gagal.');
                        }
                    };
                    input.click();
                },
            },

            // Override tombol "print" bawaan Jodit. Delegasi ke doPrint()
            // (fungsi yang sama dipakai buildPrintPopup) — logika print
            // SUDAH DISATUKAN jadi satu tempat, tidak ada lagi duplikasi
            // kode antara tombol toolbar & popup pilih ukuran. Pagination
            // dihitung native oleh browser (lihat buildPrintStyle), bukan
            // lagi dipaksakan dari spacer editor.
            print: {
                name: 'print',
                tooltip: 'Print',
                exec: (jodit) => {
                    const size = jodit.currentPaperSize || PAPER_SIZES['A4'];
                    doPrint(jodit, size);
                },
            },
        },


        uploader: uploadUrl ? {
            url: uploadUrl,
            format: 'json',
            filesVariableName: () => 'files[]',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            imagesAccept: 'image/jpg,image/png,image/jpeg,image/gif,image/webp',
            isSuccess: (resp) => !resp?.data?.error,
            process: (resp) => ({
                files: resp?.data?.files || [],
                path: resp?.data?.path || '',
                baseurl: resp?.data?.baseurl || '',
                error: resp?.data?.error ?? 0,
                msg: resp?.data?.msg || '',
            }),
            defaultHandlerSuccess(data) {
                (data.files || []).forEach((url) => this.selection.insertImage(url, null, 250));
            },
            error: (e) => console.error('Upload gagal:', e),
        } : undefined,

        ...overrides,
    });

    const form = ta.closest('form');
    let draftSaved = false;
    if (form) form.addEventListener('submit', () => {
        // getCleanValue: WAJIB — tanpa ini elemen jeda pagination ikut
        // tersimpan ke database sebagai bagian dari konten dokumen.
        // forPrint TIDAK dipakai di sini — placeholder QR tetap teks biasa
        // saat disimpan ke DB.
        ta.value = getCleanValue(editor);

        const sizeInput = form.querySelector('[name="paper_size"]');
        const marginInput = form.querySelector('[name="paper_margin"]');
        if (sizeInput) {
            sizeInput.value = findPaperKey(editor.currentPaperSize || PAPER_SIZES['A4']) || 'A4';
        }
        if (marginInput) {
            marginInput.value = JSON.stringify(editor.currentMargin || DEFAULT_MARGIN);
        }

        if (storageKey) {
            localStorage.removeItem(storageKey);
            localStorage.removeItem(storageKey + ':paper');
            draftSaved = true;
        }
    });

    // Live preview sync: mirror content into localStorage for the preview page (other tab)
    if (storageKey) {
        const draft = localStorage.getItem(storageKey);
        if (draft && draft.trim().length) {
            const probe = document.createElement('div');
            probe.innerHTML = draft;
            const hasContent = probe.textContent.trim().length > 0 || probe.querySelector('img, table, iframe');
            if (hasContent) {
                editor.value = draft;
                ta.value = draft;
            }
        }

        let timer = null;
        editor.events.on('change', () => {
            if (draftSaved) return;
            clearTimeout(timer);
            timer = setTimeout(() => localStorage.setItem(storageKey, getCleanValue(editor)), 250);
        });
    }

    if (window.__joditInstances) {
        window.__joditInstances.set(ta.id, editor);
    }

    // FIX: pakai initialSize/initialMargin (hasil baca dataset/localStorage
    // di atas), BUKAN hardcode PAPER_SIZES['A4'] seperti sebelumnya —
    // supaya editor benar-benar mulai dari margin yang sudah tersimpan.
    applyPaperSize(editor, initialSize, initialMargin);
    editor.e.on('afterInit', () => {
        applyPaperSize(editor, initialSize, initialMargin);
        repaginateEditor(editor);

        const iframeDoc = editor.editor?.ownerDocument;
        if (iframeDoc?.fonts?.ready) {
            iframeDoc.fonts.ready
                .then(() => scheduleRepaginate(editor))
                .catch(() => { /* font gagal load — biarkan, jangan block apa pun */ });
        }
    });

    editor.e.on('paste', () => {
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                scheduleRepaginate(editor);

                const iframeDoc = editor.editor?.ownerDocument;
                if (iframeDoc?.fonts?.ready) {
                    iframeDoc.fonts.ready
                        .then(() => scheduleRepaginate(editor))
                        .catch(() => { /* font gagal load — biarkan, jangan block apa pun */ });
                }
            });
        });
    });

    if (typeof ResizeObserver !== 'undefined') {
        const containerResizeObserver = new ResizeObserver(() => scheduleRepaginate(editor));
        containerResizeObserver.observe(editor.container);
        editor.e.on('beforeDestruct', () => containerResizeObserver.disconnect());
    }

    if (typeof MutationObserver !== 'undefined') {
        const spacerObserver = new MutationObserver(() => {
            if (editor._suppressSpacerObserver) return;
            scheduleRepaginate(editor);
        });
        spacerObserver.observe(editor.editor, {
            childList: true,
            subtree: true,
            characterData: true,
        });
        editor.e.on('beforeDestruct', () => spacerObserver.disconnect());
    }

    const handleEmbeddedImageSettle = (e) => {
        if (e.target?.tagName === 'IMG') scheduleRepaginate(editor);
    };
    editor.editor?.addEventListener('load', handleEmbeddedImageSettle, true);
    editor.editor?.addEventListener('error', handleEmbeddedImageSettle, true);
    editor.e.on('beforeDestruct', () => {
        editor.editor?.removeEventListener('load', handleEmbeddedImageSettle, true);
        editor.editor?.removeEventListener('error', handleEmbeddedImageSettle, true);
    });

    editor.events.on('change', () => scheduleRepaginate(editor));

    // Shortcut Ctrl+Z (undo) / Ctrl+Shift+Z / Ctrl+Y (redo).
    editor.events.on('keydown', (e) => {
        if (e.ctrlKey || e.metaKey) {
            const key = e.key.toLowerCase();
            if (key === 'z') {
                e.preventDefault();
                if (e.shiftKey) {
                    editor.execCommand('redo');
                } else {
                    editor.execCommand('undo');
                }
            } else if (key === 'y') {
                e.preventDefault();
                editor.execCommand('redo');
            }
        }
    });

    // Fullsize scroll fix (mode "buka editor ukuran penuh").
    const TOOLBAR_OFFSET = 45; // perkiraan tinggi toolbar Jodit
    let fullsizeClamping = false;
    const clampFullsizeIframe = () => {
        const iframe = editor.container?.querySelector('iframe');
        if (!iframe || !editor.isFullSize) return;
        fullsizeClamping = true;
        iframe.style.height = 'calc(100vh - ' + TOOLBAR_OFFSET + 'px)';
        iframe.style.minHeight = '0';
        const doc = iframe.contentDocument;
        if (doc) {
            doc.documentElement.style.overflowY = 'auto';
            doc.body.style.overflowY = 'auto';
        }
        queueMicrotask(() => { fullsizeClamping = false; });
    };
    const detachFullsizeScroll = () => {
        const iframe = editor.container?.querySelector('iframe');
        if (iframe) {
            iframe.style.height = '';
            iframe.style.minHeight = '';
            const doc = iframe.contentDocument;
            if (doc) {
                doc.documentElement.style.overflowY = '';
                doc.body.style.overflowY = '';
            }
            editor.e.fire('resize');
            editor.e.fire('afterResize');
        }
    };
    const applyFullsizeScroll = (isFull) => {
        if (isFull) {
            clampFullsizeIframe();
            const reapply = () => {
                if (!editor.isFullSize) return;
                clampFullsizeIframe();
                requestAnimationFrame(reapply);
            };
            requestAnimationFrame(reapply);
            editor.e.on('resize.fullsizeFix', clampFullsizeIframe);
            editor.e.on('afterResize.fullsizeFix', clampFullsizeIframe);
        } else {
            editor.e.off('resize.fullsizeFix');
            editor.e.off('afterResize.fullsizeFix');
            detachFullsizeScroll();
        }
    };
    editor.e.on('toggleFullSize', applyFullsizeScroll);
    if (editor.o.fullsize) {
        setTimeout(() => applyFullsizeScroll(true), 100);
    }

    return editor;
}

// Registry instance untuk akses dari modal/script lain (jangan timpa window.Jodit!)
window.__joditInstances = window.__joditInstances || new Map();

window.__initPreviewPagination = initPreviewPagination;
window.__paperSizes = PAPER_SIZES;
window.__findPaperKey = findPaperKey;

export { initPreviewPagination, repaginatePreview, readStoredPaper };