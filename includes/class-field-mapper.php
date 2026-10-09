<?php
/**
 * Writes a seed's `fields` into ACF.
 *
 * Seed values mirror the *logical* ACF structure (groups, repeaters, cloned
 * groups). Cloned-field prefixes are resolved automatically, so a cloned
 * `header` group is written as `"header": { "title": "..." }`.
 *
 * Value conventions:
 *   image        "file.jpg" (relative to seeds/images) or { "file": "file.jpg", "alt": "..." }
 *   file         "doc.pdf" (relative to seeds/files)
 *   post_object  post slug (or ID)
 *   link         { "title": "...", "url": "...", "target": "" }
 */
class Acf_Seeder_Field_Mapper
{
	private $assets;
	private $log;

	public function __construct(Acf_Seeder_Asset_Importer $assets, Acf_Seeder_Log $log)
	{
		$this->assets = $assets;
		$this->log    = $log;
	}

	/** @param int|string $post_id Post ID, or 'options' for ACF options pages. */
	public function apply($post_id, $post_type, $seed)
	{
		$data = $seed['fields'] ?? array();

		// Nothing to write (e.g. a blank page): skip the ACF group lookup and its warnings.
		if (! $data) {
			return;
		}

		$fields = $this->fields_for($post_id, $post_type, $seed);

		foreach ($fields as $field) {
			$value = $this->lookup($field['name'], $data);

			if (null !== $value) {
				update_field($field['key'], $this->convert($field, $value), $post_id);
			}
		}

		$this->warn_unmatched($seed['slug'], $data, $fields);
	}

	/**
	 * Top-level ACF fields for a seed: the field group(s) named by `group` (a title or a list of titles), or
	 * else the groups whose location matches the post / post type / page template.
	 */
	private function fields_for($post_id, $post_type, $seed)
	{
		$groups = array();

		if (! empty($seed['group'])) {
			$titles = (array) $seed['group'];

			foreach (acf_get_field_groups() as $group) {
				if (in_array($group['title'], $titles, true)) {
					$groups[] = $group;
				}
			}
		}

		if (! $groups) {
			$filter = array('post_type' => $post_type);

			// Lets rules that depend on the post itself (post slug, ID, ...) match.
			if (is_numeric($post_id)) {
				$filter['post_id'] = (int) $post_id;
			}

			if (! empty($seed['template'])) {
				$filter['page_template'] = $seed['template'];
			}

			$groups = acf_get_field_groups($filter);
		}

		if (! $groups) {
			$this->log->warning("No ACF field group found for '{$seed['slug']}'.");
		} else {
			$this->log->info("'{$seed['slug']}': field groups matched — " . implode(', ', array_column($groups, 'title')));
		}

		$fields = array();

		foreach ($groups as $group) {
			$fields = array_merge($fields, acf_get_fields($group['key']) ?: array());
		}

		return $fields;
	}

	/**
	 * Find the seed value for an ACF field name, tolerating clone prefixes:
	 * `header_title` is found at data['header_title'], data['header']['title']
	 * or data['title'].
	 */
	private function lookup($name, $data)
	{
		if (! is_array($data)) {
			return null;
		}

		if (array_key_exists($name, $data)) {
			return $data[$name];
		}

		foreach ($data as $key => $value) {
			if (is_string($key) && is_array($value) && ! $this->is_list($value) && 0 === strpos($name, $key . '_')) {
				$found = $this->lookup(substr($name, strlen($key) + 1), $value);

				if (null !== $found) {
					return $found;
				}
			}
		}

		foreach ($data as $key => $value) {
			if (is_string($key) && '_' . $key === substr($name, -strlen($key) - 1)) {
				return $value;
			}
		}

		return null;
	}

	private function is_list($value)
	{
		return array_keys($value) === range(0, count($value) - 1);
	}

	/**
	 * Resolve sub fields of a group / repeater row into an array keyed by
	 * field key, which ACF accepts regardless of clone prefixing.
	 */
	private function convert_fields($fields, $data)
	{
		$out = array();

		foreach ($fields as $field) {
			// Seamless clones that were not expanded are treated as inline.
			if ('clone' === $field['type'] && 'seamless' === ($field['display'] ?? '') && ! empty($field['sub_fields'])) {
				$nested = isset($data[$field['name']]) && is_array($data[$field['name']]) ? $data[$field['name']] : $data;
				$out    = array_merge($out, $this->convert_fields($field['sub_fields'], $nested));
				continue;
			}

			$value = $this->lookup($field['name'], $data);

			if (null !== $value) {
				$out[$field['key']] = $this->convert($field, $value);
			}
		}

		return $out;
	}

	private function convert($field, $value)
	{
		switch ($field['type']) {
			case 'group':
			case 'clone':
				return $this->convert_fields($field['sub_fields'] ?? array(), (array) $value);

			case 'repeater':
				return array_map(function ($row) use ($field) {
					return $this->convert_fields($field['sub_fields'], (array) $row);
				}, (array) $value);

			case 'image':
				return $this->assets->import($value, 'image');

			case 'file':
				return $this->assets->import($value, 'file');

			case 'post_object':
				return $this->post_id($value, $field['post_type'] ?? 'any');

			case 'true_false':
				return (int) (bool) $value;

			default:
				return $value;
		}
	}

	private function post_id($value, $post_type)
	{
		if (is_numeric($value)) {
			return (int) $value;
		}

		$found = get_posts(array(
			'name'        => $value,
			'post_type'   => $post_type ?: 'any',
			'post_status' => 'any',
			'numberposts' => 1,
			'fields'      => 'ids',
		));

		if (! $found) {
			$this->log->warning("Referenced post '{$value}' not found — seed its post type first.");
			return null;
		}

		return $found[0];
	}

	private function warn_unmatched($slug, $data, $fields)
	{
		$names = array_column($fields, 'name');

		foreach (array_keys($data) as $key) {
			if (! in_array($key, $names, true)) {
				$this->log->warning("'{$slug}': no top-level ACF field named '{$key}'.");
			}
		}
	}
}
