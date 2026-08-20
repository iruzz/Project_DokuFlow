# Implementation Plan — DokuFlow (Document Lifecycle Management System)

Dokumen ini adalah rencana implementasi berdasarkan hasil analisis catatan kebutuhan (folder/kategori dokumen, metadata, expiry & retention, permission, signature PERURI, master data karyawan, pengumuman bertarget, dan struktur organisasi cabang).

Tujuan akhir: mengubah DokuFlow dari aplikasi penyimpanan dokumen sederhana menjadi **sistem pengelolaan siklus hidup dokumen perusahaan**.

---

## 0. Prinsip Pengerjaan

- Dikerjakan bertahap per fase — jangan mengerjakan semua modul sekaligus karena saling bergantung.
- Setiap fase harus selesai secara fungsional sebelum lanjut ke fase berikutnya, karena fase-fase berikutnya bergantung pada data/struktur dari fase sebelumnya.
- Fitur yang datanya belum pasti (integrasi PERURI, konfigurasi forgot password dari Pak Austin) dicatat sebagai **dependency**, bukan dikerjakan dengan asumsi.

---

## 1. Fase 1 — Organization & Master Data

**Tujuan:** membangun fondasi data organisasi sebelum fitur dokumen lain dibangun di atasnya.

### Tasks
- [ ] Buat tabel `branches` (Head Office, Branch A, B, C, dst.)
- [ ] Buat/perluas tabel `divisions`
- [ ] Perluas tabel `employees` / `users` dengan kolom master data:
  - `nik`
  - `phone_number`
  - `division_id`
  - `branch_id`
  - `position`
  - `role`
  - `status`
- [ ] Tambahkan role baru: `Director` (selain Admin, Manager, Employee)
- [ ] Buat halaman admin untuk CRUD Employee Master Data (NIK, no. telepon, dsb.) — data ini akan dipakai ulang oleh modul signature, personal document, notification, dan announcement (jangan disimpan terpisah di tiap dokumen).

### Output Fase 1
Struktur organisasi (branch, division, role, employee master data) siap dipakai modul lain.

---

## 2. Fase 2 — Document Structure (Folder, Kategori, Metadata)

**Tujuan:** dokumen tidak lagi berupa daftar file panjang, tapi terstruktur seperti Google Drive.

### Tasks
- [ ] Desain struktur folder fleksibel (bukan hardcode) — Admin bisa membuat kategori/folder sendiri.
- [ ] Kategori dasar sebagai contoh awal: General, Division, Licensing/Perijinan, Announcements, Personal Documents, Shared Documents.
- [ ] Tambahkan metadata standar ke tabel `documents`:
  - `type` (jenis/kategori)
  - `division_id`
  - `created_by`
  - `target_user_id` (People — dituju)
  - `status`
  - `expiry_date`
  - `retention_period`
  - `signature_status`
- [ ] Pisahkan field tanggal sesuai kebutuhan workflow (lihat detail di bagian 4):
  `created_at`, `updated_at`, `document_date`, `effective_date`, `expiry_date`, `signature_date`, `approved_date`, `deleted_at`, `retention_until`.
- [ ] Tampilkan metadata secara konsisten di 3 halaman: **General Documents**, **Division Documents**, **Shared Documents**.
- [ ] Buat filter pencarian dokumen berdasarkan metadata (bukan hanya nama file): type, division, people, status, modified date.
- [ ] Ganti input teks bebas dengan **dropdown terstruktur** untuk field seperti Document Type, Division, Access Level, Status, Retention Policy, Recipient — supaya data tidak berantakan (mis. "HR" vs "Human Resource" vs "H.R.").

### Output Fase 2
Dokumen punya struktur folder + metadata konsisten + pencarian berbasis filter.

---

## 3. Fase 3 — Permission & Access Control

**Tujuan:** tidak semua dokumen boleh dilihat semua orang.

### Tasks
- [ ] Definisikan level akses dokumen:
  - `Public/General` — semua user
  - `Division` — hanya user dalam divisi tertentu
  - `Group` — kelompok tertentu (mis. Management: Director, Manager, Supervisor)
  - `Personal` — hanya user tertentu (mis. surat pemberitahuan karyawan)
- [ ] Tambahkan kombinasi akses **Branch + Division + User/Group** (karena sekarang ada multi-cabang).
- [ ] Buat model `document_access` (polymorphic atau pivot table) untuk menyimpan target akses per dokumen.
- [ ] Update fitur **ShareLink**: saat ini asumsinya "Anyone with link" — ubah jadi pilihan:
  - Anyone with link
  - Specific Division
  - Specific Group
  - Specific Person (pilih dari master data Employee, langsung dapat akses tanpa perlu link publik)
- [ ] Tentukan permission Director secara eksplisit dan tetap bisa dikonfigurasi (jangan otomatis full access hanya karena role Director) — atur akses ke: General/Division/Shared Documents, Approval, Signature, Retention, Confidential Documents, Reports.

### Output Fase 3
Sistem permission granular: Public / Division / Group / Personal + ShareLink yang aman.

---

## 4. Fase 4 — Document Lifecycle (Expiry, Grace Period, Retention)

**Tujuan:** dokumen (khususnya Surat Perijinan) punya masa berlaku dan aturan penghapusan yang jelas.

### Tasks
- [ ] Tambahkan field khusus dokumen perijinan:
  - `permit_number`
  - `issued_date`
  - `effective_date`
  - `expiry_date`
  - `grace_period` (masa tenggang, dalam hari)
- [ ] Buat job/scheduler harian untuk cek status dokumen:
  - 30 hari sebelum expiry → notifikasi biasa
  - 7 hari sebelum expiry → notifikasi urgent
  - Saat expiry → status `Expired`
- [ ] Implementasikan alur retention:
  ```
  Retention Expired → Mark as Retention Expired → Admin Review → Soft Delete → Retention Period → Hard Delete
  ```
- [ ] Buat 2 status berbeda: **Soft Delete** (`deleted_at` terisi, masih bisa restore oleh admin) dan **Hard Delete** (data benar-benar dihapus).
- [ ] Retention policy harus **bisa dikonfigurasi per dokumen** (bukan satu aturan global), dan perlu keputusan bisnis: apakah hard delete otomatis atau hanya menandai dokumen sebagai expired/deleted (tunggu konfirmasi user sebelum implement auto hard-delete).
- [ ] Tambahkan widget dashboard:
  - 🟡 Expiring Soon (X dokumen akan expired dalam 30 hari)
  - 🔴 Expired (Y dokumen telah expired)

### Output Fase 4
Dokumen perijinan/berjangka waktu punya lifecycle otomatis dengan reminder dan retention policy.

---

## 5. Fase 5 — Sharing (Shared Documents, Personal Share)

**Tujuan:** dokumen bisa dibagikan langsung ke individu tertentu dengan aman.

### Tasks
- [ ] Halaman Shared Documents menampilkan dokumen yang dishare ke user login (baik lewat Group/Division/Personal share).
- [ ] Tambahkan badge/notification counter di navbar/sidebar untuk:
  - Shared Documents `[n]`
  - Signature Approval `[n]`
  - Announcements `[n]`
- [ ] Tentukan mekanisme read/unread untuk menurunkan angka badge saat dokumen dibuka/dibaca.

### Output Fase 5
Shared Documents terintegrasi dengan notifikasi & badge counter.

---

## 6. Fase 6 — Workflow (Approval, Assignment, Announcement bertarget)

**Tujuan:** pengumuman/assignment tidak hanya informasi, tapi punya target + deadline + progress tracking.

### Tasks
- [ ] Buat model `Announcement` dengan relasi many-to-many ke target Employee.
- [ ] Tambahkan field `deadline` per announcement.
- [ ] Buat status progress per target: `Pending` / `Completed` (dan status lain sesuai kebutuhan, mis. `In Review`).
- [ ] Dashboard admin: lihat progress semua target (mis. Employee A → Completed, Employee B → Pending).
- [ ] Dashboard user: lihat daftar announcement yang ditujukan ke dirinya beserta deadline & status masing-masing.

### Output Fase 6
Fitur pengumuman punya timeline penyelesaian per target, bukan sekadar broadcast informasi.

---

## 7. Fase 7 — Notification System

**Tujuan:** semua event penting (expiry, approval, share, announcement) terpusat lewat notifikasi real-time.

### Tasks
- [ ] Implementasi notifikasi real-time (mis. Laravel Reverb/Pusher jika stack Laravel — sesuaikan dengan stack aplikasi berjalan).
- [ ] Event yang perlu memicu notifikasi:
  - Dokumen mendekati/mencapai expiry
  - Dokumen baru dishare ke user
  - Permintaan approval/signature
  - Announcement baru dengan deadline
- [ ] Notifikasi terhubung ke data user (nomor telepon/email dari master data jika perlu notifikasi eksternal, mis. WhatsApp/email).

### Output Fase 7
Semua modul (lifecycle, sharing, workflow) terhubung ke satu sistem notifikasi terpusat.

---

## 8. Fase 8 — Digital Signature (Integrasi PERURI)

**Tujuan:** dokumen bisa ditandatangani secara elektronik dan tersertifikasi.

### Tasks
- [ ] **Jangan mulai coding integrasi sebelum data berikut dipastikan ke perusahaan/PIC terkait:**
  - Provider/endpoint resmi PERURI yang digunakan
  - Credential/API key
  - Mekanisme OTP/autentikasi tanda tangan
  - Requirement legal (data apa saja yang wajib dikirim: NIK, no. telepon, dll.)
- [ ] Setelah data di atas tersedia, buat alur:
  ```
  Document → Request Signature → PERURI/Digital Signature API →
  Authentication/Verification → Signature Process →
  Signed Document → Signature Status = Approved
  ```
- [ ] Field `signature_status` di dokumen mengikuti alur ini (Pending → Processing → Approved/Rejected).
- [ ] Gunakan data NIK & no. telepon dari Employee Master Data (Fase 1) — jangan simpan ulang per dokumen.

### Output Fase 8
Dokumen (khususnya surat perijinan/resmi) bisa ditandatangani secara digital dan tersertifikasi.

---

## Dependency Eksternal (Dicatat, Belum Dikerjakan)

| Item | Keterangan |
|---|---|
| Forgot Password / Forgot Email | Konfigurasi akan diberikan oleh Pak Austin — jangan menebak konfigurasi email/akun perusahaan |
| Integrasi PERURI | Perlu kepastian provider, endpoint, credential, dan mekanisme OTP dari perusahaan sebelum implementasi |
| Kebijakan Hard Delete otomatis | Perlu keputusan bisnis apakah retention yang habis langsung hard delete atau hanya ditandai expired |

---

## Ringkasan Urutan Pengerjaan

```
1. Organization & Master Data
        ↓
2. Document Structure (Folder, Kategori, Metadata)
        ↓
3. Permission (User/Group/Division/Personal)
        ↓
4. Document Lifecycle (Expiry, Grace Period, Retention)
        ↓
5. Sharing (Shared Documents, Personal Share, ShareLink)
        ↓
6. Workflow (Approval, Assignment, Deadline, Timeline)
        ↓
7. Notification (Expiry, Approval, Shared, Announcement)
        ↓
8. Digital Signature (PERURI API → Signature → Verification → Signed Document)
```

Urutan ini memastikan fitur baru dibangun di atas fondasi yang sudah ada, bukan tambal-sulam ke controller/view yang sudah jadi.
