# Keputusan Teknis & Log Progress

> File ini adalah "kartu status" proyek. **Selalu upload file ini bersama folder proyek** saat melanjutkan pekerjaan di sesi/akun Claude yang berbeda, supaya konteks tidak hilang.

---

## Cara Pakai File Ini

1. Setiap kali sebuah keputusan teknis dibuat (lihat daftar pertanyaan terbuka di bawah), catat jawabannya di Bagian 1.
2. Setiap kali sebuah checklist fase di `docs/PLAN-SIMRS-KHANZA-WEB-KEBIDANAN.md` selesai, update juga ringkasan progress di Bagian 2 file ini.
3. Saat mulai sesi baru: upload folder project ini (terutama `docs/`) lalu katakan ke Claude, contoh:
   > "Lanjutkan proyek SIMRS Khanza Web Kebidanan. Baca docs/PLAN-SIMRS-KHANZA-WEB-KEBIDANAN.md dan docs/KEPUTUSAN-TEKNIS.md, kita baru selesai Fase 1."

---

## 1. Keputusan Teknis (Jawaban dari Bagian 8 Dokumen Rencana)

| # | Pertanyaan | Status | Jawaban / Keputusan |
|---|---|---|---|
| 1 | Format & generator `no_rawat` dan `no_rkm_medis` | ✅ Terkonfirmasi penuh | **no_rawat**: format `YYYY/MM/DD/NNNNNN`, reset harian — terverifikasi dari source Java DAN dari data riil RSU Al-Arif (`2026/06/30/000004`, dst, reset ke `000001` tiap ganti tanggal). **no_rkm_medis**: dikonfirmasi langsung oleh RSU Al-Arif (2026-06-29) — mode "Straight" bawaan Khanza (tahun=No, bulan=No), 6 digit urut polos tanpa reset, **maksimal 6 digit**. Data panjang 7 digit (`0435945`) & 5 digit (`05232`) adalah **data kotor hasil upload manual**, dan panjang 1 digit (`1`) **sengaja dibuat** — semua ini diabaikan oleh filter `REGEXP '^[0-9]{6}$'` di `generateNoRkmMedis()` supaya tidak mempengaruhi nilai MAX. Lihat `lib/nomor.php`. |
| 2 | Algoritma hash password tabel `user` | ✅ Terkonfirmasi | **Bukan hash, tapi MySQL `AES_ENCRYPT`/`AES_DECRYPT` dengan key literal `'windi'`** untuk kolom password, dan key `'nur'` untuk kolom `id_user` (id_user pun terenkripsi, bukan plaintext!). Sumber: `src/fungsi/akses.java:268-269`. Query asli Java: `SELECT * FROM user WHERE user.id_user=AES_ENCRYPT(?,'nur') AND user.password=AES_ENCRYPT(?,'windi')`. **Ada juga tabel `admin` terpisah** (kolom `usere`, `passworde`, pola AES sama) — khusus untuk login Admin Utama, terpisah dari `user` (dokter/perawat). Ini PAS dengan kebutuhan "login (admin utama, user (dokter-perawat))". |
| 3 | Kode poli & kode dokter khusus kebidanan/kecantikan di RSU Al-Arif | ✅ Keputusan diambil | **Dropdown GENERAL** — menampilkan SEMUA poliklinik aktif dan SEMUA dokter aktif (beserta nama spesialisasi), dipilih manual oleh petugas saat registrasi. Tidak dihardcode ke kode poli/dokter tertentu, karena bisa ada beberapa dokter kandungan dan banyak dokter kecantikan. Lihat `pasien/registrasi.php`. |
| 4 | Versi PHP & ekstensi server (mysqli/PDO, GD/Imagick) | ⏳ Belum dikonfirmasi | Koneksi PDO sudah terbukti jalan di Fase 0 (test-koneksi.php sukses) |
| 5 | Kebijakan validasi resep (berbasis tanggal vs SOP khusus) | ⏳ Belum dikonfirmasi | — |
| 6 | Opsi A vs B untuk modul kecantikan (tabel baru vs field generik) | ⏳ Belum dikonfirmasi | — |

> Update tabel di atas begitu Anda punya jawabannya (boleh dari cek source Java, atau dari SOP RSU Al-Arif).

---

## 2. Ringkasan Progress per Fase

| Fase | Status | Tanggal Selesai | Catatan |
|---|---|---|---|
| Fase 0 — Persiapan & Validasi Lingkungan | ✅ Selesai | 2026-06-29 | Koneksi DB terverifikasi sukses di server RSU Al-Arif: pasien (36.030), reg_periksa (73.822), poliklinik (16), dokter (40), user (128). |
| Fase 1 — Login, Layout, Generator no_rawat | ✅ Selesai | 2026-06-29 | Login Admin & User (dokter/perawat) **terkonfirmasi berhasil di server RSU Al-Arif** memakai AES_ENCRYPT/DECRYPT. `generateNoRawat()` & `generateNoRkmMedis()` keduanya final & terkonfirmasi dari data riil. Halaman uji `test-generator-nomor.php` dibuat (read-only, tanpa INSERT) untuk verifikasi visual sebelum Fase 2. |
| Fase 2 — Registrasi Pasien | 🟡 Sedang berjalan | — | `pasien/cari.php` (cari pasien lama by nama/RM/KTP), `pasien/daftar-baru.php` (form pasien baru, insert ke `pasien` dengan default aman utk kolom administratif berat), `pasien/registrasi.php` (form registrasi kunjungan, dropdown poli+dokter GENERAL, insert ke `reg_periksa`) — semua sudah dibuat dan diverifikasi jumlah kolom/parameter SQL secara manual. **Belum diuji langsung di server** — perlu dicoba submit form sungguhan oleh pengguna sebelum dianggap selesai. |
| Fase 3 — Asesmen & SOAP | ⬜ Belum mulai | — | |
| Fase 4 — USG, Tindakan, Resep | ⬜ Belum mulai | — | |
| Fase 5 — Billing & Pembukuan | ⬜ Belum mulai | — | |
| Fase 6 — Modul Kecantikan | ⬜ Belum mulai (perlu keputusan #6) | — | |
| Fase 7 — Hardening & Deployment | ⬜ Belum mulai | — | |

Legenda: ⬜ belum mulai · 🟡 sedang berjalan · ✅ selesai · 🔴 terhambat (butuh keputusan)

---

## 3. File Penting yang Sudah Dibuat

```
simrs-kebidanan/
├── config/
│   ├── koneksi.php       → Koneksi PDO ke database `sik` (prepared statement wajib)
│   └── app.php           → Konstanta aplikasi (role, status enum, dll — tanpa secret)
├── lib/
│   ├── auth.php           → Login Admin (tabel admin) & User dokter/perawat (tabel user) —
│   │                         TERKONFIRMASI BERHASIL di server RSU Al-Arif
│   ├── nomor.php           → generateNoRawat() & generateNoRkmMedis() — KEDUANYA FINAL
│   │                         & terkonfirmasi dari data riil RSU Al-Arif
│   ├── layout_header.php  → Partial sidebar + topbar (warna merah maroon)
│   └── layout_footer.php  → Penutup partial layout
├── pasien/
│   ├── cari.php            → Cari pasien lama (nama/No.RM/No.KTP), read-only
│   ├── daftar-baru.php     → Form pasien baru -> INSERT ke `pasien`
│   └── registrasi.php      → Form registrasi kunjungan -> INSERT ke `reg_periksa`,
│                              dropdown poli & dokter GENERAL (semua data aktif)
├── assets/css/theme.css   → Tema warna merah maroon profesional (DI-APPROVE)
├── login.php              → Halaman login (toggle Admin Utama / Dokter-Perawat) — TERUJI
├── dashboard.php           → Landing page, ringkasan kunjungan hari ini (read-only)
├── logout.php              → Hapus session
├── test-koneksi.php        → Halaman uji SELECT ke tabel inti (SUDAH SUKSES)
├── test-generator-nomor.php → Uji generateNoRawat()/generateNoRkmMedis() (read-only, no INSERT)
├── preview-tema.html        → Preview visual komponen UI (SUDAH DI-APPROVE)
└── docs/
    ├── PLAN-SIMRS-KHANZA-WEB-KEBIDANAN.md   → Dokumen rencana utama
    └── KEPUTUSAN-TEKNIS.md                   → File ini
```

---

## 4. Langkah Selanjutnya (Next Action)

- [ ] **PENTING:** Coba alur penuh Fase 2 langsung di server: `pasien/cari.php` → (kalau tidak ketemu) `pasien/daftar-baru.php` → `pasien/registrasi.php`. Ini akan melakukan INSERT sungguhan ke `pasien` dan `reg_periksa` — sebaiknya dicoba dulu dengan data uji/dummy, BUKAN data pasien sungguhan, untuk pertama kali.
- [ ] Setelah pasien uji & registrasi uji berhasil tersimpan, **cek dari aplikasi Java Khanza** apakah data tersebut muncul dan terbaca normal (nama pasien, no rawat, poli, dokter) — ini Definition of Done penting di Fase 2.
- [ ] Jika ada error saat submit form (SQL error, dll), salin pesan error lengkapnya untuk saya perbaiki.
- [ ] Setelah alur registrasi penuh terverifikasi jalan → lanjut ke **Fase 3: Asesmen & SOAP**.

---

## 5. Catatan Risiko Teknis (untuk Fase 7 — Hardening)

- **Race condition pada generator nomor.** `generateNoRawat()` dan `generateNoRkmMedis()` memakai pola "SELECT MAX lalu +1" tanpa locking. Jika dua petugas menyimpan registrasi/pasien baru dalam waktu yang hampir bersamaan, ada kemungkinan kecil keduanya membaca MAX yang sama sebelum salah satu selesai INSERT, menghasilkan nomor duplikat. Risiko ini **sama persis** dengan yang ada di aplikasi Java Khanza asli (pola `autoNomer3` di Java juga tidak memakai locking) — jadi PHP ini tidak lebih rawan dari Java yang sudah berjalan. Tetap dicatat sebagai item perbaikan Fase 7: opsi solusi antara lain `SELECT ... FOR UPDATE` dalam transaction, atau retry-on-duplicate-key (karena `no_rawat`/`no_rkm_medis` adalah PRIMARY KEY, insert duplikat akan gagal otomatis dan bisa di-retry).
- **Default data administratif di `pasien/daftar-baru.php`.** Untuk menjaga form ringkas (sesuai kebutuhan klinik kebidanan/kecantikan, bukan birokrasi BPJS penuh), kolom `kd_kel`/`kd_kec`/`kd_kab`/`kd_prop`/`suku_bangsa`/`bahasa_pasien`/`cacat_fisik` diisi kode `1` (berlabel `'-'` di tabel referensi masing-masing — **sudah diverifikasi tidak melanggar FK constraint** dari `sik.sql`), dan `perusahaan_pasien` diisi `'-'`. Data ini aman diedit kembali nanti dari aplikasi Java Khanza jika suatu saat RSU Al-Arif butuh data administratif lebih lengkap (misal untuk klaim BPJS).
- **PHP belum bisa di-syntax-check otomatis** di sandbox pengembangan (tidak ada PHP CLI tersedia karena keterbatasan jaringan sandbox). Semua file sudah direview manual baris-per-baris, termasuk verifikasi jumlah kolom vs placeholder vs parameter pada setiap query INSERT secara terhitung (bukan kira-kira). Tetap disarankan jalankan `php -l namafile.php` di server sebelum dipakai produksi sebagai pengaman tambahan.
