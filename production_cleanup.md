# Daftar File & Folder yang Boleh Dihapus Sebelum Production

> [!IMPORTANT]
> Sebelum menghapus apapun, pastikan sudah backup project dulu. Daftar ini sudah diverifikasi dari struktur direktori SIMKlinik.

---

## 🗑️ HAPUS — Folder Seluruhnya

| Folder | Alasan |
|--------|--------|
| `scratch/` | Berisi 22 file debug/test/inspect sekali-pakai — **tidak dibutuhkan di production** |
| `java/` | File source Java SIMRS Khanza (referensi saja), tidak dieksekusi oleh web server |
| `.git/` | Folder version control — tidak perlu ada di server production (**wajib hapus** untuk keamanan) |

---

## 🗑️ HAPUS — File Satu per Satu di Root

| File | Alasan |
|------|--------|
| `find_matches.php` | Script debug pencarian |
| `inspeksi-nomor.php` | Script inspeksi nomor RM — **berbahaya di production** (expose data) |
| `test-generator-nomor.php` | Script uji generator nomor — bisa disalahgunakan |
| `test-koneksi.php` | Script uji koneksi DB — **wajib hapus**, expose kredensial DB |
| `query_matches.txt` | File teks debug query |
| `rawat_jl_schemas.txt` | File teks referensi schema |
| `sik_reg.txt` | Data registrasi referensi |
| `sik_text_columns.txt` | Referensi kolom database |
| `sik_triggers.txt` | Referensi triggers |
| `sik_views.txt` | Referensi views |
| `sikori_reg.txt` | Data registrasi referensi (versi ori) |
| `sikori_setting.txt` | Setting referensi (versi ori) |
| `sikori_triggers.txt` | Referensi triggers (versi ori) |
| `sikori_views.txt` | Referensi views (versi ori) |
| `preview-tema.html` | Preview desain UI — tidak fungsional |
| `simrs-kebidanan-fase0.zip` | Arsip lama fase development |

---

## ⚠️ PERTIMBANGKAN — File Ini Sensitif tapi Mungkin Masih Dipakai

| File | Catatan |
|------|---------|
| `sik_schema.sql` | Schema dump database. **Hapus di production** — expose seluruh struktur DB. Simpan di luar htdocs jika dibutuhkan backup. |
| `sikori_schema.sql` | Sama seperti di atas |
| `sik_setting.txt` | Berisi setting sistem — simpan di luar htdocs |
| `update-status.php` | Cek dulu apakah masih diperlukan alurnya |

---

## ⚠️ PERTIMBANGKAN — File Java di Root (Duplikat dari `/java/`)

| File | Catatan |
|------|---------|
| `DlgPeresepanDokter.java` | Duplikat dari `java/` — hapus kalau `java/` juga dihapus |
| `RMHasilPemeriksaanUSG.java` | Duplikat dari `java/` |
| `RMHasilPemeriksaanUSGGynecologi.java` | Duplikat dari `java/` |
| `RMRiwayatPerawatan.java` | File source Java besar (3.4 MB) — tidak dipakai web |

---

## ✅ JANGAN DIHAPUS — Tetap Dibutuhkan

| File/Folder | Alasan |
|-------------|--------|
| `docs/` | Dokumentasi teknis internal — aman dipertahankan |
| `.gitignore` | Tidak berbahaya |
| `README.md` | Dokumentasi |
| `config/` | **Wajib ada** — koneksi DB, setting app |
| `lib/` | **Wajib ada** — library auth, layout, nomor generator |
| `assets/` | CSS, JS, gambar |
| `asesmen/`, `billing/`, `kecantikan/`, `laporan/`, `master/`, `pasien/`, `resep/`, `tindakan/`, `usg/` | Modul fungsional |
| `hasilpemeriksaanusg/` | Folder upload foto USG |
| `dashboard.php`, `index.php`, `login.php`, `logout.php` | Halaman utama |

---

## 🚀 Perintah Cepat Hapus Semua Sekaligus (PowerShell)

```powershell
# Jalankan dari: c:\xampp\htdocs\SIMKlinik

# 1. Folder
Remove-Item -Recurse -Force scratch, java, .git

# 2. File debug & sensitif di root
Remove-Item -Force `
  find_matches.php, inspeksi-nomor.php, test-generator-nomor.php, test-koneksi.php, `
  query_matches.txt, rawat_jl_schemas.txt, `
  sik_reg.txt, sik_text_columns.txt, sik_triggers.txt, sik_views.txt, `
  sikori_reg.txt, sikori_setting.txt, sikori_triggers.txt, sikori_views.txt, `
  preview-tema.html, simrs-kebidanan-fase0.zip

# 3. Schema SQL (simpan backup dulu di luar htdocs!)
Remove-Item -Force sik_schema.sql, sikori_schema.sql, sik_setting.txt

# 4. File Java duplikat di root
Remove-Item -Force DlgPeresepanDokter.java, RMHasilPemeriksaanUSG.java, RMHasilPemeriksaanUSGGynecologi.java, RMRiwayatPerawatan.java
```

> [!WARNING]
> **Backup `sik_schema.sql` dan `sikori_schema.sql`** ke luar folder `htdocs` sebelum dihapus — file ini adalah satu-satunya referensi struktur database lengkap.

> [!CAUTION]
> Menghapus `.git` berarti Anda **tidak bisa `git pull`** untuk update berikutnya. Pertimbangkan hanya menyembunyikan aksesnya via `.htaccess` jika masih ingin versioning.
