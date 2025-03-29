<?php

namespace ln\nfon_sso;

class LoginResponse
{
	public string $otpUrl;
	public bool $error;

	public function __construct(
		string $otpUrl,
		bool $error
	) {
		$this->otpUrl = $otpUrl;
		$this->error = $error;
	}
}
