<?php

namespace ln\nfon_sso;

class Generic extends \Exception
{
	public function __construct(string $message)
	{
		parent::__construct($message);
	}
}
