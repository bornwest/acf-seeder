<?php
/**
 * Creates or updates posts, pages and ACF options pages from the seed files.
 *
 * Idempotent: posts are matched by post type + slug and updated in place.
 * Post types are seeded first (pages reference them), then pages, then options.
 */
class Acf_Seeder
{
	private $source;
	private $log;
	private $mapper;

	public function __construct(?Acf_Seeder_Source $source = null, ?Acf_Seeder_Log $log = null)
	{
		$this->source = $source ?: new Acf_Seeder_Source();
		$this->log    = $log ?: new Acf_Seeder_Log();
	}

	public function source()
	{
		return $this->source;
	}

	/** @see Acf_Seeder_Source::groups() */
	public function items()
	{
		return $this->source->groups();
	}

	public function log()
	{
		return $this->log;
	}

	/**
	 * @param string[] $only Entries to seed, empty for everything. Each is
	 *                       'folder/slug' (as listed by items()), a whole folder,
	 *                       a post type, or a bare slug.
	 */
	public function run(array $only = array())
	{
		if (! function_exists('update_field') || ! function_exists('acf_get_fields')) {
			throw new RuntimeException('ACF is not active.');
		}

		$this->mapper = new Acf_Seeder_Field_Mapper(new Acf_Seeder_Asset_Importer($this->source, $this->log), $this->log);
		$only         = array_filter($only);

		foreach ($this->items() as $folder => $titles) {
			if (in_array($folder, Acf_Seeder_Source::RESERVED, true)) {
				continue;
			}

			$post_type = $this->post_type_for_folder($folder);

			if (! $post_type) {
				$this->log->warning("No registered post type matches folder '{$folder}'.");
				continue;
			}

			foreach (array_keys($titles) as $slug) {
				if ($this->selected($only, array($folder, $post_type, $slug, "{$folder}/{$slug}"))) {
					$this->seed_post($post_type, $this->source->seed($folder, $slug));
				}
			}
		}

		foreach (array_keys($this->items()[Acf_Seeder_Source::PAGES] ?? array()) as $slug) {
			if ($this->selected($only, array('pages', 'page', $slug, "pages/{$slug}"))) {
				$this->seed_post('page', $this->source->seed(Acf_Seeder_Source::PAGES, $slug));
			}
		}

		foreach (array_keys($this->items()[Acf_Seeder_Source::OPTIONS] ?? array()) as $slug) {
			if ($this->selected($only, array('options', $slug, "options/{$slug}"))) {
				$this->seed_options($this->source->seed(Acf_Seeder_Source::OPTIONS, $slug));
			}
		}
	}

	private function selected($only, $candidates)
	{
		return ! $only || (bool) array_intersect($only, $candidates);
	}

	/**
	 * Map a plural folder name (team-members) to its post type (team-member)
	 * by matching the post type name or its plural label.
	 */
	public function post_type_for_folder($folder)
	{
		foreach (get_post_types(array(), 'objects') as $type) {
			if ($folder === $type->name || $folder === sanitize_title($type->labels->name)) {
				return $type->name;
			}
		}

		return null;
	}

	/** Write an options page's fields to ACF's shared 'options' store. */
	private function seed_options($seed)
	{
		$this->mapper->apply('options', 'options', $seed);

		$this->log->info("Updated options '{$seed['slug']}'");
	}

	private function seed_post($post_type, $seed)
	{
		$existing = get_posts(array(
			'post_type'   => $post_type,
			'name'        => $seed['slug'],
			'post_status' => 'any',
			'numberposts' => 1,
			'fields'      => 'ids',
		));

		$args = array(
			'post_type'   => $post_type,
			'post_name'   => $seed['slug'],
			'post_title'  => $seed['title'],
			'post_status' => 'publish',
		);

		if ($existing) {
			$args['ID'] = $existing[0];
		}

		$id = wp_insert_post($args, true);

		if (is_wp_error($id)) {
			throw new RuntimeException($seed['slug'] . ': ' . $id->get_error_message());
		}

		if ('page' === $post_type && ! empty($seed['template'])) {
			update_post_meta($id, '_wp_page_template', $seed['template']);
		}

		$this->mapper->apply($id, $post_type, $seed);

		$this->log->info(($existing ? 'Updated' : 'Created') . " {$post_type} '{$seed['slug']}' (#{$id})");
	}
}
