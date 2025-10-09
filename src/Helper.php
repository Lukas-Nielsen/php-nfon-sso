<?php

namespace ln\nfon_sso;

class Helper
{

	public function generateUnique(int $length = 32): string
	{
		return bin2hex(random_bytes($length / 2));
	}

	public function generateCodeVerifier(int $length = 43): string
	{
		$randomBytes = random_bytes($length);
		$base64url = rtrim(strtr(base64_encode($randomBytes), '+/', '-_'), '=');

		return $base64url;
	}

	public function generateCodeChallenge(string $codeVerifier): string
	{
		$hash = hash('sha256', $codeVerifier, true);
		$codeChallenge = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
		return $codeChallenge;
	}

	public function getCodeFromUrl(string $url): string
	{
		if (strpos($url, '#') !== false) {
			[, $fragment] = explode('#', $url, 2);
		} elseif (strpos($url, '?') !== false) {
			[, $fragment] = explode('?', $url, 2);
		} else {
			return "";
		}

		parse_str($fragment, $queryParams);

		return $queryParams['code'] ?? "";
	}
}
