<?php
/**
 * Imports images (seeds/images) and files (seeds/files) into the media
 * library, once per file name.
 */
class Acf_Seeder_Asset_Importer
{
	const META_KEY = '_acf_seeder_image';

	private $source;
	private $log;

	public function __construct(Acf_Seeder_Source $source, Acf_Seeder_Log $log)
	{
		$this->source = $source;
		$this->log    = $log;

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	/**
	 * @param string|array $value "file.jpg" or array( 'file' => 'file.jpg', 'alt' => '...' )
	 * @param string       $kind  'image' (seeds/images) or 'file' (seeds/files)
	 * @return int|null Attachment ID.
	 */
	public function import($value, $kind = 'image')
	{
		$file = is_array($value) ? $value['file'] : $value;
		$alt  = is_array($value) ? ($value['alt'] ?? '') : '';

		$existing = $this->find($file);

		if ($existing) {
			return $existing;
		}

		$path = $this->source->asset_path($file, $kind);

		if (! $path) {
			$dir = 'file' === $kind ? 'seeds/files/' : 'seeds/images/';
			$this->log->warning("{$kind} not found: {$dir}{$file}");
			return null;
		}

		// media_handle_sideload moves the file, so hand it a copy.
		$tmp = wp_tempnam(basename($path));
		copy($path, $tmp);

		$id = media_handle_sideload(array('name' => basename($path), 'tmp_name' => $tmp), 0);

		if (is_wp_error($id)) {
			@unlink($tmp);
			throw new RuntimeException("{$file}: " . $id->get_error_message());
		}

		update_post_meta($id, self::META_KEY, $file);

		if ($alt) {
			update_post_meta($id, '_wp_attachment_image_alt', $alt);
		}

		return $id;
	}

	private function find($file)
	{
		$found = get_posts(array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'meta_key'    => self::META_KEY,
			'meta_value'  => $file,
			'numberposts' => 1,
			'fields'      => 'ids',
		));

		return $found ? $found[0] : null;
	}
}
