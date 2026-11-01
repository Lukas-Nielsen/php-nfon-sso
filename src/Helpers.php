<?php

declare(strict_types=1);

namespace LukasNielsen\NfonSso;

final class Helpers
{
	public const string AUTH_BASE_URL = "https://sso.cloud-cfg.com/realms/login/protocol/openid-connect";
	public const string FORM_LOGIN = "kc-form-login";
	public const string FORM_OTP = "kc-otp-login-form";

	public static function generateUnique(int $length): string
	{
		return bin2hex(random_bytes(max(1, (int) ceil($length / 2))));
	}

	public static function generateCodeVerifier(int $length = 43): string
	{
		$bytes = random_bytes(max(32, $length));
		return rtrim(strtr(base64_encode($bytes), "+/", "-_"), "=");
	}

	public static function generateCodeChallenge(string $codeVerifier): string
	{
		return rtrim(strtr(base64_encode(hash("sha256", $codeVerifier, true)), "+/", "-_"), "=");
	}

	public static function getCodeFromUrl(string $url): string
	{
		$parsed = parse_url($url);
		$fragment = $parsed["fragment"] ?? "";
		if ($fragment === "") {
			$fragment = $parsed["query"] ?? "";
		}

		if ($fragment === "") {
			return "";
		}

		parse_str($fragment, $values);
		return (string) ($values["code"] ?? "");
	}

	public static function extractFormActionFromBody(string $htmlBody, string $id): string
	{
		if (preg_match('/<form id="' . $id . '".+?action="(.+?)".+?>/is', $htmlBody, $actionMatch) !== 1) {
			throw new \RuntimeException("form with id '{$id}' not found");
		}

		return $actionMatch[1];
	}

	public static function generateTotp(string $secret): string
	{
		$secret = self::normalizeBase32Secret($secret);
		$decoded = self::base32Decode($secret);
		$counter = intdiv((int) time(), 30);
		$counterBytes = pack("N*", 0, $counter);
		$hash = hash_hmac("sha1", $counterBytes, $decoded, true);
		$offset = \ord($hash[\strlen($hash) - 1]) & 0x0F;
		$binary =
			((\ord($hash[$offset]) & 0x7F) << 24)
			| ((\ord($hash[$offset + 1]) & 0xFF) << 16)
			| ((\ord($hash[$offset + 2]) & 0xFF) << 8)
			| (\ord($hash[$offset + 3]) & 0xFF);

		$otp = $binary % 1000000;

		return str_pad((string) $otp, 6, "0", STR_PAD_LEFT);
	}

	public static function normalizeBase32Secret(string $secret): string
	{
		$secret = strtoupper(trim($secret));
		return preg_replace('/\s+/', "", $secret) ?? $secret;
	}

	public static function base32Decode(string $secret): string
	{
		$secret = self::normalizeBase32Secret($secret);
		$alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
		$binary = "";
		$bits = 0;
		$value = 0;

		for ($i = 0, $len = \strlen($secret); $i < $len; $i++) {
			$char = $secret[$i];
			$index = strpos($alphabet, $char);
			if ($index === false) {
				continue;
			}

			$value = ($value << 5) | $index;
			$bits += 5;

			if ($bits >= 8) {
				$bits -= 8;
				$binary .= \chr(($value >> $bits) & 0xFF);
			}
		}

		return $binary;
	}

	public static function resolveUrl(string $baseUrl, string $relativeOrAbsoluteUrl): string
	{
		if (preg_match('/^https?:\/\//i', $relativeOrAbsoluteUrl) === 1) {
			return $relativeOrAbsoluteUrl;
		}

		if (str_starts_with($relativeOrAbsoluteUrl, "/")) {
			$parsed = parse_url($baseUrl);
			$scheme = $parsed["scheme"] ?? "https";
			$host = $parsed["host"] ?? "";
			return "{$scheme}://{$host}{$relativeOrAbsoluteUrl}";
		}

		return rtrim($baseUrl, "/") . "/" . ltrim($relativeOrAbsoluteUrl, "/");
	}
}
