<!-- FITUR: Penutup kerangka tampilan (footer, script global) -- pasangan includes/layout_top.php. Murni presentasi. -->
      </div>
    </main>

    <footer class="admin-footer">
      <div class="container-fluid px-3 px-lg-4">
        <span>SPK KIS Desa Cicadas &copy; <?= date('Y') ?></span>
        <span>Sistem Klasifikasi</span>
      </div>
    </footer>

  </div>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
<script>
  // Popup notifikasi hilang otomatis setelah 3 detik
  (function () {
    document.querySelectorAll('.alert-dismissible').forEach(function (el) {
      setTimeout(function () {
        var alertInstance = bootstrap.Alert.getOrCreateInstance(el);
        alertInstance.close();
      }, 3000);
    });
  })();
</script>
</body>
</html>