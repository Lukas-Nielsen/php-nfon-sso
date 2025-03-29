<?php

namespace ln\nfon_sso;

class User
{
	public string $username;
	public string $password;
	public string $mfa = "";
	public function __construct(string $username, string $password, string $mfa = "")
	{
		$this->password = $password;
		$this->username = $username;
		$this->mfa = $mfa;
	}
}
