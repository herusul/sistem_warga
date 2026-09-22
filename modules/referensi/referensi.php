<?php
require_once('../../includes/db.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');
require_once('../../includes/encryption.php');

// Referensi hanya untuk admin/operator
check_auth(['admin', 'superadmin', 'operator']);

$kategori = isset($_GET['kategori']) ? $_GET['kategori'] : 'agama';

// Ambil data referensi dengan prepared statement
$sql_data = "SELECT * FROM referensi WHERE ref_kategori = ? ORDER BY ref_nama ASC";
$stmt_data = mysqli_prepare($conn, $sql_data);
mysqli_stmt_bind_param($stmt_data, "s", $kategori);
mysqli_stmt_execute($stmt_data);
$data = mysqli_stmt_get_result($stmt_data);

// Ambil semua kategori unik untuk tombol navigasi
$kategori_list = [];
$kategori_result = mysqli_query($conn, "SELECT DISTINCT ref_kategori FROM referensi ORDER BY ref_kategori ASC");
while ($r = mysqli_fetch_assoc($kategori_result)) {
    $kategori_list[] = $r['ref_kategori'];
}

// Ambil jumlah item per kategori
$jumlah_per_kategori = [];
$jumlah_result = mysqli_query($conn, "SELECT ref_kategori, COUNT(*) as jumlah FROM referensi GROUP BY ref_kategori");
while ($j = mysqli_fetch_assoc($jumlah_result)) {
    $jumlah_per_kategori[$j['ref_kategori']] = $j['jumlah'];
}

?>
<?php include '../../views/header.php'; ?>
<?php include '../../views/sidebar.php'; ?>

<div class="container mt-4">
    <h4>📚 Data Referensi - <?= e(ucfirst(str_replace('_', ' ', $kategori))) ?></h4>

    <?php if (isset($_SESSION['success'])) : ?>
        <div class="alert alert-success"><?= e($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])) : ?>
        <div class="alert alert-danger"><?= e($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- Form tambah kategori baru -->
    <form method="post" action="referensi_kategori_tambah.php" class="row g-2 mb-3">
        <?= csrf_input() ?>
        <div class="col-md-4">
            <input type="text" name="kategori_baru" class="form-control" placeholder="➕ Tambah Kategori Baru..." required>
        </div>
        <div class="col-md-2">
            <button class="btn btn-outline-success">Tambah Kategori</button>
        </div>
    </form>

    <!-- Tombol hapus kategori aktif -->
    <?php if ($kategori) : ?>
        <form method="post" action="referensi_kategori_hapus.php" onsubmit="return confirm('Yakin ingin menghapus seluruh kategori \'<?= e($kategori) ?>\'?')" class="mb-2">
            <?= csrf_input() ?>
            <input type="hidden" name="kategori" value="<?= e($kategori) ?>">
            <button class="btn btn-danger btn-sm">🗑 Hapus Kategori Ini</button>
        </form>
    <?php endif; ?>

    <!-- Tombol kategori -->
	<div class="mb-3">
		<?php foreach ($kategori_list as $kat) : 
			$jumlah = $jumlah_per_kategori[$kat] ?? 0;
		?>
			<a href="?kategori=<?= urlencode($kat) ?>" class="btn btn-sm btn-outline-<?= $kategori == $kat ? 'primary' : 'secondary' ?> me-1 mb-1">
				<?= e(ucfirst(str_replace('_', ' ', $kat))) ?>
				<span class="badge bg-<?= $kategori == $kat ? 'light' : 'secondary' ?>"><?= e($jumlah) ?></span>
			</a>
		<?php endforeach; ?>
	</div>

    <!-- Info jumlah total -->
    <div class="alert alert-info">
        Total item dalam kategori <strong><?= e(ucfirst(str_replace('_', ' ', $kategori))) ?></strong>: <?= e($jumlah_per_kategori[$kategori] ?? 0) ?>
    </div>

    <!-- Form tambah referensi -->
	<form method="post" action="referensi_tambah.php" class="row g-2 mb-3">
		<?= csrf_input() ?>
		<input type="hidden" name="kategori" value="<?= e($kategori) ?>">
		
		<div class="col-md-5">
			<input type="text" name="isi" class="form-control" placeholder="Tambah Referensi Baru..." required>
		</div>

		<div class="col-md-2">
			<select name="is_aktif" class="form-select">
				<option value="1" selected>Aktif</option>
				<option value="0">Tidak Aktif</option>
			</select>
		</div>

		<div class="col-md-2">
			<button class="btn btn-success">➕ Tambah</button>
		</div>
	</form>

    <!-- Tabel data referensi -->
    <table class="table table-bordered table-striped">
        <thead>
            <tr class="text-center">
                <th>No</th>
                <th>Isi</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php $no=1; while($row=mysqli_fetch_assoc($data)) : ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td><?= e($row['ref_nama']) ?></td>
                    <td class="text-center">
                        <?= $row['is_aktif'] == 1 ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Tidak Aktif</span>' ?>
                    </td>
                    <td class="text-center">
                        <a href="referensi_edit.php?id=<?= encrypt_id($row['ref_id']) ?>&kategori=<?= urlencode($kategori) ?>" class="btn btn-sm btn-primary">✏️ Edit</a>
                        <form action="referensi_hapus.php" method="post" style="display:inline-block;" onsubmit="return confirm('Hapus data ini?')">
                            <?= csrf_input() ?>
                            <input type="hidden" name="id" value="<?= encrypt_id($row['ref_id']) ?>">
                            <input type="hidden" name="kategori" value="<?= e($kategori) ?>">
                            <button class="btn btn-sm btn-danger">🗑️ Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include '../../views/footer.php'; ?>
