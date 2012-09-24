<?php
namespace Daemon\Application;

abstract class Application
{
	const NAME = '';
    private $config = array();
	private $config_desc = array();

	public function  __construct($only_help = false)
	{
		Config::create(__CLASS__, $this->config, $this->config_desc);
		if($only_help)
		{
			return;
		}
	}

	public function getName()
	{
		return static::NAME;
	}
}
