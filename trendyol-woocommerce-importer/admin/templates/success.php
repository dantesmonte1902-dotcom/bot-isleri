<?php
/**
 * Success Template - Modern Design
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html__( 'Import Successful', 'trendyol-woocommerce-importer' ); ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="<?php echo esc_url( TRENDYOL_IMPORTER_URL . 'admin/assets/admin-style.css' ); ?>">
	<?php wp_admin_css(); ?>
	<style>
		body {
			background-color: var(--light-bg);
		}
		.success-header {
			background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
			color: white;
			padding: 60px 20px;
			text-align: center;
			margin-bottom: 40px;
			position: relative;
			overflow: hidden;
		}
		.success-header::before {
			content: '';
			position: absolute;
			top: -50%;
			right: -10%;
			width: 400px;
			height: 400px;
			background: rgba(255, 255, 255, 0.1);
			border-radius: 50%;
		}
		.success-header::after {
			content: '';
			position: absolute;
			bottom: -30%;
			left: -5%;
			width: 300px;
			height: 300px;
			background: rgba(255, 255, 255, 0.05);
			border-radius: 50%;
		}
		.success-header h1 {
			font-size: 48px;
			font-weight: 700;
			margin: 0 0 10px 0;
			position: relative;
			z-index: 1;
		}
		.success-header p {
			font-size: 16px;
			opacity: 0.95;
			margin: 0;
			position: relative;
			z-index: 1;
		}
		.success-container {
			max-width: 800px;
			margin: 0 auto;
			padding: 0 20px 40px;
		}
		.success-content {
			text-align: center;
		}
		.checkmark {
			width: 80px;
			height: 80px;
			background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
			border-radius: 50%;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			margin: 0 auto 25px;
			box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
			font-size: 40px;
		}
		.success-message h2 {
			font-size: 28px;
			font-weight: 700;
			margin-bottom: 15px;
			color: var(--text-primary);
		}
		.success-message p {
			color: var(--text-secondary);
			font-size: 16px;
			line-height: 1.6;
			margin-bottom: 30px;
		}
		.product-summary {
			background: white;
			border-radius: 8px;
			padding: 25px;
			margin-bottom: 30px;
			box-shadow: var(--shadow-sm);
			border-left: 4px solid var(--success-color);
		}
		.summary-item {
			display: grid;
			grid-template-columns: 150px 1fr;
			gap: 15px;
			padding: 12px 0;
			border-bottom: 1px solid var(--border-color);
		}
		.summary-item:last-child {
			border-bottom: none;
		}
		.summary-item strong {
			color: var(--text-primary);
		}
		.summary-item span {
			color: var(--text-secondary);
		}
	</style>
</head>
<body>
	<div class="success-header">
		<h1>🎉</h1>
		<h1><?php echo esc_html__( 'Import Successful!', 'trendyol-woocommerce-importer' ); ?></h1>
		<p><?php echo esc_html__( 'Your product has been successfully imported to WooCommerce', 'trendyol-woocommerce-importer' ); ?></p>
	</div>

	<div class="success-container">
		<div class="success-content">
			<div class="checkmark">✓</div>

			<div class="success-message">
				<h2><?php echo esc_html__( 'Great! Everything is ready', 'trendyol-woocommerce-importer' ); ?></h2>
				<p><?php echo esc_html__( 'The product has been imported as a draft. You can now review, edit, and publish it whenever you\'re ready.', 'trendyol-woocommerce-importer' ); ?></p>
			</div>

			<div class="product-summary">
				<div class="summary-item">
					<strong><?php echo esc_html__( 'Product ID:', 'trendyol-woocommerce-importer' ); ?></strong>
					<span>#<?php echo esc_html( $product_id ); ?></span>
				</div>
				<div class="summary-item">
					<strong><?php echo esc_html__( 'Status:', 'trendyol-woocommerce-importer' ); ?></strong>
					<span><span class="badge bg-warning"><?php echo esc_html__( 'Draft', 'trendyol-woocommerce-importer' ); ?></span></span>
				</div>
				<div class="summary-item">
					<strong><?php echo esc_html__( 'Imported At:', 'trendyol-woocommerce-importer' ); ?></strong>
					<span><?php echo esc_html( current_time( 'Y-m-d H:i' ) ); ?></span>
				</div>
			</div>

			<div class="button-group" style="margin-bottom: 30px;">
				<a href="<?php echo esc_url( get_edit_post_link( $product_id ) ); ?>" class="btn btn-primary btn-lg" target="_blank">
					<?php echo esc_html__( '✏️ Edit Product', 'trendyol-woocommerce-importer' ); ?>
				</a>
				<a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>" class="btn btn-light btn-lg" target="_blank">
					<?php echo esc_html__( '👁️ View Product', 'trendyol-woocommerce-importer' ); ?>
				</a>
			</div>

			<div class="button-group">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=trendyol-importer' ) ); ?>" class="btn btn-success btn-lg">
					<?php echo esc_html__( '➕ Import Another Product', 'trendyol-woocommerce-importer' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>" class="btn btn-light btn-lg">
					<?php echo esc_html__( '📦 Go to Products', 'trendyol-woocommerce-importer' ); ?>
				</a>
			</div>
		</div>
	</div>
</body>
</html>