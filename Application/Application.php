<?php
namespace Daemon\Application;

abstract class Application
{
	const NAME = '';
    private $config = array();

	private $config_desc = array();

	public function  __construct($only_help = false)
	{
		if($only_help)
		{
			Config::add(__CLASS__, $this->config, $this->config_desc);
			return;
		}
	}

	public function getConfig() { return $this->config; }
	public function getConfigDesc() { return $this->config_desc; }

	public function getHelpMessage()
	{
		$help_message = "\tApplication \"".static::NAME."\" settings:\n";
		foreach($this->config_desc as $name => $desc) {
			$help_message .= "\t--$name$desc\n";
		}
		return $help_message;
	}
}
