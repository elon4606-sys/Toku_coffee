<?php

/* =====================================================
   FITUR SALES - PROMO & IKLAN PRODUK (SISI PELANGGAN)
   -----------------------------------------------------
   Di-include dari pelanggan/index.php, tepat di samping
   tombol keranjang. Berkas ini memakai variabel yang
   sudah ada di index.php:
     - $promoList  (daftar promo)
     - $produkMenu (produk yang dijual)
   Tidak mengubah database maupun berkas lain.
===================================================== */

$salesPromoList = (isset($promoList) && is_array($promoList))
    ? $promoList
    : [];

$salesProdukList = (isset($produkMenu) && is_array($produkMenu))
    ? $produkMenu
    : [];

/* Hanya produk yang masih ada stok, maksimal 6 untuk iklan */

$salesIklan = [];

foreach ($salesProdukList as $salesItem) {

    if ((int)($salesItem['stok'] ?? 0) > 0) {
        $salesIklan[] = $salesItem;
    }

    if (count($salesIklan) >= 6) {
        break;
    }
}

$salesEsc = function ($teks) {
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
};

$salesRupiah = function ($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
};

$salesJumlahPromo = count($salesPromoList);

/* Produk unggulan = iklan utama (banner besar) */

$salesUtama = $salesIklan[0] ?? null;
$salesLainnya = array_slice($salesIklan, 1);

?>

<style>
    /* =====================================================
       TOMBOL SALES (di samping keranjang)
    ===================================================== */

    .sales-link {
        position: relative;
        width: 4.5rem;
        height: 4.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border: .1rem solid #ddd;
        border-radius: 50%;
        background: #fff;
        color: var(--main-color);
        font-size: 1.8rem;
        cursor: pointer;
        padding: 0;
        transition: .2s linear;
    }

    .sales-link:hover,
    .sales-link.active {
        background: #f5f2ea;
        border-color: var(--main-color);
        transform: translateY(-2px);
    }

    .sales-link .sales-count {
        position: absolute;
        top: -.5rem;
        right: -.5rem;
        min-width: 2rem;
        height: 2rem;
        padding: 0 .4rem;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #c68b3c;
        color: #fff;
        border-radius: 50%;
        font-size: 1rem;
        font-weight: 600;
    }

    /* =====================================================
       OVERLAY + PANEL
    ===================================================== */

    .sales-overlay {
        position: fixed;
        inset: 0;
        background: rgba(45, 33, 27, .5);
        opacity: 0;
        visibility: hidden;
        z-index: 9990;
        transition: .3s ease;
    }

    .sales-overlay.active {
        opacity: 1;
        visibility: visible;
    }

    .sales-panel {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        width: 44rem;
        max-width: 100%;
        background: #faf9f5;
        z-index: 9991;
        display: flex;
        flex-direction: column;
        box-shadow: -1rem 0 3rem rgba(0, 0, 0, .15);
        transform: translateX(105%);
        transition: transform .35s ease;
    }

    .sales-panel.active {
        transform: translateX(0);
    }

    .sales-panel,
    .sales-panel * {
        text-transform: none;
    }

    .sales-panel-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        padding: 2rem 2.2rem;
        background: #fff;
        border-bottom: .1rem solid #eee;
    }

    .sales-panel-head h3 {
        font-size: 2rem;
        color: #2d211b;
        display: flex;
        align-items: center;
        gap: .9rem;
    }

    .sales-panel-head h3 i {
        color: #c68b3c;
    }

    .sales-panel-head p {
        font-size: 1.2rem;
        color: #888;
        margin-top: .3rem;
    }

    .sales-close {
        flex-shrink: 0;
        width: 4rem;
        height: 4rem;
        border: .1rem solid #e5e0d8;
        border-radius: 50%;
        background: #fff;
        color: #443;
        font-size: 1.6rem;
        cursor: pointer;
    }

    .sales-close:hover {
        background: #f5f2ea;
    }

    .sales-body {
        flex: 1;
        overflow-y: auto;
        padding: 2rem 2.2rem 3rem;
    }

    .sales-section-title {
        display: flex;
        align-items: center;
        gap: .8rem;
        font-size: 1.4rem;
        font-weight: 600;
        color: #443;
        margin: 0 0 1.2rem;
    }

    .sales-section-title i {
        color: #c68b3c;
    }

    .sales-section-title+.sales-list,
    .sales-section-title+.sales-banner {
        margin-top: 0;
    }

    /* =====================================================
       BANNER IKLAN UTAMA
    ===================================================== */

    .sales-banner {
        position: relative;
        overflow: hidden;
        border-radius: 1.6rem;
        background: #443;
        color: #fff;
        margin-bottom: 2.6rem;
    }

    .sales-banner img {
        width: 100%;
        height: 19rem;
        object-fit: cover;
        display: block;
        opacity: .72;
    }

    .sales-banner-info {
        position: absolute;
        inset: auto 0 0 0;
        padding: 3rem 1.8rem 1.6rem;
        background: linear-gradient(to top, rgba(30, 20, 15, .92), rgba(30, 20, 15, 0));
    }

    .sales-ad-tag {
        position: absolute;
        top: 1.2rem;
        left: 1.2rem;
        background: #c68b3c;
        color: #fff;
        font-size: 1.05rem;
        font-weight: 700;
        letter-spacing: .08rem;
        padding: .4rem 1rem;
        border-radius: 5rem;
        z-index: 2;
    }

    .sales-banner-info h4 {
        font-size: 1.9rem;
        font-weight: 600;
        line-height: 1.3;
    }

    .sales-banner-info p {
        font-size: 1.2rem;
        color: #eadfd2;
        margin-top: .4rem;
        line-height: 1.6;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .sales-banner-bottom {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-top: 1rem;
    }

    .sales-banner-price {
        font-size: 1.8rem;
        font-weight: 700;
    }

    /* =====================================================
       KARTU PROMO
    ===================================================== */

    .sales-list {
        display: flex;
        flex-direction: column;
        gap: 1.1rem;
        margin-bottom: 2.6rem;
    }

    .sales-promo-card {
        display: flex;
        align-items: center;
        gap: 1.3rem;
        padding: 1.5rem;
        background: #fff;
        border: .1rem dashed #d9cbbd;
        border-radius: 1.4rem;
    }

    .sales-promo-card:hover {
        border-color: #c68b3c;
        box-shadow: 0 .8rem 2rem rgba(0, 0, 0, .06);
    }

    .sales-promo-icon {
        flex-shrink: 0;
        width: 4.4rem;
        height: 4.4rem;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #443;
        color: #fff;
        border-radius: 50%;
        font-size: 1.7rem;
    }

    .sales-promo-text {
        flex: 1;
        min-width: 0;
    }

    .sales-promo-code {
        display: inline-block;
        background: #f0e2cf;
        color: #8b6847;
        padding: .2rem .8rem;
        border-radius: .5rem;
        font-size: 1.05rem;
        font-weight: 700;
        letter-spacing: .05rem;
        text-transform: uppercase !important;
    }

    .sales-promo-text h4 {
        font-size: 1.4rem;
        color: #2d211b;
        margin-top: .5rem;
    }

    .sales-promo-text p {
        font-size: 1.15rem;
        color: #666;
        margin-top: .2rem;
    }

    .sales-promo-text small {
        display: block;
        font-size: 1.05rem;
        color: #999;
        margin-top: .3rem;
    }

    .sales-copy-btn {
        flex-shrink: 0;
        border: none;
        background: #443;
        color: #fff;
        font-size: 1.1rem;
        padding: .8rem 1.1rem;
        border-radius: .8rem;
        cursor: pointer;
        white-space: nowrap;
    }

    .sales-copy-btn:hover {
        background: #6f4e37;
    }

    .sales-copy-btn.copied {
        background: #2e7d32;
    }

    /* =====================================================
       KARTU IKLAN PRODUK
    ===================================================== */

    .sales-ad-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.2rem;
    }

    .sales-ad-card {
        position: relative;
        background: #fff;
        border: .1rem solid #e8e3dc;
        border-radius: 1.4rem;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .sales-ad-card:hover {
        border-color: #d9cbbd;
        box-shadow: 0 .8rem 2rem rgba(68, 51, 51, .1);
    }

    .sales-ad-card img {
        width: 100%;
        height: 12rem;
        object-fit: cover;
        display: block;
        background: #f7f4ef;
    }

    .sales-ad-card .sales-ad-tag {
        top: .8rem;
        left: .8rem;
        font-size: .95rem;
        padding: .3rem .8rem;
    }

    .sales-ad-info {
        padding: 1.1rem 1.2rem 1.3rem;
        display: flex;
        flex-direction: column;
        flex: 1;
    }

    .sales-ad-info h4 {
        font-size: 1.3rem;
        color: #2d211b;
        line-height: 1.4;
    }

    .sales-ad-info small {
        font-size: 1.05rem;
        color: #c77700;
        margin-top: .2rem;
    }

    .sales-ad-bottom {
        margin-top: auto;
        padding-top: 1rem;
        display: flex;
        flex-direction: column;
        gap: .7rem;
    }

    .sales-ad-price {
        font-size: 1.4rem;
        font-weight: 700;
        color: #443;
    }

    .sales-add-btn {
        border: none;
        background: #443;
        color: #fff;
        font-size: 1.15rem;
        padding: .8rem 1rem;
        border-radius: .9rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .6rem;
    }

    .sales-add-btn:hover {
        background: #2f2424;
    }

    .sales-add-btn:disabled {
        background: #bbb;
        cursor: not-allowed;
    }

    .sales-banner .sales-add-btn {
        background: #c68b3c;
        padding: .9rem 1.4rem;
        font-size: 1.2rem;
    }

    .sales-banner .sales-add-btn:hover {
        background: #a8742f;
    }

    .sales-empty {
        text-align: center;
        padding: 3rem 1.5rem;
        background: #fff;
        border: .1rem solid #eee;
        border-radius: 1.4rem;
        font-size: 1.3rem;
        color: #888;
        margin-bottom: 2.6rem;
    }

    .sales-foot-link {
        display: block;
        text-align: center;
        margin-top: 2.2rem;
        font-size: 1.25rem;
        color: #8b6847;
    }

    .sales-foot-link:hover {
        color: #443;
    }

    body.sales-lock {
        overflow: hidden;
    }

    @media (max-width: 450px) {

        .sales-panel-head {
            padding: 1.6rem;
        }

        .sales-body {
            padding: 1.6rem 1.6rem 3rem;
        }

        .sales-ad-grid {
            grid-template-columns: 1fr;
        }

        .sales-ad-card img {
            height: 15rem;
        }
    }
</style>


<!-- TOMBOL SALES -->

<button
    type="button"
    class="sales-link"
    id="salesBtn"
    title="Sales: Promo &amp; Iklan Produk"
    aria-label="Buka Sales: Promo dan Iklan Produk"
    aria-controls="salesPanel"
    aria-expanded="false">

    <i class="fas fa-bullhorn"></i>

    <?php if ($salesJumlahPromo > 0): ?>
        <span class="sales-count">
            <?= $salesJumlahPromo ?>
        </span>
    <?php endif; ?>

</button>


<!-- OVERLAY + PANEL SALES -->

<div class="sales-overlay" id="salesOverlay"></div>

<aside
    class="sales-panel"
    id="salesPanel"
    role="dialog"
    aria-label="Sales Toku Coffee"
    aria-hidden="true">

    <div class="sales-panel-head">

        <div>
            <h3>
                <i class="fas fa-bullhorn"></i>
                Sales Toku Coffee
            </h3>
            <p>Promo dan produk pilihan khusus untukmu</p>
        </div>

        <button
            type="button"
            class="sales-close"
            id="salesClose"
            aria-label="Tutup">
            <i class="fas fa-times"></i>
        </button>

    </div>


    <div class="sales-body">

        <!-- IKLAN UTAMA -->

        <?php if ($salesUtama): ?>

            <div class="sales-section-title">
                <i class="fas fa-star"></i>
                Produk Unggulan
            </div>

            <div class="sales-banner">

                <span class="sales-ad-tag">IKLAN</span>

                <img
                    src="<?= $salesEsc($salesUtama['gambar']) ?>"
                    alt="<?= $salesEsc($salesUtama['nama_produk']) ?>"
                    onerror="this.src='../upload/toku-americano.png';">

                <div class="sales-banner-info">

                    <h4><?= $salesEsc($salesUtama['nama_produk']) ?></h4>

                    <p>
                        <?= $salesEsc(
                            ($salesUtama['deskripsi'] ?? '') !== ''
                                ? $salesUtama['deskripsi']
                                : 'Nikmati minuman pilihan Toku Coffee dengan cita rasa yang khas.'
                        ) ?>
                    </p>

                    <div class="sales-banner-bottom">

                        <span class="sales-banner-price">
                            <?= $salesRupiah($salesUtama['harga']) ?>
                        </span>

                        <button
                            type="button"
                            class="sales-add-btn"
                            data-sales-add="<?= $salesEsc($salesUtama['id']) ?>">
                            <i class="fas fa-cart-plus"></i>
                            Pesan Sekarang
                        </button>

                    </div>

                </div>

            </div>

        <?php endif; ?>


        <!-- PROMO -->

        <div class="sales-section-title">
            <i class="fas fa-tags"></i>
            Promo Spesial
        </div>

        <?php if ($salesJumlahPromo > 0): ?>

            <div class="sales-list">

                <?php foreach ($salesPromoList as $salesPromo): ?>

                    <div class="sales-promo-card">

                        <div class="sales-promo-icon">
                            <i class="fas <?= $salesEsc($salesPromo['icon'] ?? 'fa-tags') ?>"></i>
                        </div>

                        <div class="sales-promo-text">

                            <span class="sales-promo-code">
                                <?= $salesEsc($salesPromo['kode'] ?? '') ?>
                            </span>

                            <h4><?= $salesEsc($salesPromo['judul'] ?? '') ?></h4>

                            <p><?= $salesEsc($salesPromo['deskripsi'] ?? '') ?></p>

                            <small><?= $salesEsc($salesPromo['syarat'] ?? '') ?></small>

                        </div>

                        <button
                            type="button"
                            class="sales-copy-btn"
                            data-sales-copy="<?= $salesEsc($salesPromo['kode'] ?? '') ?>">
                            <i class="fas fa-copy"></i>
                            Salin
                        </button>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="sales-empty">
                Belum ada promo yang tersedia saat ini.
            </div>

        <?php endif; ?>


        <!-- IKLAN PRODUK LAINNYA -->

        <?php if (!empty($salesLainnya)): ?>

            <div class="sales-section-title">
                <i class="fas fa-mug-hot"></i>
                Rekomendasi Untukmu
            </div>

            <div class="sales-ad-grid">

                <?php foreach ($salesLainnya as $salesProduk): ?>

                    <?php $salesStok = (int)($salesProduk['stok'] ?? 0); ?>

                    <div class="sales-ad-card">

                        <span class="sales-ad-tag">IKLAN</span>

                        <img
                            src="<?= $salesEsc($salesProduk['gambar']) ?>"
                            alt="<?= $salesEsc($salesProduk['nama_produk']) ?>"
                            loading="lazy"
                            onerror="this.src='../upload/toku-americano.png';">

                        <div class="sales-ad-info">

                            <h4><?= $salesEsc($salesProduk['nama_produk']) ?></h4>

                            <?php if ($salesStok <= 5): ?>
                                <small>Stok tinggal <?= $salesStok ?></small>
                            <?php endif; ?>

                            <div class="sales-ad-bottom">

                                <span class="sales-ad-price">
                                    <?= $salesRupiah($salesProduk['harga']) ?>
                                </span>

                                <button
                                    type="button"
                                    class="sales-add-btn"
                                    data-sales-add="<?= $salesEsc($salesProduk['id']) ?>">
                                    <i class="fas fa-cart-plus"></i>
                                    Tambah
                                </button>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php elseif (!$salesUtama): ?>

            <div class="sales-empty">
                Belum ada produk yang diiklankan saat ini.
            </div>

        <?php endif; ?>


        <a href="#menu" class="sales-foot-link" data-sales-menu>
            Lihat semua menu <i class="fas fa-arrow-right"></i>
        </a>

    </div>

</aside>


<script>
    (function() {

        var btn = document.getElementById('salesBtn');
        var panel = document.getElementById('salesPanel');
        var overlay = document.getElementById('salesOverlay');
        var closeBtn = document.getElementById('salesClose');

        if (!btn || !panel || !overlay) {
            return;
        }

        /* Pindahkan panel ke <body> agar tidak terpengaruh header */

        document.body.appendChild(overlay);
        document.body.appendChild(panel);

        function bukaSales() {
            panel.classList.add('active');
            overlay.classList.add('active');
            btn.classList.add('active');
            btn.setAttribute('aria-expanded', 'true');
            panel.setAttribute('aria-hidden', 'false');
            document.body.classList.add('sales-lock');
        }

        function tutupSales() {
            panel.classList.remove('active');
            overlay.classList.remove('active');
            btn.classList.remove('active');
            btn.setAttribute('aria-expanded', 'false');
            panel.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('sales-lock');
        }

        btn.addEventListener('click', function() {
            if (panel.classList.contains('active')) {
                tutupSales();
            } else {
                bukaSales();
            }
        });

        overlay.addEventListener('click', tutupSales);

        if (closeBtn) {
            closeBtn.addEventListener('click', tutupSales);
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                tutupSales();
            }
        });

        /* Tautan "Lihat semua menu": tutup panel lalu gulir ke menu */

        var menuLink = panel.querySelector('[data-sales-menu]');

        if (menuLink) {
            menuLink.addEventListener('click', tutupSales);
        }

        /* Pesan kecil (memakai toast bawaan halaman bila ada) */

        function pesanSales(teks) {

            if (typeof showToast === 'function') {
                showToast(teks);
                return;
            }

            alert(teks);
        }

        /* Salin kode promo */

        panel.querySelectorAll('[data-sales-copy]').forEach(function(tombol) {

            tombol.addEventListener('click', function() {

                var kode = tombol.getAttribute('data-sales-copy');

                function sukses() {
                    tombol.classList.add('copied');
                    tombol.innerHTML = '<i class="fas fa-check"></i> Tersalin';
                    pesanSales('Kode ' + kode + ' disalin. Gunakan di keranjang.');

                    setTimeout(function() {
                        tombol.classList.remove('copied');
                        tombol.innerHTML = '<i class="fas fa-copy"></i> Salin';
                    }, 2000);
                }

                function cadangan() {
                    var area = document.createElement('textarea');
                    area.value = kode;
                    area.style.position = 'fixed';
                    area.style.opacity = '0';
                    document.body.appendChild(area);
                    area.select();

                    try {
                        document.execCommand('copy');
                    } catch (err) {}

                    document.body.removeChild(area);
                    sukses();
                }

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(kode).then(sukses, cadangan);
                } else {
                    cadangan();
                }
            });
        });

        /* Tambah ke keranjang dari iklan (memakai endpoint yang sama dengan halaman) */

        panel.querySelectorAll('[data-sales-add]').forEach(function(tombol) {

            tombol.addEventListener('click', function() {

                if (tombol.disabled) {
                    return;
                }

                var data = new FormData();
                data.append('action', 'tambah_keranjang');
                data.append('id_produk', tombol.getAttribute('data-sales-add'));
                data.append('qty', '1');

                tombol.disabled = true;

                fetch(window.location.href, {
                        method: 'POST',
                        body: data,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(function(res) {
                        return res.json();
                    })
                    .then(function(hasil) {

                        if (hasil.success) {

                            var hitung = document.getElementById('cartCount');
                            var hitungFooter = document.getElementById('footerCartCount');

                            if (hitung) {
                                hitung.textContent = hasil.cart_count;
                                hitung.style.display = hasil.cart_count > 0 ? 'flex' : 'none';
                            }

                            if (hitungFooter) {
                                hitungFooter.textContent = hasil.cart_count;
                            }
                        }

                        pesanSales(hasil.message || 'Selesai.');
                    })
                    .catch(function() {
                        pesanSales('Terjadi kesalahan saat menambahkan produk.');
                    })
                    .then(function() {
                        tombol.disabled = false;
                    });
            });
        });

    })();
</script>
