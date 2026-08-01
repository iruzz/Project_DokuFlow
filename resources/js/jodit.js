import { Jodit } from 'jodit';
import 'jodit/es2021/jodit.min.css';
import 'jodit/esm/plugins/all.js'; // registrasi semua plugin bawaan (wajib biar icon & fungsi lengkap)

export function initJoditEditor(selector, overrides = {}) {
    const ta = document.querySelector(selector);
    if (!ta) return null;

    const uploadUrl = ta.dataset.uploadUrl;
    const csrfToken = ta.dataset.csrfToken;

    const editor = Jodit.make(ta, {
        height: 600,
        width: '100%',
        language: 'id',
        toolbarButtonSize: 'middle',
        toolbarAdaptive: true,
        toolbarSticky: false,

        // Konten tampil seperti halaman kertas terpisah
        style: {
            maxWidth: '800px',
            margin: '0 auto',
            padding: '48px 56px',
            background: '#fff',
            backgroundImage: 'repeating-linear-gradient(to bottom, transparent 0, transparent 1123px, #d1d5db 1123px, #d1d5db 1125px)',
            border: '2px solid #6b7280',
            boxShadow: '0 1px 3px rgba(0,0,0,0.1)',
            minHeight: '400px',
        },

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
            'image', 'video', 'file', 'table', 'link', 'hr', '|',
            'align', '|',
            'undo', 'redo', 'eraser', 'copyformat', '|',
            'symbol', 'speechRecognize', '|',
            'cut', 'copy', 'paste', 'selectall', 'find', '|',
            'preview', 'print', 'fullsize', 'about',
        ],
        
        // Tombol image: langsung buka file picker & upload, tanpa tab URL
        controls: {
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
                        // === TAMBAHKAN KODE DI BAWAH INI ===
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
    if (form) form.addEventListener('submit', () => { ta.value = editor.value; });

    return editor;
}