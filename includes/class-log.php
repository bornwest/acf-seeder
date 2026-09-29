<?php
/**
 * Collects messages produced during a seeding run so they can be shown to the user.
 */
class Acf_Seeder_Log
{
	private $messages = array();

	public function info($text)
	{
		$this->add('info', $text);
	}

	public function warning($text)
	{
		$this->add('warning', $text);
	}

	public function add($level, $text)
	{
		$this->messages[] = array('level' => $level, 'text' => $text);
	}

	public function all()
	{
		return $this->messages;
	}
}
