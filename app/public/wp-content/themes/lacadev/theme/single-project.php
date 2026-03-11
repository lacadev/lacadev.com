<?php
	/**
	 * App Layout: layouts/app.php
	 *
	 * Template for displaying a single project as a professional quotation document.
	 *
	 * @link    https://codex.wordpress.org/Template_Hierarchy
	 *
	 * @package WPEmergeTheme
	 */

	while (have_posts()) : the_post();

		$postId = get_the_ID();

		// --- Quotation fields ---
		$quotationIntro    = carbon_get_post_meta($postId, 'quotation_intro');
		$designPages       = carbon_get_post_meta($postId, 'design_pages');
		$backendFeatures   = carbon_get_post_meta($postId, 'backend_features');
		$timelinePhases    = carbon_get_post_meta($postId, 'timeline_phases');
		$quotationItems    = carbon_get_post_meta($postId, 'quotation_items');
		$workflowSteps     = carbon_get_post_meta($postId, 'workflow_steps');
		$clientReqs        = carbon_get_post_meta($postId, 'client_requirements');
		$paymentTerms      = carbon_get_post_meta($postId, 'payment_terms');
		$validDays         = carbon_get_post_meta($postId, 'quotation_valid_days') ?: '15';

		// --- Client Info ---
		$clientName    = carbon_get_post_meta($postId, 'client_name');
		$clientEmail   = carbon_get_post_meta($postId, 'client_email');
		$clientPhone   = carbon_get_post_meta($postId, 'client_phone');
		$clientAddress = carbon_get_post_meta($postId, 'client_address');
		$clientType    = carbon_get_post_meta($postId, 'client_type');

		// --- Status & Timeline ---
		$projectStatus = carbon_get_post_meta($postId, 'project_status');
		$estimatedDays = carbon_get_post_meta($postId, 'estimated_days');
		$dateStart     = carbon_get_post_meta($postId, 'date_start');
		$dateHandover  = carbon_get_post_meta($postId, 'date_handover');

		// --- Finance ---
		$priceBuild       = carbon_get_post_meta($postId, 'price_build');
		$priceMaintenance = carbon_get_post_meta($postId, 'price_maintenance_yearly');
		$paymentHistory   = carbon_get_post_meta($postId, 'payment_history');
		$paymentStatus    = carbon_get_post_meta($postId, 'payment_status');

		// --- Tech ---
		$platform       = carbon_get_post_meta($postId, 'platform');
		$builder        = carbon_get_post_meta($postId, 'builder');
		$features       = carbon_get_post_meta($postId, 'features');
		$customFeatures = carbon_get_post_meta($postId, 'custom_features');
		$demoUrl        = carbon_get_post_meta($postId, 'demo_design_url');

		// --- Maintenance ---
		$maintenanceType  = carbon_get_post_meta($postId, 'maintenance_type');
		$maintenanceStart = carbon_get_post_meta($postId, 'maintenance_start');
		$maintenanceEnd   = carbon_get_post_meta($postId, 'maintenance_end');
		$maintenanceScope = carbon_get_post_meta($postId, 'maintenance_scope');

		// --- Site meta ---
		$logoId    = carbon_get_theme_option('logo');
		$logoUrl   = $logoId ? wp_get_attachment_image_url($logoId, 'full') : '';
		$siteEmail = carbon_get_theme_option('email') ?: get_bloginfo('admin_email');
		$sitePhone = carbon_get_theme_option('phone');
		$siteAddress = carbon_get_theme_option('address');

		// --- State maps ---
		$statusLabels = [
			'pending'     => ['label' => 'Chờ duyệt',      'class' => 'badge--warning'],
			'in_progress' => ['label' => 'Đang thực hiện', 'class' => 'badge--info'],
			'done'        => ['label' => 'Hoàn thành',     'class' => 'badge--success'],
			'maintenance' => ['label' => 'Bảo trì',        'class' => 'badge--neutral'],
			'paused'      => ['label' => 'Tạm dừng',       'class' => 'badge--neutral'],
		];
		$statusInfo = $statusLabels[$projectStatus] ?? ['label' => 'Không rõ', 'class' => 'badge--neutral'];

		$paymentLabels = [
			'pending' => ['label' => 'Chưa thanh toán',        'class' => 'badge--warning'],
			'partial' => ['label' => 'Đã thanh toán một phần', 'class' => 'badge--info'],
			'paid'    => ['label' => 'Đã thanh toán đủ',       'class' => 'badge--success'],
			'overdue' => ['label' => 'Quá hạn',                'class' => 'badge--danger'],
		];
		$paymentInfo = $paymentLabels[$paymentStatus] ?? ['label' => '—', 'class' => 'badge--neutral'];

		$platformLabels = [
			'wordpress'    => 'WordPress',
			'woocommerce'  => 'WooCommerce',
			'landing_page' => 'Landing Page',
			'shopify'      => 'Shopify',
			'laravel'      => 'Laravel',
			'next_js'      => 'Next.js',
			'custom'       => 'Custom Code',
		];
		$featureLabels = [
			'landing_page'   => 'Landing Page',
			'multi_language' => 'Đa ngôn ngữ',
			'booking'        => 'Booking System',
			'payment'        => 'Cổng thanh toán',
			'flash_sale'     => 'Flash Sale',
			'seo'            => 'SEO Tối ưu',
			'speed'          => 'Tốc độ cao',
			'membership'     => 'Membership',
			'chat'           => 'Live Chat',
		];

		// --- Finance calc ---
		$totalPaid = 0;
		if (!empty($paymentHistory)) {
			foreach ($paymentHistory as $ph) {
				$totalPaid += (int) preg_replace('/[^0-9]/', '', $ph['pay_amount'] ?? '0');
			}
		}
		$priceBuildNum = (int) preg_replace('/[^0-9]/', '', $priceBuild ?? '0');
		$remaining     = $priceBuildNum - $totalPaid;

		// Quotation items total
		$quotationTotal = 0;
		if (!empty($quotationItems)) {
			foreach ($quotationItems as $qi) {
				$unitPrice = (int) preg_replace('/[^0-9]/', '', $qi['item_unit_price'] ?? '0');
				$qty       = max(1, (int) ($qi['item_qty'] ?? 1));
				$quotationTotal += $unitPrice * $qty;
			}
		}

		// Date issued & validity
		$dateIssued = get_the_date('d/m/Y');
		$validUntil = date('d/m/Y', strtotime('+' . intval($validDays) . ' days', strtotime(get_the_date('Y-m-d'))));

	endwhile;
?>

<article class="quotation-doc">
    <?php get_template_part('template-parts/post-hero'); ?>

	<!-- ============================================================
	     DOCUMENT HEADER
	     ============================================================ -->
	<header class="qd-header">
		<div class="qd-header__brand">
			<?php if ($logoUrl) : ?>
				<a href="<?php echo esc_url(home_url('/')); ?>">
					<img src="<?php echo esc_url($logoUrl); ?>" alt="<?php bloginfo('name'); ?>" class="qd-header__logo">
				</a>
			<?php else : ?>
				<a href="<?php echo esc_url(home_url('/')); ?>" class="qd-header__site-name"><?php bloginfo('name'); ?></a>
			<?php endif; ?>
		</div>

		<div class="qd-header__info">
			<h1 class="qd-header__title">BÁO GIÁ DỊCH VỤ</h1>
			<table class="qd-header__meta-table">
				<tr>
					<td>Số báo giá</td>
					<td><strong>#<?php echo get_the_ID(); ?></strong></td>
				</tr>
				<tr>
					<td>Ngày lập</td>
					<td><?php echo esc_html($dateIssued); ?></td>
				</tr>
				<tr>
					<td>Hiệu lực đến</td>
					<td><?php echo esc_html($validUntil); ?></td>
				</tr>
				<tr>
					<td>Trạng thái</td>
					<td><span class="badge <?php echo esc_attr($statusInfo['class']); ?>"><?php echo esc_html($statusInfo['label']); ?></span></td>
				</tr>
			</table>
		</div>
	</header>

	<!-- Parties: Provider ↔ Client -->
	<div class="qd-parties">
		<div class="qd-party">
			<p class="qd-party__role">Bên cung cấp dịch vụ (Bên A)</p>
			<p class="qd-party__name"><?php bloginfo('name'); ?></p>
			<?php if ($siteEmail) : ?>
				<p class="qd-party__detail">
					<strong>Email:</strong> <a href="mailto:<?php echo esc_attr($siteEmail); ?>"><?php echo esc_html($siteEmail); ?></a>
				</p>
			<?php endif; ?>
			<?php if ($sitePhone) : ?>
				<p class="qd-party__detail"><strong>ĐT:</strong> <?php echo esc_html($sitePhone); ?></p>
			<?php endif; ?>
			<?php if ($siteAddress) : ?>
				<p class="qd-party__detail"><strong>Địa chỉ:</strong> <?php echo esc_html($siteAddress); ?></p>
			<?php endif; ?>
		</div>

		<div class="qd-party qd-party--client">
			<p class="qd-party__role">Bên sử dụng dịch vụ (Bên B)</p>
			<p class="qd-party__name"><?php echo esc_html($clientName ?: get_the_title()); ?></p>
			<?php if ($clientEmail) : ?>
				<p class="qd-party__detail"><strong>Email:</strong> <a href="mailto:<?php echo esc_attr($clientEmail); ?>"><?php echo esc_html($clientEmail); ?></a></p>
			<?php endif; ?>
			<?php if ($clientPhone) : ?>
				<p class="qd-party__detail"><strong>ĐT:</strong> <a href="tel:<?php echo esc_attr(preg_replace('/\s/', '', $clientPhone)); ?>"><?php echo esc_html($clientPhone); ?></a></p>
			<?php endif; ?>
			<?php if ($clientAddress) : ?>
				<p class="qd-party__detail"><strong>Địa chỉ:</strong> <?php echo esc_html($clientAddress); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<hr class="qd-rule">

	<!-- ============================================================
	     SECTION I — GIỚI THIỆU
	     ============================================================ -->
	<?php if ($quotationIntro || get_the_content()) : ?>
		<section class="qd-section">
			<h2 class="qd-section__heading"><span class="qd-section__num">I</span> Giới thiệu</h2>
			<div class="qd-prose">
				<?php
				if ($quotationIntro) {
					echo wp_kses_post(apply_filters('the_content', $quotationIntro));
				} else {
					theContent();
				}
				?>
			</div>
			<?php if ($demoUrl) : ?>
				<a href="<?php echo esc_url($demoUrl); ?>" target="_blank" rel="noopener noreferrer" class="qd-demo-link">
					<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
					Xem thiết kế mẫu / Figma
				</a>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<!-- ============================================================
	     SECTION II — PHẠM VI CÔNG VIỆC
	     ============================================================ -->
	<?php if (!empty($designPages) || !empty($platform) || !empty($features) || !empty($customFeatures) || $backendFeatures) : ?>
		<section class="qd-section">
			<h2 class="qd-section__heading"><span class="qd-section__num">II</span> Phạm vi công việc</h2>

			<!-- 2A: Trang thiết kế -->
			<?php if (!empty($designPages)) : ?>
				<h3 class="qd-subsection">A. Danh sách trang thiết kế</h3>
				<table class="qd-table">
					<thead>
						<tr>
							<th class="qd-table__col-num">STT</th>
							<th>Tên trang</th>
							<th>Website mẫu tham khảo</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($designPages as $i => $page) : ?>
							<tr>
								<td class="qd-table__col-num"><?php echo ($i + 1); ?></td>
								<td><?php echo esc_html($page['page_name'] ?? ''); ?></td>
								<td>
									<?php if (!empty($page['page_demo_url'])) : ?>
										<a href="<?php echo esc_url($page['page_demo_url']); ?>" target="_blank" rel="noopener noreferrer" class="qd-link">
											<?php echo esc_html(parse_url($page['page_demo_url'], PHP_URL_HOST) ?: $page['page_demo_url']); ?>
										</a>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<!-- 2B: Nền tảng & Tính năng -->
			<?php if (!empty($platform) || !empty($features) || !empty($customFeatures)) : ?>
				<h3 class="qd-subsection">B. Nền tảng &amp; Tính năng</h3>
				<div class="qd-feature-grid">
					<?php if (!empty($platform)) : ?>
						<div class="qd-feature-group">
							<span class="qd-feature-group__label">Nền tảng</span>
							<div class="qd-tags">
								<?php foreach ((array)$platform as $p) : ?>
									<span class="qd-tag qd-tag--platform"><?php echo esc_html($platformLabels[$p] ?? $p); ?></span>
								<?php endforeach; ?>
								<?php if ($builder) : ?>
									<span class="qd-tag qd-tag--builder"><?php echo esc_html($builder); ?></span>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>
					<?php if (!empty($features) || !empty($customFeatures)) : ?>
						<div class="qd-feature-group">
							<span class="qd-feature-group__label">Tính năng chuẩn</span>
							<div class="qd-tags">
								<?php foreach ((array)$features as $f) : ?>
									<span class="qd-tag"><?php echo esc_html($featureLabels[$f] ?? $f); ?></span>
								<?php endforeach; ?>
								<?php foreach ((array)$customFeatures as $cf) : ?>
									<span class="qd-tag qd-tag--custom"><?php echo esc_html($cf['name'] ?? ''); ?></span>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<!-- 2C: Lập trình backend -->
			<?php if ($backendFeatures) : ?>
				<h3 class="qd-subsection">C. Tính năng kỹ thuật / Lập trình Backend</h3>
				<div class="qd-prose">
					<?php echo wp_kses_post(apply_filters('the_content', $backendFeatures)); ?>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<!-- ============================================================
	     SECTION III — THỜI GIAN THỰC HIỆN
	     ============================================================ -->
	<?php if ($estimatedDays || !empty($timelinePhases) || $dateStart || $dateHandover) : ?>
		<section class="qd-section">
			<h2 class="qd-section__heading"><span class="qd-section__num">III</span> Thời gian thực hiện</h2>

			<?php if ($estimatedDays || $dateStart || $dateHandover) : ?>
				<div class="qd-timeline-summary">
					<?php if ($estimatedDays) : ?>
						<div class="qd-timeline-chip">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
							Tổng thời gian: <strong><?php echo esc_html($estimatedDays); ?> ngày</strong>
						</div>
					<?php endif; ?>
					<?php if ($dateStart) : ?>
						<div class="qd-timeline-chip">
							📅 Dự kiến bắt đầu: <strong><?php echo date('d/m/Y', strtotime($dateStart)); ?></strong>
						</div>
					<?php endif; ?>
					<?php if ($dateHandover) : ?>
						<div class="qd-timeline-chip">
							🚀 Dự kiến bàn giao: <strong><?php echo date('d/m/Y', strtotime($dateHandover)); ?></strong>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if (!empty($timelinePhases)) : ?>
				<table class="qd-table qd-table--timeline">
					<thead>
						<tr>
							<th class="qd-table__col-num">Giai đoạn</th>
							<th>Nội dung công việc</th>
							<th class="qd-table__col-days">Thời gian</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($timelinePhases as $phase) : ?>
							<tr>
								<td class="qd-table__col-num qd-phase-name"><?php echo esc_html($phase['phase_name'] ?? ''); ?></td>
								<td class="qd-prose qd-prose--sm">
									<?php echo wp_kses_post(apply_filters('the_content', $phase['phase_content'] ?? '')); ?>
								</td>
								<td class="qd-table__col-days">
									<?php if (!empty($phase['phase_days'])) : ?>
										<?php echo esc_html($phase['phase_days']); ?> ngày
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<!-- ============================================================
	     SECTION IV — CHI PHÍ THỰC HIỆN
	     ============================================================ -->
	<?php if (!empty($quotationItems) || $priceBuild) : ?>
		<section class="qd-section">
			<h2 class="qd-section__heading"><span class="qd-section__num">IV</span> Chi phí thực hiện</h2>

			<?php if (!empty($quotationItems)) : ?>
				<table class="qd-table qd-table--cost">
					<thead>
						<tr>
							<th class="qd-table__col-num">STT</th>
							<th>Mô tả hạng mục</th>
							<th class="qd-table__col-price">Đơn giá</th>
							<th class="qd-table__col-qty">SL</th>
							<th class="qd-table__col-price">Thành tiền / Ghi chú</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($quotationItems as $i => $qi) :
							$unitPrice = (int) preg_replace('/[^0-9]/', '', $qi['item_unit_price'] ?? '0');
							$qty       = max(1, (int) ($qi['item_qty'] ?? 1));
							$lineTotal = $unitPrice * $qty;
						?>
							<tr>
								<td class="qd-table__col-num"><?php echo ($i + 1); ?></td>
								<td><?php echo esc_html($qi['item_name'] ?? ''); ?></td>
								<td class="qd-table__col-price qd-amount">
									<?php echo !empty($qi['item_unit_price']) ? esc_html($qi['item_unit_price']) : '—'; ?>
								</td>
								<td class="qd-table__col-qty"><?php echo esc_html($qi['item_qty'] ?? '1'); ?></td>
								<td class="qd-table__col-price qd-amount">
									<?php
									if (!empty($qi['item_note'])) {
										echo esc_html($qi['item_note']);
									} elseif ($lineTotal > 0) {
										echo number_format($lineTotal, 0, ',', '.') . ' đ';
									} else {
										echo '—';
									}
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<?php if ($quotationTotal > 0 || $priceBuild) : ?>
						<tfoot>
							<tr class="qd-table__total-row">
								<td colspan="4"><strong>Tổng chi phí xây dựng</strong></td>
								<td class="qd-table__col-price qd-amount qd-amount--total">
									<?php
									if ($priceBuild) {
										echo esc_html($priceBuild) . ' đ';
									} else {
										echo number_format($quotationTotal, 0, ',', '.') . ' đ';
									}
									?>
								</td>
							</tr>
							<?php if ($priceMaintenance) : ?>
								<tr class="qd-table__note-row">
									<td colspan="4">Phí bảo trì hàng năm (sau bàn giao)</td>
									<td class="qd-table__col-price qd-amount"><?php echo esc_html($priceMaintenance); ?> đ/năm</td>
								</tr>
							<?php endif; ?>
						</tfoot>
					<?php endif; ?>
				</table>
			<?php elseif ($priceBuild) : ?>
				<!-- Chỉ hiển thị tổng nếu không có items chi tiết -->
				<div class="qd-price-summary">
					<span class="qd-price-summary__label">Chi phí xây dựng website</span>
					<span class="qd-price-summary__value"><?php echo esc_html($priceBuild); ?> đ</span>
				</div>
				<?php if ($priceMaintenance) : ?>
					<div class="qd-price-summary qd-price-summary--muted">
						<span class="qd-price-summary__label">Phí bảo trì hàng năm</span>
						<span class="qd-price-summary__value"><?php echo esc_html($priceMaintenance); ?> đ/năm</span>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<!-- Lịch sử thanh toán -->
			<?php if (!empty($paymentHistory)) : ?>
				<h3 class="qd-subsection">Lịch sử thanh toán</h3>
				<table class="qd-table qd-table--payment">
					<thead>
						<tr>
							<th>Ngày</th>
							<th>Ghi chú</th>
							<th class="qd-table__col-price">Số tiền</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($paymentHistory as $ph) :
							if (empty($ph['pay_amount'])) continue;
						?>
							<tr>
								<td><?php echo !empty($ph['pay_date']) ? date('d/m/Y', strtotime($ph['pay_date'])) : '—'; ?></td>
								<td><?php echo esc_html($ph['pay_note'] ?? ''); ?></td>
								<td class="qd-table__col-price qd-amount qd-amount--paid"><?php echo esc_html($ph['pay_amount']); ?> đ</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<?php if ($priceBuildNum > 0 && $totalPaid > 0) : ?>
						<tfoot>
							<tr>
								<td colspan="2"><strong>Đã thanh toán</strong></td>
								<td class="qd-table__col-price qd-amount qd-amount--paid"><?php echo number_format($totalPaid, 0, ',', '.'); ?> đ</td>
							</tr>
							<?php if ($remaining > 0) : ?>
								<tr class="qd-table__remaining-row">
									<td colspan="2">Còn lại</td>
									<td class="qd-table__col-price qd-amount qd-amount--danger"><?php echo number_format($remaining, 0, ',', '.'); ?> đ</td>
								</tr>
							<?php endif; ?>
						</tfoot>
					<?php endif; ?>
				</table>
				<div class="qd-payment-status">
					Trạng thái thanh toán: <span class="badge <?php echo esc_attr($paymentInfo['class']); ?>"><?php echo esc_html($paymentInfo['label']); ?></span>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<!-- ============================================================
	     SECTION V — QUY TRÌNH LÀM VIỆC
	     ============================================================ -->
	<?php if ($workflowSteps) : ?>
		<section class="qd-section">
			<h2 class="qd-section__heading"><span class="qd-section__num">V</span> Quy trình làm việc</h2>
			<div class="qd-prose">
				<?php echo wp_kses_post(apply_filters('the_content', $workflowSteps)); ?>
			</div>
		</section>
	<?php endif; ?>

	<!-- ============================================================
	     SECTION VI — CHÍNH SÁCH BẢO TRÌ / BẢO HÀNH
	     ============================================================ -->
	<?php if ($maintenanceType && $maintenanceType !== 'none') : ?>
		<section class="qd-section">
			<h2 class="qd-section__heading"><span class="qd-section__num">VI</span> Chính sách bảo trì &amp; bảo hành</h2>
			<div class="qd-maintenance-header">
				<span class="badge <?php echo $maintenanceType === 'free' ? 'badge--success' : 'badge--info'; ?>">
					<?php echo $maintenanceType === 'free' ? 'Bảo hành miễn phí' : 'Bảo trì có phí'; ?>
				</span>
				<?php if ($maintenanceStart && $maintenanceEnd) : ?>
					<span class="qd-maintenance-period">
						<?php echo date('d/m/Y', strtotime($maintenanceStart)); ?> — <?php echo date('d/m/Y', strtotime($maintenanceEnd)); ?>
					</span>
				<?php endif; ?>
			</div>
			<?php if ($maintenanceScope) : ?>
				<div class="qd-prose">
					<?php echo wp_kses_post(apply_filters('the_content', $maintenanceScope)); ?>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<!-- ============================================================
	     SECTION VII — YÊU CẦU TỪ PHÍA KHÁCH HÀNG
	     ============================================================ -->
	<?php if ($clientReqs) : ?>
		<section class="qd-section">
			<h2 class="qd-section__heading"><span class="qd-section__num">VII</span> Yêu cầu từ phía Bên B</h2>
			<div class="qd-prose">
				<?php echo wp_kses_post(apply_filters('the_content', $clientReqs)); ?>
			</div>
		</section>
	<?php endif; ?>

	<!-- ============================================================
	     SECTION VIII — PHƯƠNG THỨC THANH TOÁN
	     ============================================================ -->
	<?php if ($paymentTerms) : ?>
		<section class="qd-section">
			<h2 class="qd-section__heading"><span class="qd-section__num">VIII</span> Phương thức thanh toán</h2>
			<div class="qd-prose">
				<?php echo wp_kses_post(apply_filters('the_content', $paymentTerms)); ?>
			</div>
		</section>
	<?php endif; ?>



</article>
