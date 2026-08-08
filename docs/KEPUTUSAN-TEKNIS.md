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
| 5 | Kebijakan validasi resep (berbasis tanggal vs SOP khusus) | ✅ Terkonfirmasi | **Alur penyerahan mengikuti Java:** Resep dibuat dokter dengan status `tgl_perawatan='0000-00-00'` (belum validasi). Resep hanya bisa dibuat jika stok di `gudangbarang` mencukupi. Petugas farmasi mem-**validasi** (update `tgl_perawatan` & `jam`), barulah resep dianggap sah dan masuk tagihan Billing. Setelah itu, petugas dapat menandai **Serahkan** yang mengisi `tgl_penyerahan=CURDATE()` & `jam_penyerahan=CURTIME()`. Obat yang tidak divalidasi akan diabaikan dari tagihan pasien. |
| 6 | Fungsi tabel `detail_nota_jalan` | ✅ Terkonfirmasi | Tabel `detail_nota_jalan` HANYA digunakan untuk mencatat **Riwayat Pembayaran** / Metode Pembayaran (merujuk ke FK `akun_bayar.nama_bayar` seperti Tunai, Bank BRI, Kasbon). Tabel ini BUKAN untuk mencatat rincian item tagihan (seperti administrasi, obat, tindakan). Rincian tagihan invoice dihitung secara *on the fly* dari tabel-tabel aslinya (`rawat_jl_dr`, `resep_obat`, dll). |
| 7 | Opsi A vs B untuk modul kecantikan (tabel baru vs field generik) | ⏳ Belum dikonfirmasi | — |

> Update tabel di atas begitu Anda punya jawabannya (boleh dari cek source Java, atau dari SOP RSU Al-Arif).

---

## 2. Ringkasan Progress per Fase

| Fase | Status | Tanggal Selesai | Catatan |
|---|---|---|---|
| Fase 0 — Persiapan & Validasi Lingkungan | ✅ Selesai | 2026-06-29 | Koneksi DB terverifikasi sukses di server RSU Al-Arif: pasien (36.030), reg_periksa (73.822), poliklinik (16), dokter (40), user (128). |
| Fase 1 — Login, Layout, Generator no_rawat | ✅ Selesai | 2026-06-29 | Login Admin & User (dokter/perawat) terkonfirmasi berhasil. `generateNoRawat()` & `generateNoRkmMedis()` keduanya final & terkonfirmasi dari data riil. |
| Fase 2 — Registrasi Pasien | ✅ Selesai | 2026-06-30 | `pasien/cari.php`, `pasien/daftar-baru.php` (revisi email & NIP), `pasien/registrasi.php` — semua diverifikasi manual. Belum diuji submit langsung di server. |
| Fase 3 — Asesmen & SOAP | ✅ Selesai | 2026-07-01 | Semua modul asesmen selesai: `soap.php` (bug footer fixed 2026-07-01), `kebidanan-medis.php`, `kebidanan-keperawatan.php`, `obstetri-detail.php`, `ginekologi-detail.php`, `kecantikan.php` (placeholder), `pilih.php` (menu + status tiap sub-modul). |
| Fase 4 — USG, Tindakan, Resep | ✅ Selesai | 2026-07-02 | USG: selesai. **Tindakan**: input tindakan dr/pr/bersama, filter tarif per pj+poli, live petugas fetch. **Resep**: penomoran format Java `YYYYMMDDxxxx` = 12 karakter. Pengecekan sisa stok ke tabel `gudangbarang` sebelum peresepan. Alur resep: Tulis (Dokter) → Validasi `tgl_perawatan` (Farmasi) → Serah Obat (Farmasi): saat serahkan, INSERT ke `detail_pemberian_obat` (agar Java bisa baca biaya obat) + kurangi stok `gudangbarang`. |
| Fase 5 — Billing & Pembukuan | ✅ Selesai | 2026-07-06 | **Diselaraskan dengan Java tanpa konflik.** PHP bertindak sebagai read-only viewer untuk rincian tagihan (Registrasi, Tindakan, Obat) dan menyediakan cetak nota thermal 58mm (`cetak-thermal.php`) kapan saja (sebelum/sesudah bayar). Status pembayaran disinkronkan real-time dari `reg_periksa.status_bayar`. Penutupan billing / input jurnal dilakukan sepenuhnya di Java Khanza. |
| Fase 6 — Modul Kecantikan | ✅ Selesai | 2026-07-06 | Asesmen Awal & Rencana Treatment Wajah (Kecantikan) dengan peta titik treatment wajah interaktif SVG, terintegrasi dengan penguncian data jika billing sudah lunas. |
| Fase 7 — Hardening & Deployment | ⬜ Belum mulai | — | |


Legenda: ⬜ belum mulai · 🟡 sedang berjalan · ✅ selesai · 🔴 terhambat (butuh keputusan)

---

## 3. File Penting yang Sudah Dibuat

```
simrs-kebidanan/
├── config/
│   ├── koneksi.php          → Koneksi PDO ke database `sik`
│   └── app.php              → Konstanta aplikasi (role, status enum)
├── lib/
│   ├── auth.php             → Login Admin & User — TERKONFIRMASI BERHASIL
│   ├── nomor.php            → generateNoRawat() & generateNoRkmMedis() — FINAL
│   ├── layout_header.php    → Sidebar + topbar (sidebar links aktif, billing link dinamis/kontekstual)
│   └── layout_footer.php
├── pasien/
│   ├── cari.php             → Cari pasien lama
│   ├── daftar-baru.php      → Form pasien baru → INSERT ke `pasien`
│   └── registrasi.php       → Form registrasi kunjungan → INSERT ke `reg_periksa`
├── asesmen/
│   ├── index.php            → Daftar kunjungan hari ini + status kelengkapan
│   ├── pilih.php            → Menu asesmen per kunjungan (8 card: SOAP/Medis/Keperawatan/Obstetri/Ginekologi/Tindakan/Resep/Kecantikan)
│   ├── soap.php             → SOAP+TTV → pemeriksaan_ralan (bug footer fixed 2026-07-01)
│   ├── kebidanan-medis.php  → 46 kolom → penilaian_medis_ralan_kandungan
│   ├── kebidanan-keperawatan.php → 118 kolom/5-tab → penilaian_awal_keperawatan_kebidanan
│   ├── obstetri-detail.php  → pemeriksaan_obstetri_ralan
│   ├── ginekologi-detail.php → pemeriksaan_ginekologi_ralan
│   └── kecantikan.php       → Coming Soon placeholder
├── usg/
│   ├── index.php            → Daftar pasien + status USG Kandungan/Ginekologi
│   ├── kandungan.php        → 21 kolom + upload gambar → hasil_pemeriksaan_usg
│   └── ginekologi.php       → 10 kolom + upload gambar → hasil_pemeriksaan_usg_gynecologi
├── tindakan/
│   ├── index.php            → Daftar pasien hari ini untuk pilih kunjungan tindakan
│   └── input.php            → rawat_jl_dr + rawat_jl_pr + rawat_jl_drpr, 3 kategori, filter tarif per poli+cara bayar, dropdown dokter+petugas, hapus baris
├── resep/
│   ├── index.php            → Daftar pasien hari ini untuk pilih kunjungan resep
│   ├── tulis.php            → no_resep format Java (12-char YYYYmmddHHii), kolom peresepan+validasi+penyerahan, tombol Validasi & Serahkan manual
│   └── detail.php           → Detail satu resep + subtotal per item
├── billing/
│   ├── index.php            → Daftar pasien kunjungan hari ini untuk billing & status pembayaran
│   ├── tagihan.php          → Read-only breakdown tagihan (Registrasi, Tindakan, Obat/Resep) & tombol cetak
│   ├── cetak.php            → Cetak Nota pembayaran pasien format A4/PDF (jika sudah ada nota)
│   └── cetak-thermal.php    → Cetak Nota thermal 58mm (bisa dicetak kapan saja, status lunas/tagihan otomatis)
├── dashboard.php            → Landing page, kunjungan hari ini, aksi Asesmen + USG
├── login.php, logout.php
├── assets/css/theme.css     → Tema merah maroon profesional
└── docs/
    ├── PLAN-SIMRS-KHANZA-WEB-KEBIDANAN.md
    └── KEPUTUSAN-TEKNIS.md  → File ini
```

---

## 4. Langkah Selanjutnya (Next Action)

- [ ] **PRIORITAS:** Uji alur lengkap dari registrasi rawat jalan → asesmen SOAP → tindakan/prosedur → tulis resep dokter → validasi farmasi → serah obat → billing kasir (buat nota & print invoice).
- [ ] Setelah alur berjalan, verifikasi dari **aplikasi Java Khanza** apakah data tindakan (`rawat_jl_dr`/`rawat_jl_pr`/`rawat_jl_drpr`), resep (`resep_obat` + `resep_dokter`), USG (`hasil_pemeriksaan_usg`), dan billing/nota (`nota_jalan` + `detail_nota_jalan`) ter-sync dengan benar.
- [ ] Konfirmasi versi PHP & ekstensi GD (untuk upload/resize gambar USG) di server RSU Al-Arif.

---

## 5. Catatan Risiko Teknis (untuk Fase 7 — Hardening)

- **Race condition pada generator nomor.** `generateNoRawat()` dan `generateNoRkmMedis()` memakai pola "SELECT MAX lalu +1" tanpa locking. Jika dua petugas menyimpan registrasi/pasien baru dalam waktu yang hampir bersamaan, ada kemungkinan kecil keduanya membaca MAX yang sama sebelum salah satu selesai INSERT, menghasilkan nomor duplikat. Risiko ini **sama persis** dengan yang ada di aplikasi Java Khanza asli (pola `autoNomer3` di Java juga tidak memakai locking) — jadi PHP ini tidak lebih rawan dari Java yang sudah berjalan. Tetap dicatat sebagai item perbaikan Fase 7: opsi solusi antara lain `SELECT ... FOR UPDATE` dalam transaction, atau retry-on-duplicate-key (karena `no_rawat`/`no_rkm_medis` adalah PRIMARY KEY, insert duplikat akan gagal otomatis dan bisa di-retry).
- **Default data administratif di `pasien/daftar-baru.php`.** Untuk menjaga form ringkas (sesuai kebutuhan klinik kebidanan/kecantikan, bukan birokrasi BPJS penuh), kolom `kd_kel`/`kd_kec`/`kd_kab`/`kd_prop`/`suku_bangsa`/`bahasa_pasien`/`cacat_fisik` diisi kode `1` (berlabel `'-'` di tabel referensi masing-masing — **sudah diverifikasi tidak melanggar FK constraint** dari `sik.sql`), dan `perusahaan_pasien` diisi `'-'`. Data ini aman diedit kembali nanti dari aplikasi Java Khanza jika suatu saat RSU Al-Arif butuh data administratif lebih lengkap (misal untuk klaim BPJS).
- **PHP belum bisa di-syntax-check otomatis** di sandbox pengembangan (tidak ada PHP CLI tersedia karena keterbatasan jaringan sandbox). Semua file sudah direview manual baris-per-baris, termasuk verifikasi jumlah kolom vs placeholder vs parameter pada setiap query INSERT secara terhitung (bukan kira-kira). Tetap disarankan jalankan `php -l namafile.php` di server sebelum dipakai produksi sebagai pengaman tambahan.
