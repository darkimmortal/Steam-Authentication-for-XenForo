<?php
/**
 *      This file is part of Steam Authentication for XenForo
 *
 *      Written by Morgan Humes <morgan@lanaddict.com>
 *      Copyright 2012 Morgan Humes
 *
 *      Steam Authentication for XenForo is free software: you can redistribute
 *      it and/or modify it under the terms of the GNU General Public License
 *      as published by the Free Software Foundation, either version 3 of the
 *      License, or (at your option) any later version.
 *
 *      Steam Authentication for XenForo is distributed in the hope that it
 *      will be useful, but WITHOUT ANY WARRANTY; without even the implied
 *      warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *      See the GNU General Public License for more details.
 *
 *      You should have received a copy of the GNU General Public License
 *      along with SteamProfile.  If not, see <http://www.gnu.org/licenses/>.
 */

class Steam_Helper_Steam {

	// cURL Variable
	private $ch = null;

	public function __construct() {
		// Setup cURL
		$this->ch = curl_init();
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->ch, CURLOPT_TIMEOUT, 4);
		if(!ini_get('safe_mode') && !ini_get('open_basedir'))
		{
			curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, TRUE);
		}
	}	

	public function getUserInfo($steam_id) {
		
		if(!ini_get('safe_mode') && !ini_get('open_basedir'))
		{
			// cURL
			curl_setopt($this->ch, CURLOPT_URL, "http://steamcommunity.com/profiles/{$steam_id}/?xml=1");
			$result = curl_exec($this->ch);
			$result = trim($result);
			$xml = simplexml_load_string($result);
		}
		else
		{
			$xml = simplexml_load_file("http://steamcommunity.com/profiles/{$steam_id}/?xml=1");
		}
		if(!empty($xml)) {
			return array(
				'username' => $xml->steamID,
				'avatar' => $xml->avatarFull,
				'icon' => $xml->avatarIcon,
				'state' => $xml->onlineState

			);
		} else {
			return array(
				'username' => "Unknown Steam Account",
				'avatar' => "Ruh Roh!"
			);
		}
	}

	public function getUserGames($steam_id) {
		$games = array();
		if(!ini_get('safe_mode') && !ini_get('open_basedir'))
		{
			// cURL
			curl_setopt($this->ch, CURLOPT_URL, "http://steamcommunity.com/profiles/$steam_id/games/?xml=1");
			curl_setopt($this->ch, CURLOPT_TIMEOUT, 5);
			ob_start();
			$result = curl_exec($this->ch);
			echo $result;
			$result = ob_get_clean();
			$result = trim($result);
			if(strpos($result, 'Error 503 Service Unavailable') !== false)
				return array();
			$xml = simplexml_load_string($result);
		}
		else
		{
			$xml = simplexml_load_file("http://steamcommunity.com/profiles/$steam_id/games/?xml=1");
		}
		if(!empty($xml)) {
			if(isset($xml->games)) {
				foreach($xml->games->children() as $game) {
					$appId = isset($game->appID) ? $game->appID : 0;
					$appName = isset($game->name) ? addslashes($game->name) : "";
					$appLogo = isset($game->logo) ? addslashes($game->logo) : "";
					$appLink = isset($game->storeLink) ? addslashes($game->storeLink) : "";
					$hours = isset($game->hoursOnRecord) ? $game->hoursOnRecord : 0;
					$hoursRecent = isset($game->hoursLast2Weeks) ? $game->hoursLast2Weeks : 0;
					if($appId == 0 || $appName == "") {
						continue;
					}
					$games["$appId"] = array (
						'name'  => $appName,
						'logo'  => $appLogo,
						'link'  => $appLink,
						'hours' => $hours,
						'hours_recent' => $hoursRecent
					);
				}
			}
		}
		return $games;
	}

	public function deleteSteamData($user_id) {
		$db = XenForo_Application::get('db');
		$db->query("DELETE FROM xf_user_steam_games WHERE user_id = $user_id");
	}

	public function getGameInfo($id) {
		$db = XenForo_Application::get('db');
		$row = $db->fetchRow("SELECT game_id, game_name, game_logo, game_link FROM xf_steam_games WHERE game_id = $id");
		$rVal = array(
			'id' => $row['game_id'],
			'name' => $row['game_name'],
			'logo' => $row['game_logo'],
			'link' => $row['game_link']
		);

		return $rVal;
	}

	public function getGameOwners($id) {
		$rVal = array();
		$db = XenForo_Application::get('db');
		$results = $db->fetchAll("SELECT u.user_id, u.username, g.game_hours, g.game_hours_recent FROM xf_user_steam_games g, xf_user u WHERE g.user_id = u.user_id AND g.game_id = $id");
		foreach($results as $row) {
			$rVal[] = array(
				'user_id' => $row['user_id'],
				'username' => $row['username'],
				'hours' => $row['game_hours'],
				'hours_recent' => $row['game_hours_recent']
			);
		}

		return $rVal;
	}

	public function getGameStatistics($limit=25) {
		$rVal = array();
		$db = XenForo_Application::get('db');
		$results = $db->fetchAll("SELECT g.game_id, g.game_name, g.game_logo, g.game_link, COUNT(*) AS count FROM xf_user_steam_games u, xf_steam_games g WHERE u.game_id = g.game_id GROUP BY u.game_id ORDER BY count DESC, g.game_id ASC LIMIT $limit;");
		foreach($results as $row) {
			$rVal[$row['game_id']] = array(
				'name' => $row['game_name'],
				'count' => $row['count'],
				'logo' => $row['game_logo'],
				'link' => $row['game_link']
			);
		}

		return $rVal;
	}

	public function getGamePlayedStatistics($limit=25) {
		$rVal = array();
		$db = XenForo_Application::get('db');
		$results = $db->fetchAll("SELECT g.game_id, g.game_name, g.game_logo, g.game_link, SUM(u.game_hours) AS hours FROM xf_user_steam_games u, xf_steam_games g WHERE u.game_id = g.game_id GROUP BY u.game_id ORDER BY hours DESC, g.game_id ASC LIMIT $limit;");
		foreach($results as $row) {
			$rVal[$row['game_id']] = array(
				'name' => $row['game_name'],
				'hours' => $row['hours'],
				'logo' => $row['game_logo'],
				'link' => $row['game_link']
			);
		}

		return $rVal;
	}

	public function getGamePlayedRecentStatistics($limit=25) {
		$rVal = array();
		$db = XenForo_Application::get('db');
		$results = $db->fetchAll("SELECT g.game_id, g.game_name, g.game_logo, g.game_link, SUM(u.game_hours_recent) AS hours FROM xf_user_steam_games u, xf_steam_games g WHERE u.game_id = g.game_id GROUP BY u.game_id ORDER BY hours DESC, g.game_id ASC LIMIT $limit;");
		foreach($results as $row) {
			$rVal[$row['game_id']] = array(
				'name' => $row['game_name'],
				'hours' => $row['hours'],
				'logo' => $row['game_logo'],
				'link' => $row['game_link']
			);
		}

		return $rVal;
	}

	public function getAvailableGames() {
		$rVal = array();
		$db = XenForo_Application::get('db');
		$results = $db->fetchAll("SELECT game_id, game_name, game_link, game_logo FROM xf_steam_games ORDER BY game_name;");
		foreach($results as $row) {
			$rVal[] = array(
				'id' => $row['game_id'],
				'name' => $row['game_name'],
				'link' => $row['game_link'],
				'logo' => $row['game_logo']
			);
		}
		return $rVal;
	}

	public function getSteamUsers() {
		$rVal = array();
		$db = XenForo_Application::get('db');
		$results = $db->fetchAll("SELECT u.user_id, u.username, p.steam_auth_id FROM xf_user u, xf_user_profile p WHERE u.user_id = p.user_id AND p.steam_auth_id > 0 ORDER BY u.username;");
		foreach($results as $row) {
			$rVal[] = array(
				'id' => Steam_Helper_Steam::convertIdToString($row['steam_auth_id']),
				'id64' => $row['steam_auth_id'],
				'username' => $row['username'],
				'user_id' => $row['user_id']
			);
		}
		return $rVal;
	}

	public static function convertIdToString($id) {
		$steamId1  = substr($id, -1) % 2;
		$steamId2a = intval(substr($id, 0, 4)) - 7656;
		$steamId2b = substr($id, 4) - 1197960265728;
		$steamId2b = $steamId2b - $steamId1;

		if($steamId2a <= 0 && $steamId2b <= 0) {
			throw new SteamCondenserException("SteamID $id is too small.");
		}

		return "STEAM_0:$steamId1:" . (($steamId2a + $steamId2b) / 2);
	}
}

?>