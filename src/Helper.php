<?php

namespace ln\nfon_sso;

class Helper
{

	public function generateUnique($length = 32)
	{
		return bin2hex(random_bytes($length / 2));
	}

	public function generateCodeVerifier($length = 43)
	{
		$randomBytes = random_bytes($length);
		$base64url = rtrim(strtr(base64_encode($randomBytes), '+/', '-_'), '=');

		return $base64url;
	}

	public function generateCodeChallenge($code_verifier)
	{
		$hash = hash('sha256', $code_verifier, true);
		$code_challenge = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
		return $code_challenge;
	}

	public function getCodeFromUrl($url)
	{
		if (strpos($url, '#') !== false) {
			[, $fragment] = explode('#', $url, 2);
			parse_str($fragment, $queryParams);
			return $queryParams['code'] ?? "";
		}
		if (strpos($url, '?') !== false) {
			[, $fragment] = explode('?', $url, 2);
			parse_str($fragment, $queryParams);
			return $queryParams['code'] ?? "";
		}
		return "";
	}
}
