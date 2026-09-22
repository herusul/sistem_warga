    </div> <!-- penutup container-fluid dari sidebar -->
</div> <!-- penutup content-wrapper dari sidebar -->

<footer class="app-footer bg-white border-top py-3 text-center">
    <div class="container-fluid">
        <small class="text-muted fst-italic">
            &copy; <?= date('Y') ?> Sistem Informasi Warga Perumahan Wahana Praja I RT 03/14<br>
            <span class="text-secondary" style="font-size: 0.8rem;">Versi 1.2 [Mei 2026]</span>
        </small>
    </div>
</footer>

<style>
    .app-footer {
        margin-left: var(--sidebar-width, 220px);
        transition: all 0.3s ease;
        width: auto;
    }
    
    /* Responsif untuk layar kecil */
    @media (max-width: 768px) {
        .app-footer {
            margin-left: 0 !important;
        }
    }
</style>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php
// Aktifkan pemantauan timeout dan modal peringatan jika user terautentikasi
if (isset($_SESSION['user'])) {
    include __DIR__ . '/session_timeout_modal.php';
}
?>
</body>
</html>
