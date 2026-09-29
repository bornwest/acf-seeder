<?php
/**
 * Plugin Name:       ACF Seeder
 * Description:       Seeds posts, pages and ACF field values (and their images) from JSON files in the active theme's seeds/ folder, from a "Seed Content" admin page.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  advanced-custom-fields-pro
 * Text Domain:       acf-seeder
 */

if (! defined('ABSPATH')) {
	exit;
}

define('ACF_SEEDER_DIR', plugin_dir_path(__FILE__));
define('ACF_SEEDER_URL', plugin_dir_url(__FILE__));

// The seeder only has an admin UI (including admin-post.php requests).
if (is_admin()) {
	foreach (array('log', 'source', 'image-importer', 'field-mapper', 'seeder', 'admin-page') as $name) {
		require_once ACF_SEEDER_DIR . "includes/class-{$name}.php";
	}

	(new Acf_Seeder_Admin_Page())->register();
}
