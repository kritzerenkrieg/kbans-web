<?php

class Steam
{
	public static function ExtractSteamIDFromText($input)
	{
		$steamInput = trim((string)$input);
		#these patterns will match the most common SteamID formats. Validation is done in the convertSteamID function.
		if ($steamInput === '') {
			return '';
		}

		$patterns = [
			'/STEAM_[0-5]:[0-1]:\d+/i',
			'/\[U:1:\d+\]/i',
			'/U:1:\d+/i',
			'/7656119\d{10}/'
		];

		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $steamInput, $match)) {
				return strtoupper($match[0]);
			}
		}

		return $steamInput;
	}
	private static function resolveInputID($steamid)
	{
		switch (true) {
			case preg_match('/^STEAM_[0-5]:[0-1]:\d+$/i', $steamid):
				return 'Steam2';
			case preg_match('/^\[U:1:\d+\]$/i', $steamid):
				return 'Steam3';
			case preg_match('/^U:1:\d+$/i', $steamid):
				return 'Steam3';
			case preg_match('/^7656119\d{10}$/', $steamid):
				return 'Steam64';
			default:
				throw new Exception("Invalid SteamID input!");
		}
	}

	public static function convertSteamID($steamid)
	{
		try {
			$type = self::resolveInputID($steamid);
			switch ($type) {
				case 'Steam2':
					return $steamid;
				case 'Steam3':
					$converted = self::SteamID3_To_SteamID($steamid);
					return $converted;
				case 'Steam64':
					$converted = self::SteamID64_To_SteamID($steamid);
					return $converted;
				default:
					throw new Exception("Invalid SteamID input!");
			}
		} catch (Exception $e) {
			error_log("Error during conversion: " . $e->getMessage());
			throw $e;
		}
	}

	public static function SteamID_To_SteamID3($steamid32) 
	{
		if (preg_match('/^STEAM_[01]\:[01]\:(.*)$/', $steamid32, $res)) {
			$st = '[U:1:';
			$st .= $res[1] * 2 + intval(substr($steamid32, 8, 1));
			$st .= ']';
			return $st;
		}

		return false;
	}

	public static function SteamID3_To_SteamID($steamid3)
	{
		if (preg_match("/U:1:(\d+)/", $steamid3, $matches)) {
			$steam3 = intval($matches[1]);
			$A = $steam3 % 2;
			$B = intval($steam3 / 2);
			return "STEAM_0:" . $A . ":" . $B;
		}
		return false;
	}

	public static function SteamID_To_SteamID64($steamid32)
	{
		if (preg_match('/^STEAM_0\:1|0\:(.*)$/', $steamid32, $res)) {
			list(, $m1, $m2) = explode(':', $steamid32, 3);
			list($steam_cid, ) = explode('.', bcadd((((int)$m2 * 2) + $m1), '76561197960265728'), 2);
			return $steam_cid;
		}

		return false;
	}

	public static function SteamID64_To_SteamID($steamid64)
	{
		$pattern = "/^(7656119)([0-9]{10})$/";
		if (preg_match($pattern, $steamid64, $match)) {
			$const1 = 7960265728;
			$const2 = "STEAM_0:";
			$steam32 = '';
			if ($const1 <= $match[2]) {
				$a = ($match[2] - $const1) % 2;
				$b = ($match[2] - $const1 - $a) / 2;
				$steam32 = $const2 . $a . ':' . $b;
			}

			return $steam32;
		}

		return false;
	}

	public static function GetSteamProfile($steamid64, $attribute)
	{
		if (preg_match('/^\d+$/', $steamid64)) {
			$xml = simplexml_load_file("https://steamcommunity.com/profiles/".$steamid64."?xml=1");
		} else {
			$xml = simplexml_load_file("https://steamcommunity.com/id/".$steamid64."?xml=1");
		}

		$SteamProfileAttribute = array();

		$attributes = explode(', ', $attribute);

		foreach ($attributes as $key => $attr_key) {
			$SteamProfileAttribute += [$attr_key => $xml->$attr_key];
		}

		return $SteamProfileAttribute;
	}
	
	function verifyAndConvertSteamID($steamid) {
		try {
			$steamID2 = Steam::convertSteamID($steamid);
			return ['success' => true, 'steamID2' => $steamID2];
		} catch (Exception $e) {
			return ['success' => false, 'error' => $e->getMessage()];
		}
	}

}
?>