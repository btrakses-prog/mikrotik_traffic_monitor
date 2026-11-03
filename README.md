# MikroTik Traffic Monitor Dynamic

PHP + RouterOS API dashboard responsif.

## Fitur
- Monitoring realtime setiap 2 detik.
- RX/TX dan packets per second.
- Total Source dan Output.
- Menampilkan identity, uptime, CPU, memory.
- Tombol **Interface** untuk mengambil daftar interface langsung dari MikroTik.
- Centang/tambah interface lain yang ingin dimonitor tanpa mengedit kode.
- Atur label dashboard dan role Source/Output/Other.
- Konfigurasi pilihan disimpan ke `config.php`.

## Instalasi
1. Upload semua file ke hosting/server PHP yang bisa mengakses MikroTik TCP 8728.
2. Edit `config.php` untuk host, username, password, port jika diperlukan.
3. Pastikan PHP dapat menulis `config.php` jika ingin menggunakan tombol Simpan di dashboard.
4. Buka `index.php`.

## Catatan
- RouterOS API harus aktif dan dapat diakses dari server PHP.
- Jika `config.php` tidak writable, interface masih bisa dipilih dengan mengedit bagian `interfaces` secara manual.
- Chart.js dimuat dari CDN, jadi halaman memerlukan akses internet untuk grafik.
