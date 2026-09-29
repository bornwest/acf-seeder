<?php
/**
 * Seed Content admin page.
 *
 * @var array  $groups   Seed titles by folder (Acf_Seeder_Source::groups()).
 * @var bool   $ready    Whether ACF is available.
 * @var array  $messages Messages from the previous run.
 * @var string $action   admin-post action name.
 * @var array  $icons    Dashicon per folder.
 * @var string $dir      Absolute path of the seeds directory.
 */
?>
<div class="wrap cseed">
	<h1 class="wp-heading-inline">Seed Content</h1>
	<hr class="wp-header-end">

	<p class="cseed__intro">Creates posts and pages from the JSON files in the active theme's <code>seeds/data</code>, with images from
		<code>seeds/images</code>. Existing posts with the same slug are <strong>overwritten</strong>; images are only uploaded once.</p>

	<?php if (! $groups) : ?>
		<div class="notice notice-warning inline">
			<p>No seed files found in <code><?php echo esc_html($dir); ?>/data</code>.</p>
		</div>
	<?php endif; ?>

	<?php if (! $ready) : ?>
		<div class="notice notice-error"><p>ACF is not active, so seeding is unavailable.</p></div>
	<?php endif; ?>

	<?php foreach ($messages as $message) : ?>
		<?php
		$classes = array('warning' => 'notice-warning', 'error' => 'notice-error', 'success' => 'notice-success');
		?>
		<div class="notice <?php echo esc_attr($classes[$message['level']] ?? 'notice-info'); ?> inline">
			<p><?php echo esc_html($message['text']); ?></p>
		</div>
	<?php endforeach; ?>

	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-cseed-form>
		<input type="hidden" name="action" value="<?php echo esc_attr($action); ?>">
		<?php wp_nonce_field($action); ?>

		<?php foreach ($groups as $folder => $titles) : ?>
			<section class="cseed__group">
				<div class="cseed__head">
					<h2><?php echo esc_html(ucwords(str_replace('-', ' ', $folder))); ?></h2>
					<span class="cseed__count"><?php echo count($titles); ?></span>
					<button type="button" class="button-link cseed__toggle" data-cseed-toggle>Deselect all</button>
				</div>
				<div class="cseed__grid">
					<?php foreach ($titles as $slug => $title) : ?>
						<label class="cseed__card">
							<span class="dashicons <?php echo esc_attr($icons[$folder] ?? 'dashicons-admin-post'); ?>"></span>
							<span class="cseed__name"><?php echo esc_html($title); ?></span>
							<input type="checkbox" name="seed[]" value="<?php echo esc_attr("{$folder}/{$slug}"); ?>" checked>
						</label>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; ?>

		<div class="cseed__actions">
			<?php submit_button('Run seeder', 'primary', 'submit', false, $ready ? array() : array('disabled' => 'disabled')); ?>
			<span class="cseed__hint">Only checked items are seeded.</span>
		</div>
	</form>
</div>
