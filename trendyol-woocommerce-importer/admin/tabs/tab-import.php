<?php
if ( ! session_id() ) session_start();

// Hata ve başarı mesajlarını göster
if (isset($_SESSION['trendyol_import_error'])) {
	echo '<div class="alert alert-warning" style="margin-bottom:20px;">' . esc_html($_SESSION['trendyol_import_error']) . '</div>';
	unset($_SESSION['trendyol_import_error']);
} elseif (isset($_SESSION['trendyol_import_success'])) {
	$id = intval($_SESSION['trendyol_import_success']['id']);
	echo '<div class="alert alert-success" style="margin-bottom:20px;">';
	echo '✅ ' . esc_html__('Ürün başarıyla eklendi!', 'trendyol-woocommerce-importer') . ' ';
	if ($id) {
		echo '<a class="button button-primary" href="' . esc_url(get_edit_post_link($id)) . '" target="_blank">✏️ Ürünü Düzenle</a>';
	}
	echo '</div>';
	unset($_SESSION['trendyol_import_success']);
}
if (isset($_SESSION['trendyol_preview_error'])) {
	echo '<div class="alert alert-warning" style="margin-bottom:20px;">' . esc_html($_SESSION['trendyol_preview_error']) . '</div>';
	unset($_SESSION['trendyol_preview_error']);
}
?>

<?php
if (isset($_SESSION['trendyol_preview_data'])):
	$product_data = $_SESSION['trendyol_preview_data'];
?>
<div class="trendyol-card" style="margin-bottom:30px;">
	<h3 style="margin-bottom:16px;"><?php echo esc_html__('Ürün Önizlemesi', 'trendyol-woocommerce-importer'); ?></h3>
	<ul style="margin-bottom:16px;list-style:none;padding:0;">
		<li><strong><?php echo esc_html__('Ürün Adı:', 'trendyol-woocommerce-importer'); ?></strong> <?php echo esc_html($product_data['name']); ?></li>
		<li><strong><?php echo esc_html__('Fiyat:', 'trendyol-woocommerce-importer'); ?></strong> <?php echo esc_html($product_data['price']); ?> TL</li>
		<li><strong><?php echo esc_html__('Bedenler:', 'trendyol-woocommerce-importer'); ?></strong>
			<?php
			if (!empty($product_data['sizes'])) {
				foreach ($product_data['sizes'] as $size) {
					echo '<span class="badge bg-primary" style="margin-right:6px;">' . esc_html($size) . '</span>';
				}
			} else {
				echo '<span class="badge bg-warning">' . esc_html__('Yok', 'trendyol-woocommerce-importer') . '</span>';
			}
			?>
		</li>
	</ul>
	<?php if (!empty($product_data['images'])): ?>
		<div class="trendyol-images" style="margin-bottom:16px;">
			<?php foreach ($product_data['images'] as $img): ?>
				<img src="<?php echo esc_url($img); ?>" style="max-width:110px;max-height:110px;border-radius:6px;border:1px solid #e2e8f0;margin-right:6px;" />
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<div style="margin-bottom:14px;">
		<?php if (!empty($product_data['content'])): ?>
			<strong><?php echo esc_html__('Ek Bilgi:', 'trendyol-woocommerce-importer'); ?></strong><br>
			<span style="color:#64748b;font-size:14px;"><?php echo wp_kses_post($product_data['content']); ?></span>
		<?php endif; ?>
	</div>

	<form method="post" action="" style="margin-top:18px;">
		<?php wp_nonce_field( 'trendyol_import_nonce' ); ?>
		<input type="hidden" name="action" value="trendyol_import">
		<input type="hidden" name="product_name" value="<?php echo esc_attr($product_data['name']); ?>">
		<input type="hidden" name="product_price" value="<?php echo esc_attr($product_data['price']); ?>">
		<input type="hidden" name="product_sizes" value="<?php echo esc_attr(json_encode($product_data['sizes'])); ?>">
		<input type="hidden" name="product_images" value="<?php echo esc_attr(json_encode($product_data['images'])); ?>">
		<input type="hidden" name="product_content" value="<?php echo esc_attr($product_data['content']); ?>">
		<input type="hidden" name="product_url" value="<?php echo esc_attr($product_data['url']); ?>">
		<input type="hidden" name="product_category" value="<?php echo esc_attr(isset($product_data['category']) ? $product_data['category'] : ''); ?>">
		<input type="hidden" name="product_brand" value="<?php echo esc_attr(isset($product_data['brand']) ? $product_data['brand'] : ''); ?>">
		<button type="submit" class="button button-primary" style="margin-right:10px;"><?php echo esc_html__('✅ WooCommerce\'e İçe Aktar', 'trendyol-woocommerce-importer'); ?></button>
		<a href="<?php echo esc_url(admin_url('admin.php?page=trendyol-importer&tab=import&cancelpreview=1')); ?>" class="button"><?php echo esc_html__('İptal', 'trendyol-woocommerce-importer'); ?></a>
	</form>
</div>
<?php
unset($_SESSION['trendyol_preview_data']);
endif;
?>

<div class="trendyol-card">
	<div class="trendyol-section">
		<h2><?php echo esc_html__( 'Trendyol\'dan Ürün İçe Aktarma', 'trendyol-woocommerce-importer' ); ?></h2>
		<p><?php echo esc_html__( 'Trendyol\'dan ürünleri doğrudan WooCommerce mağazanıza hızlı ve kolay bir şekilde içe aktarın. Aşağıya bir Trendyol ürün bağlantısı yapıştırın ve geri kalanını biz halledelim.', 'trendyol-woocommerce-importer' ); ?></p>
	</div>

	<form method="post" action="" class="trendyol-form">
		<?php wp_nonce_field( 'trendyol_preview_nonce' ); ?>
		<input type="hidden" name="trendyol_preview" value="1" />

		<div class="form-group">
			<label for="trendyol_url">
				<?php echo esc_html__( 'Trendyol Ürün Bağlantısı', 'trendyol-woocommerce-importer' ); ?>
				<span style="color: #ef4444;">*</span>
			</label>
			<input
				type="url"
				id="trendyol_url"
				name="trendyol_url"
				class="form-control"
				placeholder="https://www.trendyol.com/..."
				required
				pattern="https?://(www\.)?trendyol\.com/.+"
			>
			<small class="form-text">
				<?php echo esc_html__( 'Yalnızca Trendyol.com ürün bağlantıları kabul edilir. Tarayıcı adres çubuğundan tam URL\'yi kopyalayın.', 'trendyol-woocommerce-importer' ); ?>
			</small>
		</div>

		<div class="button-group">
			<button type="submit" class="button button-primary button-large">
				<?php echo esc_html__( '🔍 Ürünü Önizle', 'trendyol-woocommerce-importer' ); ?>
			</button>
		</div>
	</form>

	<div class="divider"></div>

	<div class="trendyol-section">
		<h3><?php echo esc_html__( '💡 İpuçları', 'trendyol-woocommerce-importer' ); ?></h3>
		<ul style="color: #64748b; line-height: 1.8; padding-left: 20px;">
			<li><?php echo esc_html__( 'İçe aktarmadan önce ürün sayfasının düzgün yüklendiğinden emin olun.', 'trendyol-woocommerce-importer' ); ?></li>
			<li><?php echo esc_html__( 'Ürünler taslak olarak oluşturulur - yayınlamadan önce gözden geçirin.', 'trendyol-woocommerce-importer' ); ?></li>
			<li><?php echo esc_html__( 'Ürün resimleri otomatik olarak indirilir ve medya kütüphanenize eklenir.', 'trendyol-woocommerce-importer' ); ?></li>
			<li><?php echo esc_html__( 'Beden çeşitleri değişken ürünler için otomatik olarak oluşturulur.', 'trendyol-woocommerce-importer' ); ?></li>
		</ul>
	</div>
</div>
