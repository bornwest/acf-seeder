<?php
/**
 * Read-only access to the seed files on disk (in the active theme by default).
 *
 *   seeds/data/<plural-name>/<slug>.json   one post per file (e.g. faqs/, team-members/)
 *   seeds/data/pages/<slug>.json           one page per file
 *   seeds/data/options/<slug>.json         one ACF options page per file
 *   seeds/files/<file>                files (PDFs etc.) referenced from the JSON
 *   seeds/images/<file>                    images referenced from the JSON
 */
class Acf_Seeder_Source
{
	const PAGES   = 'pages';
	const OPTIONS = 'options';

	// Folders in seeds/data that are not post types.
	const RESERVED = array('pages', 'options');

	private $dir;

	/**
	 * Seeds live in the active theme's root `seeds/` folder unless overridden
	 * with the `acf_seeder_dir` filter (absolute path, no trailing slash).
	 */
	public function __construct($dir = null)
	{
		$this->dir = $dir ?: apply_filters('acf_seeder_dir', get_stylesheet_directory() . '/seeds');
	}

	public function exists()
	{
		return is_dir($this->dir . '/data');
	}

	public function dir()
	{
		return $this->dir;
	}

	/**
	 * Seed titles grouped by folder, post type folders first and pages last:
	 * array( 'faqs' => array( 'slug' => 'Title' ), 'pages' => array( ... ) )
	 */
	public function groups()
	{
		$groups = array();

		foreach (glob($this->dir . '/data/*', GLOB_ONLYDIR) as $path) {
			$titles = array();

			foreach (glob($path . '/*.json') as $file) {
				$slug          = basename($file, '.json');
				$titles[$slug] = $this->read($file)['title'] ?? $slug;
			}

			if ($titles) {
				$groups[basename($path)] = $titles;
			}
		}

		// Post type folders first (alphabetical), then pages, then options.
		$rank = function ($folder) {
			return array(self::PAGES => 1, self::OPTIONS => 2)[$folder] ?? 0;
		};

		uksort($groups, function ($a, $b) use ($rank) {
			return $rank($a) <=> $rank($b) ?: strcmp($a, $b);
		});

		return $groups;
	}

	public function seed($folder, $slug)
	{
		return $this->read("{$this->dir}/data/{$folder}/{$slug}.json");
	}

	/**
	 * Absolute path of a referenced asset, relative to seeds/images (kind
	 * 'image') or seeds/files (kind 'file'). The extension may be omitted
	 * ("capability/fixed-income"), in which case the first file with that name
	 * and any extension is used. Null if nothing matches.
	 */
	public function asset_path($file, $kind = 'image')
	{
		$base = 'file' === $kind ? $this->dir . '/files/' : $this->dir . '/images/';
		$path = $base . $file;

		if (is_file($path)) {
			return $path;
		}

		$matches = glob($path . '.*');

		return $matches ? $matches[0] : null;
	}

	private function read($file)
	{
		$data = json_decode(file_get_contents($file), true);

		if (JSON_ERROR_NONE !== json_last_error()) {
			throw new RuntimeException(basename($file) . ': ' . json_last_error_msg());
		}

		return $data;
	}
}
