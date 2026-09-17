# Toku_coffee
Tentu. Kalau Toku Coffee dibangun sebagai website e-commerce berbasis ERP, pembagian sistem Frontend dan Backend bisa dibuat dari tahap awal database → API → integrasi → tampilan website seperti ini.

1. Frontend — Sistem Tampilan & Interaksi Pengguna

Tahap	Sistem/Tugas	Hasil

1	Perancangan UI/UX	Wireframe dan desain halaman
2	Struktur Website	HTML halaman Customer & Admin
3	Styling	CSS, warna, layout, responsive
4	Interaksi	JavaScript, form, tombol, modal, validasi
5	Halaman Customer	Home, katalog, detail produk, keranjang
6	E-Commerce	Checkout, pembayaran, riwayat pesanan
7	Halaman Admin	Dashboard, produk, stok, pesanan
8	Integrasi API	Mengambil/mengirim data ke Backend
9	Responsive	Tampilan mobile, tablet, desktop
10	Testing Tampilan	Cek error UI dan user experience


Teknologi:
HTML + CSS + JavaScript + Bootstrap/Tailwind + Fetch/AJAX

Fokus Frontend:

> Apa yang dilihat dan digunakan oleh Customer dan Admin.




2. Backend — Sistem Data, API & Logika Bisnis

Tahap	Sistem/Tugas	Hasil

1	Perancangan Database	ERD dan struktur tabel
2	Pembuatan Database	MySQL/MariaDB
3	Tabel User	Login, register, role
4	Tabel Produk & Kategori	Data katalog
5	Tabel Keranjang & Pesanan	Sistem transaksi
6	Tabel Pembayaran	Data pembayaran
7	Tabel Stok	Pengelolaan persediaan
8	REST API	API untuk Frontend
9	Authentication	Login, logout, session/token
10	Authorization	Role Customer, Staff, Admin
11	Business Logic	Order, stok, pembayaran, dll.
12	Security & Validation	Validasi input dan keamanan
13	Testing API	Pengujian menggunakan Postman
14	Integrasi Database	API ↔ Database


Teknologi:
PHP + MySQL/MariaDB + REST API + JSON

Fokus Backend:

> Mengatur data, database, keamanan, API, dan proses bisnis website.




Alur Pembangunan Toku Coffee

DATABASE
   ↓
MySQL / MariaDB
   ↓
BACKEND
PHP + REST API
   ↓
JSON
   ↓
FRONTEND
HTML + CSS + JavaScript
   ↓
Tampilan Website
   ↓
CUSTOMER / ADMIN

Contoh saat Customer membeli kopi:

Customer
   ↓
Frontend
   ↓
Klik "Beli"
   ↓
REST API
   ↓
Backend
   ↓
Cek Produk & Stok
   ↓
Database
   ↓
Simpan Pesanan
   ↓
Backend
   ↓
JSON Response
   ↓
Frontend
   ↓
"Pesanan Berhasil"

Pembagian sederhana untuk presentasi

Frontend:

> Membuat seluruh tampilan website, UI/UX, halaman Customer dan Admin, responsive design, JavaScript, serta menghubungkan website dengan API Backend.



Backend:

> Membuat database, REST API, sistem login dan role, pengelolaan produk, stok, keranjang, pesanan, pembayaran, validasi, keamanan, serta logika bisnis.



Jadi, Backend mengerjakan dari database sampai API, sedangkan Frontend mengerjakan dari API sampai menjadi tampilan yang digunakan pengguna.
