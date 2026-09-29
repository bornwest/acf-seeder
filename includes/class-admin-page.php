<?php
/**
 * "Seed Content" admin sidebar page: lists the available seeds as checkboxes
 * and runs the selected ones through Acf_Seeder.
 */
class Acf_Seeder_Admin_Page
{
	const SLUG       = 'acf-seeder';
	const ACTION     = 'acf_seeder';
	const CAPABILITY = 'manage_options';

	public function register()
	{
		add_action('admin_menu', array($this, 'add_menu'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
		add_action('admin_post_' . self::ACTION, array($this, 'handle_submit'));
	}

	public function add_menu()
	{
		add_menu_page(
			'Seed Content',
			'Seed Content',
			self::CAPABILITY,
			self::SLUG,
			array($this, 'render'),
			'dashicons-database-import',
			80
		);
	}

	public function enqueue_assets($hook)
	{
		if ('toplevel_page_' . self::SLUG !== $hook) {
			return;
		}

		$base = ACF_SEEDER_DIR . 'assets/';
		$uri  = ACF_SEEDER_URL . 'assets/';

		wp_enqueue_style('acf-seeder', $uri . 'admin-page.css', array(), filemtime($base . 'admin-page.css'));
		wp_enqueue_script('acf-seeder', $uri . 'admin-page.js', array(), filemtime($base . 'admin-page.js'), true);
	}

	public function handle_submit()
	{
		if (! current_user_can(self::CAPABILITY)) {
			wp_die('You do not have permission to do this.', 403);
		}

		check_admin_referer(self::ACTION);

		$only = isset($_POST['seed']) ? array_map('sanitize_text_field', (array) wp_unslash($_POST['seed'])) : array();

		if (! $only) {
			$this->finish(array(array('level' => 'warning', 'text' => 'Nothing was selected.')));
		}

		@set_time_limit(300);

		$seeder = new Acf_Seeder();

		try {
			$seeder->run($only);
			$messages   = $seeder->log()->all();
			$messages[] = array('level' => 'success', 'text' => 'Seeding complete.');
		} catch (Exception $e) {
			$messages   = $seeder->log()->all();
			$messages[] = array('level' => 'error', 'text' => $e->getMessage());
		}

		$this->finish($messages);
	}

	public function render()
	{
		if (! current_user_can(self::CAPABILITY)) {
			return;
		}

		$seeder   = new Acf_Seeder();
		$groups   = $seeder->items();
		$ready    = function_exists('acf_get_fields');
		$messages = $this->pull_messages();
		$action   = self::ACTION;
		$dir      = $seeder->source()->dir();
		$icons    = $this->icons(array_keys($groups), $seeder);

		require ACF_SEEDER_DIR . 'views/admin-page.php';
	}

	/** Dashicon per folder, taken from the registered post type's menu icon. */
	private function icons($folders, $seeder)
	{
		$icons = array(Acf_Seeder_Source::PAGES => 'dashicons-admin-page');

		foreach ($folders as $folder) {
			$type = $seeder->post_type_for_folder($folder);
			$icon = $type ? get_post_type_object($type)->menu_icon : null;

			if (is_string($icon) && 0 === strpos($icon, 'dashicons-')) {
				$icons[$folder] = $icon;
			}
		}

		return $icons;
	}

	/** Stash messages for the next page load, then redirect back (post/redirect/get). */
	private function finish($messages)
	{
		set_transient($this->messages_key(), $messages, 5 * MINUTE_IN_SECONDS);

		wp_safe_redirect(admin_url('admin.php?page=' . self::SLUG));
		exit;
	}

	private function pull_messages()
	{
		$messages = get_transient($this->messages_key());
		delete_transient($this->messages_key());

		return (array) $messages;
	}

	private function messages_key()
	{
		return 'acf_seeder_messages_' . get_current_user_id();
	}
}
