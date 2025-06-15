</div> <!-- Close container from header -->

    <footer class="footer mt-auto py-3">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <span class="text-muted">WiFi Payment System &copy; <?php echo date('Y'); ?></span>
                </div>
                <div class="col-md-6 text-md-end">
                    <small class="text-muted">Versi 1.0</small>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Jangan muat Bootstrap lagi karena sudah dimuat di header -->
    <script>
        // Tambahkan fungsi tambahan yang diperlukan di sini, tetapi jangan lagi inisialisasi Bootstrap
        document.addEventListener('DOMContentLoaded', function() {
            // Script untuk mobile drawer (jika masih diperlukan)
            // Tanpa inisialisasi Bootstrap
        });
    </script>
    <?php if (isset($extra_js)): echo $extra_js; endif; ?>
</body>
</html>