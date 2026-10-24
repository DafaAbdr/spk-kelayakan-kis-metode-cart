# SPK Kelayakan Penerimaan Kartu Indonesia Sehat (KIS)
### Desa Cicadas, Kampung Pabuaran — Metode CART (Classification and Regression Tree)

Aplikasi PHP native (tanpa framework) + MySQL, menggunakan Bootstrap sebagai
kerangka tampilan. Seluruh proses klasifikasi CART dihitung langsung oleh
sistem (bukan simulasi) berdasarkan data yang tersimpan di database, lalu
disimpan agar pohon keputusan dapat ditampilkan ulang kapan saja.

## Fitur

- **Login / Register** — autentikasi admin dengan password ter-hash (bcrypt).
- **Dashboard** — ringkasan jumlah data, hasil klasifikasi terakhir, akurasi model.
- **Kriteria** — CRUD data warga, dipisah menjadi 2 bagian agar tidak rancu:
  **Data Identitas** (NIK, nama, alamat) dan **Data Kriteria** (penghasilan, status
  pekerjaan, jumlah tanggungan, kondisi tempat tinggal, kepemilikan aset — *Memiliki
  Aset* / *Tidak Memiliki Aset*), ditambah kolom Keterangan Aktual (label hasil
  survei), pencarian, dan upload massal via CSV.
- **Proses Klasifikasi** — melatih pohon keputusan CART dari data berlabel, lalu
  mengklasifikasikan seluruh data (termasuk data baru yang belum berlabel).
  Parameter (kedalaman maksimum & minimum sampel split) dapat diatur.
- **Hasil Klasifikasi** — visualisasi pohon keputusan (diagram HTML/CSS, dibangun
  ulang dari struktur yang tersimpan di database — bukan gambar statis), pie
  chart hasil, dan tabel detail per warga beserta tingkat kecocokan dengan
  keterangan aktual.
- **Laporan** — 3 jenis laporan (Hasil Klasifikasi, Data Masyarakat, Riwayat Proses
  CART), masing-masing dengan filter tanggal/status/pencarian, export ke CSV
  (Excel-compatible), dan cetak/PDF (via dialog cetak browser).
- **Profile & Settings** — kelola profil admin (termasuk upload foto profil),
  ganti password, dan atur parameter default algoritma CART. Jika belum
  mengunggah foto, sistem otomatis membuat avatar inisial (mis. "DA" untuk
  "Dafa Abdurrohman") dengan warna latar konsisten.

## Struktur Basis Data

| Tabel | Fungsi |
|---|---|
| `users` | Akun admin/petugas |
| `masyarakat` | Data kriteria warga (termasuk `keterangan` = label hasil survei aktual, dipakai untuk melatih model) |
| `cart_runs` | Riwayat setiap kali proses klasifikasi dijalankan (akurasi, jumlah data, parameter) |
| `cart_nodes` | Struktur pohon keputusan CART hasil training, per run (disimpan sebagai baris flat: id, parent_id) |
| `hasil_klasifikasi` | Hasil prediksi per warga, per run (hasil, confidence, kecocokan dengan label aktual) |
| `app_settings` | Parameter default CART (kedalaman pohon, minimum sampel split) |

## Cara Instalasi

1. **Siapkan server** — PHP 8.0+ dengan ekstensi `pdo_mysql`, dan MySQL/MariaDB
   (bisa memakai XAMPP/Laragon/Docker).
2. **Buat database** — impor `database.sql` melalui phpMyAdmin, atau via CLI:
   ```bash
   mysql -u root -p < database.sql
   ```
   File ini otomatis membuat database `db_kis_cart`, seluruh tabel, 1 akun
   admin default, dan 90 data contoh masyarakat (untuk demo/latihan model).
3. **Atur koneksi database** — edit `config/database.php` sesuai kredensial
   MySQL Anda (host, nama database, user, password).
4. **Jalankan** — arahkan document root web server (Apache/Nginx/PHP built-in
   server) ke folder proyek ini, lalu buka `login.php` di browser.
   Untuk uji cepat dengan PHP built-in server:
   ```bash
   php -S localhost:8000
   ```
   lalu buka `http://localhost:8000/login.php`.

> **Sudah punya database `db_kis_cart` dari versi sebelumnya (kolom `gaji`,
> kategori aset 3 pilihan)?** Jangan impor ulang `database.sql` (data lama
> akan hilang). Jalankan `migration_penghasilan_aset_phi.sql` sekali saja
> untuk mengubah skema tanpa kehilangan data, lalu jalankan ulang menu
> **Proses Klasifikasi**.
>
> **Sudah pernah menjalankan migrasi di atas (kolom `cart_nodes.phi` sudah
> ada)?** Jalankan juga `migration_gini_split.sql` sekali saja untuk
> mengganti kolom tersebut menjadi `gini_split`, sesuai kriteria Gini Index
> yang sekarang dipakai, lalu jalankan ulang menu **Proses Klasifikasi**.

## Login Default

| Email | Password |
|---|---|
| admin@example.com | admin123 |

## Alur Penggunaan

1. Buka menu **Kriteria** → tambahkan/edit data warga. Isi kolom
   **Keterangan Aktual** jika hasil survei sudah diketahui (dipakai untuk
   melatih model). Kosongkan untuk data baru yang ingin diprediksi.
2. Buka menu **Proses Klasifikasi** → atur parameter (opsional) → klik
   **Proses Klasifikasi**. Sistem akan membangun pohon keputusan CART dari
   data berlabel, lalu mengklasifikasikan seluruh data.
3. Buka menu **Hasil Klasifikasi** untuk melihat visualisasi pohon keputusan,
   ringkasan Layak/Tidak Layak, akurasi model, dan detail per warga.
4. Buka menu **Laporan** untuk memfilter, mencetak (PDF), atau mengekspor
   (Excel/CSV) hasil klasifikasi.

## Catatan Teknis Algoritma CART

Kriteria pemilihan split memakai **Gini Index / Gini Split**:

```
Gini(t)        = 1 − (P1² + P2²)                        (impuritas node t)
Gini_split(s,t) = (nL/n) · Gini(tL) + (nR/n) · Gini(tR)   (untuk calon cabang s)
```

dengan P1/P2 = proporsi kelas Layak/Tidak Layak di node t, nL/nR = jumlah data
di anak kiri/kanan, dan n = nL + nR (total data di node t).

- Fitur numerik (Penghasilan, Jumlah Tanggungan) displit dengan ambang batas
  (threshold) hasil pencarian titik tengah antar nilai unik.
- Fitur kategorikal (Status Pekerjaan, Kondisi Tempat Tinggal, Kepemilikan
  Aset) displit dengan mencoba seluruh kombinasi himpunan bagian kategori
  (subset vs bukan subset), sesuai prinsip split biner CART.
- Pada setiap node, seluruh calon cabang dari seluruh variabel dihitung
  Gini_split(s,t)-nya, lalu calon cabang dengan Gini_split **paling kecil**
  dipilih sebagai split (root pertama kali dipilih dari seluruh data
  training). Semakin kecil Gini_split, semakin homogen (murni) hasil
  pemisahannya.
- Pohon berhenti bertumbuh jika: node sudah murni (Gini(t) = 0), mencapai
  kedalaman maksimum, jumlah sampel di bawah ambang minimum, atau tidak ada
  lagi calon cabang dengan Gini_split(s,t) lebih kecil dari Gini(t) node saat ini.
- Setiap daun (leaf) memprediksi kelas mayoritas dengan tingkat keyakinan
  (confidence) = proporsi kelas mayoritas terhadap total sampel di daun tersebut.
- Akurasi model dihitung dari data yang memiliki label aktual (hasil survei),
  membandingkan prediksi CART dengan label sebenarnya.

Kode algoritma ada di `classes/CartTree.php`, dan visualisasi pohon di
`classes/TreeRenderer.php` + `assets/css/tree.css`.
