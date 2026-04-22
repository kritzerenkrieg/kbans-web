<?php
    include_once('steam.php');
    class Utility {
        public static function sanitizeInput($input) {
            $replacements = array("'", '"', "\\", ";", "`", "--", "#", "=", ">", "<", "&", "%", "|", "^", "~", "(", ")");
            return str_replace($replacements, "", $input);
        }
    }

    class Admin {
        public $adminID = -1;
        public $adminGroupID = -1;
        public $adminSteamID = "";
        public $adminUser = "";

        public function IsLoginValid($steamID, $secret_key, $bInitialVerification) {
         if (empty($steamID) || empty($secret_key) || $secret_key !== $GLOBALS['SECRET_KEY']) {
            return false;
        }

        $sql = "SELECT aid FROM sb_admins WHERE authid = ?";
        $stmt = $GLOBALS['SBPP']->prepare($sql);
        $stmt->bind_param("s", $steamID);
        $stmt->execute();
        $queryResult = $stmt->get_result();
        $stmt->close();

        // Fetch the result from the query
        $row = $queryResult->fetch_assoc();
        $sbppaid = $row['aid'];

        // Compare the cookie 'aid' with the result from the query
        if (!$bInitialVerification && $sbppaid != $_COOKIE['aid']) {
            return false;
        }

        $sql = "SELECT * FROM `sb_admins` WHERE `authid`=?";
        $stmt = $GLOBALS['SBPP']->prepare($sql);
        $stmt->bind_param("s", $steamID);
        $stmt->execute();
        $queryResult = $stmt->get_result();
        $stmt->close();

        if ($queryResult->num_rows <= 0) {
            return false;
        }

        $acceptableGroups = array_merge(GID_STAFF, GID_ADMIN);
        $resultsAAA = $queryResult->fetch_all(MYSQLI_ASSOC);
        foreach ($resultsAAA as $result) {
            $gid = $result['gid'];
            if (!in_array($gid, $acceptableGroups) || $gid == -1) {
                return false;
            }
        }

        return true;
    }

        public function UpdateAdminInfo($steamID) {
            if (!isset($_COOKIE['secret_key'])) {
                return false;
            }

            $secret_key = $_COOKIE['secret_key'];
            if (!$this->IsLoginValid($steamID, $secret_key, false)) {
                return false;
            }

            $sql = "SELECT `aid`, `gid`, `authid`, `user` FROM `sb_admins` WHERE `authid`=?";
            $stmt = $GLOBALS['SBPP']->prepare($sql);
            $stmt->bind_param("s", $steamID);
            $stmt->execute();
            $queryResult = $stmt->get_result();

            if ($queryResult->num_rows <= 0) {
                $stmt->close();
                return false;
            }

            $result = $queryResult->fetch_assoc();
            $stmt->close();

            $this->adminID = $result['aid'];
            $this->adminGroupID = $result['gid'];
            $this->adminSteamID = $result['authid'];
            $this->adminUser = $result['user'];

            return true;
        }

        public function GetAdminNameFromSteamID($steamID) {
            if (!str_contains($steamID, "STEAM")) {
                return "CONSOLE";
            }

            $sql = "SELECT * FROM `sb_admins` WHERE `authid`=?";
            $stmt = $GLOBALS['SBPP']->prepare($sql);
            $stmt->bind_param("s", $steamID);
            $stmt->execute();
            $queryResult = $stmt->get_result();
            $stmt->close();

            $results = $queryResult->fetch_all(MYSQLI_ASSOC);
            foreach ($results as $result) {
                return $result['user'];
            }

            return "<i>Admin Deleted</i>";
    }

        public function DoesHaveFullAccess() {
            if (!isset($_COOKIE['steamID'])) {
                return false;
            }

            if (in_array($this->adminGroupID, GID_STAFF)) {
                return true;
            }

            return false;
        }

    }

    class Kban {
        public function UnbanByID($id, $reasonA) {
            if (empty($reasonA)) {
                $reasonA = "No Reason";
            }

            $reason = Utility::sanitizeInput($reasonA);

            if (!isset($_COOKIE['steamID'])) {
                return false; // Should never happen but better be safe
            }

            $admin = new Admin();
            if (!$admin->UpdateAdminInfo($_COOKIE['steamID'])) {
                return false;
            }

            $adminName = $admin->adminUser;
            $adminSteamID = $admin->adminSteamID;

            $kban = new Kban();
            $resultsB = $kban->getKbanInfoFromID($id);
            $length = $resultsB['length'];

            $time_removed = time();

            $sql = "UPDATE `KbRestrict_CurrentBans` SET `is_expired`=1, `is_removed`=1, 
                    `admin_name_removed`=?, `admin_steamid_removed`=?, `reason_removed`=?, 
                    `time_stamp_removed`=? WHERE `id`=?";
            $stmt = $GLOBALS['DB']->prepare($sql);
            $stmt->bind_param("sssii", $adminName, $adminSteamID, $reason, $time_removed, $id);
            $stmt->execute();
            $stmt->close();


            $stmt = $GLOBALS['DB']->prepare($sql);
            $stmt->bind_param("ssssi", $adminName, $adminSteamID, $reason, $time_removed, $id);
            $stmt->execute();
            $stmt->close();

            $results = $this->getKbanInfoFromID($id);
            $playerName = $results['client_name'];
            $playerSteamID = $results['client_steamid'];
            $message = "Kban Removed (was $length minutes. Reason: $reason)";
            $time_stamp_start = time();

            GetRowInfo($id);

            $sql = "INSERT INTO `KbRestrict_weblogs` (`client_name`, `client_steamid`, `admin_name`, `admin_steamid`, `message`, `time_stamp`) ";
            $sql .= "VALUES (?, ?, ?, ?, ?, ?)";

            $stmt = $GLOBALS['DB']->prepare($sql);
            $stmt->bind_param("sssssi", $playerName, $playerSteamID, $adminName, $adminSteamID, $message, $time_stamp_start);
            $stmt->execute();
            $stmt->close();

            //echo "<script>showKbanWindowInfo(2, \"$playerName\", \"$playerSteamID\", \"$reason\", \"$length minutes\");</script>";
            echo "<script>showKbanWindowInfo(2, \"$playerName\", \"$playerSteamID\", \"$reason\", \"$length minutes\", $id);</script>";

            return true;
        }

        public function RemoveKbanFromDB($id) {
            $admin = new Admin();
            $adminSteamID = isset($_COOKIE['steamID']) ? $_COOKIE['steamID'] : "";
            $admin->UpdateAdminInfo($adminSteamID);
            if (!IsAdminLoggedIn() || !$admin->DoesHaveFullAccess()) {
                return false;
            }

            $resultsC = $this->getKbanInfoFromID($id);
            $playerName = $resultsC['client_name'];
            $playerSteamID = $resultsC['client_steamid'];
            $length = $resultsC['length'];
            $reason = $resultsC['reason'];
            $isExpired = ($resultsC['is_expired'] == 1);
            $isRemoved = ($resultsC['is_removed'] == 1);
            $time_stamp_end = $resultsC['time_stamp_end'];
            $status = "Active";
            if ($isExpired && !$isRemoved || ($time_stamp_end >= 1 && time() > $time_stamp_end)) {
                $status = "Expired";
            }
            if ($isRemoved) {
                $status = "Removed";
            }

            $message = "KBan Deleted (was $length minutes. Issued for: $reason. Kban was $status)";
			
			$admin->UpdateAdminInfo($_COOKIE['steamID']);
			$adminName = $admin->adminUser;
			$adminSteamID = $admin->adminSteamID;
			$time_stamp = time();

            // Use prepared statements
            $stmt = $GLOBALS['DB']->prepare("DELETE FROM `KbRestrict_CurrentBans` WHERE `id` = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();

            $stmt = $GLOBALS['DB']->prepare("INSERT INTO `KbRestrict_weblogs` (`client_name`, `client_steamid`, `admin_name`, `admin_steamid`, `message`, `time_stamp`) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('sssssi', $playerName, $playerSteamID, $adminName, $adminSteamID, $message, $time_stamp);
            $stmt->execute();
            $stmt->close();

            echo "<script>showKbanWindowInfo(3, \"$playerName\", \"$playerSteamID\", \"$reason\", \"$length minutes\", $id);</script>";
            //echo "<script>window.location.replace('index.php?all');</script>";
        }

        public function formatLength($seconds) {
            /* if less than one minute */
            if ($seconds < 60) {
                return "$seconds Seconds";
            }

            /* if one minute or more */
            if ($seconds >= 60 && $seconds < 3600) {
                $minutes = ($seconds / 60);
                $minutesPhrase = ($minutes > 1) ? "Minutes" : "Minute";
                return "$minutes $minutesPhrase";
            }

            /* If hour or more*/
            if ($seconds >= 3600 && $seconds < 86400) {
                $hours = intval(($seconds / 3600));
                $minutes = intval((($seconds / 60) % 60));
                $hoursPhrase = ($hours > 1) ? "Hours" : "Hour";
                $minutesPhrase = ($minutes > 1) ? "Minutes" : "Minute";

                if ($minutes <= 0) {
                    return "$hours $hoursPhrase";
                }
                return "$hours $hoursPhrase, $minutes $minutesPhrase";
            }

            /* If day or more */
            if ($seconds >= 86400 && $seconds < 604800) {
                $days = intval(($seconds / 86400));
                $hours = intval((($seconds / 3600) % 24));
                $daysPhrase = ($days > 1) ? "Days" : "Day";
                $hoursPhrase = ($hours > 1) ? "Hours" : "Hour";

                if ($hours <= 0) {
                    return "$days $daysPhrase";
                }
                return "$days $daysPhrase, $hours $hoursPhrase";
            }

            /* if week or more */
            if ($seconds >= 604800 && $seconds < 2592000) {
                $weeks = intval(($seconds / 604800));
                $days = intval((($seconds / 86400) % 7));
                $weeksPhrase = ($weeks > 1) ? "Weeks" : "Week";
                $daysPhrase = ($days > 1) ? "Days" : "Day";
                
                if ($days <= 0) {
                    return "$weeks $weeksPhrase";
                }
                return "$weeks $weeksPhrase, $days $daysPhrase";
            }

            /* if month or more */
            if ($seconds >= 2592000) {
                $months = intval(($seconds / 2592000));
                $days = intval((($seconds / 86400) % 30));
                $monthsPhrase = ($months > 1) ? "Months" : "Month";
                $daysPhrase = ($days > 1) ? "Days" : "Day";

                if ($days <= 0) {
                    return "$months $monthsPhrase";
                }
                return "$months $monthsPhrase, $days $daysPhrase";
            }
        }

        public function getKbanInfoFromID($id) {
            $sql = "SELECT * FROM `KbRestrict_CurrentBans` WHERE `id`=?";
            $stmt = $GLOBALS['DB']->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            foreach($results as $result) {
                return $result;
            }
        }

        public function GetKbansNumber($steamID, $IP = "") {
            $search = (empty($steamID)) ? $IP : $steamID;
            $searchMethod = (empty($steamID)) ? "client_ip" : "client_steamid";

            $sql = "SELECT COUNT(*) AS total FROM `KbRestrict_CurrentBans` WHERE `$searchMethod`=?";
            $stmt = $GLOBALS['DB']->prepare($sql);
            $stmt->bind_param("s", $search);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $rows = intval($result['total']);
            $stmt->close();
            return $rows;
        }
        
        public function GetRealKbansNumber($steamID, $IP = "") {
            $search = (empty($steamID)) ? $IP : $steamID;
            $searchMethod = (empty($steamID)) ? "client_ip" : "client_steamid";

            $sql = "SELECT COUNT(*) AS total FROM `KbRestrict_CurrentBans` WHERE `$searchMethod`=? AND `is_removed`=0";
            $stmt = $GLOBALS['DB']->prepare($sql);
            $stmt->bind_param("s", $search);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $rows = intval($result['total']);
            $stmt->close();
            return $rows;
        }

        public function addNewKban($playerNameA, $playerSteamID, $length, $reasonA) {
            $admin = new Admin();
            $admin->UpdateAdminInfo($_COOKIE['steamID']);
            $adminName = $admin->adminUser;
            $adminSteamID = $admin->adminSteamID;

            $playerName = Utility::sanitizeInput($playerNameA);
            $reason = Utility::sanitizeInput($reasonA);
            $lengthInMinutes = ($length / 60);
            $time_stamp_start = time();
            $time_stamp_end = 0;
            if($lengthInMinutes !== 0) {
                $time_stamp_end = ($length < 0) ? -1 : (time() + $length);
            }

            if ($length <= -1) {
                $lengthInMinutes = 30;
            } else if ($length == 0) {
                $lengthInMinutes = 0;
            }

            if ($this->IsSteamIDAlreadyBanned($playerSteamID)) {
                die();
            }

            // Use prepared statements for the insertion into `KbRestrict_CurrentBans`
            $sql = "INSERT INTO `KbRestrict_CurrentBans` 
                    (`client_name`, `client_steamid`, `client_ip`, `admin_name`, `admin_steamid`, `reason`, 
                    `map`, `length`, `time_stamp_start`, `time_stamp_end`, `is_expired`, `is_removed`, 
                    `admin_name_removed`, `admin_steamid_removed`, `time_stamp_removed`, `reason_removed`) 
                    VALUES (?, ?, 'Unknown', ?, ?, ?, 'Web Ban', ?, ?, ?, 0, 0, 'null', 'null', '0', 'null')";

            $stmt = $GLOBALS['DB']->prepare($sql);
            $stmt->bind_param("sssssiii", $playerName, $playerSteamID, $adminName, $adminSteamID, $reason, $lengthInMinutes, $time_stamp_start, $time_stamp_end);
            $stmt->execute();
            $stmt->close();

            $message = "Kban Added (";
            if ($lengthInMinutes >= 1) {
                $message .= "$lengthInMinutes Minutes";
            } else if ($lengthInMinutes == 0) {
                $message .= "Permanent";
            } else {
                $message .= "Session";
            }
            $message .= ")";

            // Use prepared statements for the insertion into `KbRestrict_weblogs`
            $sql = "INSERT INTO `KbRestrict_weblogs` 
                    (`client_name`, `client_steamid`, `admin_name`, `admin_steamid`, `message`, `time_stamp`) 
                    VALUES (?, ?, ?, ?, ?, ?)";

            $stmt = $GLOBALS['DB']->prepare($sql);
            $stmt->bind_param("sssssi", $playerName, $playerSteamID, $adminName, $adminSteamID, $message, $time_stamp_start);
            $stmt->execute();
            $stmt->close();

            echo "<script>showKbanWindowInfo(0, \"$playerName\", \"$playerSteamID\", \"$reason\", \"$lengthInMinutes minutes\");</script>";
            //echo "<script>window.location.replace('index.php?all');</script>";
        }

        public function EditKban($id, $playerNameA, $playerSteamID, $length, $reasonA) {
            $admin = new Admin();
            $admin->UpdateAdminInfo($_COOKIE['steamID']);
            $adminName = $admin->adminUser;
            $adminSteamID = $admin->adminSteamID;

            $playerName = Utility::sanitizeInput($playerNameA);
            $reason = Utility::sanitizeInput($reasonA);
            $lengthInMinutes = ($length / 60);
 
            $info = $this->getKbanInfoFromID($id);

            $time_stamp_end = ($info['time_stamp_start'] + $length);
            if ($length <= -1) {
                $lengthInMinutes = -1;
                $time_stamp_end = -1;
            } else if ($length == 0) {
                $lengthInMinutes = 0;
                $time_stamp_end = 0;
            }

            $time = time();
            if ($length >= 1) {
                if ($time_stamp_end < $time) {
                    $this->UnbanByID($id, "NO REASON");
                    echo "<script>window.location.replace('index.php?all');</script>";
                    die();
                }
            }

            // Use prepared statements for the UPDATE query
            $sql = "UPDATE `KbRestrict_CurrentBans` SET `client_name`=?, `client_steamid`=?, `reason`=?, `length`=?, `time_stamp_end`=? WHERE `id`=?";
            $stmt = $GLOBALS['DB']->prepare($sql);
            $stmt->bind_param("sssiii", $playerName, $playerSteamID, $reason, $lengthInMinutes, $time_stamp_end, $id);
            $stmt->execute();
            $stmt->close();

            $message = "Kban Edited (";
            if ($playerName != $info['client_name']) {
                $message .= " New Name: $playerName";
            }
            if ($playerSteamID != $info['client_steamid']) {
                $message .= " New SteamID: $playerSteamID";
            }
            if ($reason != $info['reason']) {
                $message .= " New Reason: $reason"; 
            }

            if ($lengthInMinutes != $info['length']) {
                if ($lengthInMinutes >= 1) {
                    $message .= " New Length: $lengthInMinutes Minutes";
                } else if ($lengthInMinutes == 0) {
                    $message .= " New Length: Permanent";
                } else {
                    $message .= " New Length: Session";
                }
            }

            $message .= " )";

            $playerNameOld = $info['client_name'];
            $playerSteamIDOld = $info['client_steamid'];

            // Use prepared statements for the INSERT query
            $sql = "INSERT INTO `KbRestrict_weblogs` (`client_name`, `client_steamid`, `admin_name`, `admin_steamid`, `message`, `time_stamp`) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $GLOBALS['DB']->prepare($sql);
            $stmt->bind_param("sssssi", $playerNameOld, $playerSteamIDOld, $adminName, $adminSteamID, $message, $time);
            $stmt->execute();
            $stmt->close();

            echo "<script>showKbanWindowInfo(1, \"$playerName\", \"$playerSteamID\", \"$reason\", \"$lengthInMinutes minutes\");</script>";
            //echo "<script>window.location.replace('index.php?all');</script>";
        }

        public function IsSteamIDAlreadyBanned($steamID) {
            $sql = "SELECT * FROM `KbRestrict_CurrentBans` WHERE `client_steamid`=?";
            $stmt = $GLOBALS['DB']->prepare($sql);
            $stmt->bind_param("s", $steamID);
            $stmt->execute();
            $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            foreach ($results as $result) {
                $isActive = ($result['is_expired'] == 0 && $result['is_removed'] == 0);
                $isPermanent = ($result['time_stamp_end'] <= 0);
                $isExpired = !$isPermanent && (time() >= $result['time_stamp_end']);

                if ($isActive && ($isPermanent || !$isExpired)) {
                    return true; // Early return when a matching active ban is found
                }
            }

            return false;
        }

    }

    function IsAdminLoggedIn() {
        if(!isset($_COOKIE['steamID']) || !isset($_COOKIE['secret_key'])) {
            return false;
        }

        $steamID = $_COOKIE['steamID'];
        $secret_key = $_COOKIE['secret_key'];

        $admin = new Admin();
        if ($admin->IsLoginValid($steamID, $secret_key, false)) {
            return true;
        }

        return false;
    }

    function formatMethod(int $method) {
        $methods = ["client_steamid", "client_name", "client_ip", "admin_name", "admin_steamid", "map", "length", "all_search"];
        $index = $method - 1;
        return $methods[$index] ?? "client_steamid";
    }

    function DbBindParams(mysqli_stmt $stmt, string $types, array &$params) {
        if ($types === '' || empty($params)) {
            return;
        }

        $bindArgs = [$types];
        foreach ($params as $key => &$value) {
            $bindArgs[] = &$value;
        }

        call_user_func_array([$stmt, 'bind_param'], $bindArgs);
    }

    function GetRowInfo($id, $result2 = null) {
        $admin = new Admin();
        $kban = new Kban();
        
        if ($id != 0) {
            $result2 = $kban->getKbanInfoFromID($id);
        } else {
            $id = $result2['id'];
        }

        $clientName         = $result2['client_name'];
        $clientSteamID      = $result2['client_steamid'];
        $clientIP           = $result2['client_ip'];
        $adminSteamID       = $result2['admin_steamid'];
        $reason             = $result2['reason'];
        $map                = $result2['map'];
        $time_stamp_start   = $result2['time_stamp_start'];
        $time_stamp_end     = $result2['time_stamp_end'];
        $isExpired          = ($result2['is_expired'] == 1);
        $isRemoved          = ($result2['is_removed'] == 1); 
        $adminNameRemoved   = $result2['admin_name_removed'];
        $time_stamp_removed = $result2['time_stamp_removed'];
        $reason_removed     = $result2['reason_removed'];

        $adminName = $admin->GetAdminNameFromSteamID($adminSteamID);

        $length = $kban->formatLength(($time_stamp_end - $time_stamp_start));
        $expiresOn = "";
        if ($time_stamp_end == 0) {
            $length = "Permanent";
            $expiresOn = "Never";
        } else if ($time_stamp_end <= -1) {
            $length = "Session";
            $expiresOn = "Temporary";
        }

        $status = "Kban Active";
        if ($isExpired && !$isRemoved || ($time_stamp_end >= 1 && time() > $time_stamp_end)) {
            $status = "Kban Expired";
        }

        if ($isRemoved) {
            $status = "Kban Removed";
        }

        echo "<div class='kban-buttons'>";
        
         $searchMethod = 1;
        if (IsAdminLoggedIn()) {
            if ($clientSteamID == "NO STEAMID") {
                $href = "ViewPlayerHistory(\"$clientIP\", 3)";
                $searchMethod = 3;
            } else {
                $href = "ViewPlayerHistory(\"$clientSteamID\", 1);";
                $searchMethod = 1;
            }
        } else {
            if ($clientSteamID != "NO STEAMID") {
                $href = "ViewPlayerHistory(\"$clientSteamID\", 1);";
                $searchMethod = 1;
            } else {
                $searchMethod = 3;
            }
        }

        if (IsAdminLoggedIn() || ($searchMethod == 1 && !IsAdminLoggedIn())) {
            echo "<button onclick='$href' class='button button-light' title='View History'><i class='fa-solid fa-clock-rotate-left'></i>&nbspView History</button>";
        }

        if (IsAdminLoggedIn()) {
            $admin->UpdateAdminInfo($_COOKIE['steamID']);

            if (($time_stamp_end < 1 && $isRemoved == false && $isExpired == false) || ($time_stamp_end >= 1 && time() < $time_stamp_end && $isRemoved == false && $isExpired == false)) {
            
                if ($admin->DoesHaveFullAccess() || $adminSteamID == $admin->adminSteamID) {
                    $editFunction = "EditFromID(\"$id\")";
                    echo "<button class='button button-primary' title='Edit' onclick='$editFunction'><i class='fa-regular fa-pen-to-square'></i>&nbspEdit Details</button>";
                    $unbanFunction = "ConfirmUnban($id, \"$clientName\", \"$clientSteamID\");";
                    echo "<button class='button button-important' title='Unban' onclick='$unbanFunction'><i class='fas fa-undo fa-lg'></i>&nbspUnban</button>";
                }
            } else {
                if ($clientSteamID != "NO STEAMID" && !$kban->IsSteamIDAlreadyBanned($clientSteamID)) {
                    $reBanFunction = "RebanFromID(\"$id\");";
                    echo "<button class='button button-important' title='Reban' onclick='$reBanFunction'><i class='fas fa-redo fa-lg'></i>&nbspReban</button>";
                }
            }
        }

        if ($admin->DoesHaveFullAccess()) {
            $deleteFunction = "RemoveKbanFromDBCheck($id);";
            echo "<button class='button button-important' title='Delete' onclick='$deleteFunction'><i class='fa-solid fa-trash'></i>&nbspDelete KBan</button>";
        }

        if (!IsAdminLoggedIn()) {
            $href = "Login();";
            echo "<button onclick='$href' class='button button-success' title='Sign in'>Admin? Sign in</button>";
        }

        echo "</div>";

        $date = new DateTime("now", new DateTimeZone(DATE_TIME_ZONE));
        $date->setTimestamp($time_stamp_start);
        $startDate  = $date->format(DATE_TIME_FORMAT);

        $date->setTimestamp($time_stamp_end);
        $endDate    = ($expiresOn !== "") ? $expiresOn : $date->format(DATE_TIME_FORMAT);

        echo "<ul class='kban_details'>";

        echo "<li>";
        echo "<span><i class='fas fa-user'></i> Player</span>";
        echo "<span>$clientName</span>";
        echo "</li>";

        $steam = new Steam();
        $clientSteamID3 = $steam->SteamID_To_SteamID3($clientSteamID);
        $clientSteamID64 = $steam->SteamID_To_SteamID64($clientSteamID);
        echo "<li>";
        echo "<span><i class='fab fa-steam-symbol'></i> Steam ID</span>";
        echo "<span>$clientSteamID</span>";
        echo "</li>";

        echo "<li>";
        echo "<span><i class='fab fa-steam-symbol'></i> Steam3 ID</span>";
        echo "<span><a href='https://steamcommunity.com/profiles/$clientSteamID64' target='_blank'>$clientSteamID3</a></span>";
        echo "</li>";

        echo "<li>";
        echo "<span><i class='fab fa-steam-symbol'></i> Steam Community</span>";
        echo "<span><a href='https://steamcommunity.com/profiles/$clientSteamID64' target='_blank'>$clientSteamID64</a></span>";
        echo "</li>";

        if (IsAdminLoggedIn() && $admin->DoesHaveFullAccess()) {
            echo "<li>";
            echo "<span><i class='fas fa-network-wired'></i> IP address</span>";
            if ($clientIP == "Unknown" || $clientIP == "unknown") {
                echo "<span>$clientIP</span>";
            } else {
                echo "<span><a href='https://www.infobyip.com/ip-$clientIP.html' target='_blank'>$clientIP</a></span>";
            }
            echo "</li>";
        }

        echo "<li>";
        echo "<span><i class='fas fa-play'></i> Invoked on</span>";
        echo "<span>$startDate</span>";
        echo "</li>";

        echo "<li>";
        echo "<span><i class='fas fa-hourglass-half'></i> KBan Duration</span>";
        echo "<span>$length</span>";
        echo "</li>";

        echo "<li>";
        echo "<span><i class='fas fa-clock'></i> Expires on</span>";
        echo "<span>$endDate</span>";
        echo "</li>";

        echo "<li>";
        echo "<span><i class='fas fa-question'></i> Reason</span>";
        echo "<span>$reason</span>";
        echo "</li>";

        echo "<li>";
        echo "<span><i class='fas fa-ban'></i> Banned by Admin</span>";
        echo "<span>$adminName</span>";
        echo "</li>";

        echo "<li>";
        echo "<span><i class='fa-solid fa-circle-exclamation'></i> KBan Status</span>";
        echo "<span>$status</span>";
        echo "</li>";

        if ($isRemoved) {
            $date->setTimestamp($time_stamp_removed);
            $removedDate = $date->format(DATE_TIME_FORMAT);

            echo "<li>";
            echo "<span><i class='fas fa-play'></i> Unbanned on</span>";
            echo "<span>$removedDate</span>";
            echo "</li>";

            echo "<li>";
            echo "<span><i class='fas fa-ban'></i> Unbanned By Admin</span>";
            echo "<span>$adminNameRemoved</span>";
            echo "</li>";

            echo "<li>";
            echo "<span><i class='fas fa-question'></i> Unban Reason</span>";
            echo "<span>$reason_removed</span>";
            echo "</li>";
        }

        echo "<li>";
        echo "<span><i class='fa-solid fa-gamepad'></i> Map</span>";
        if ($map != "Web Ban" && $map != "From Web") {
            $fastdl = $GLOBALS['SERVER_FASTDL'];
            echo "<span><a href='$fastdl/maps/$map.bsp.bz2'>$map</a></span>";
        } else {
            echo "<span>$map</span>";
        }
        echo "</li>";

        echo "</ul>";
        
    }

    function GetKbanLengths($addTag = true) {
        if($addTag == true) {
            echo "<select id='add-select' class='select add-select'>";
        }
        echo "<optgroup label='Minutes'>";
        for($second = 1; $second < 3600; $second++) {
            /* we want 10, 30, and 50 minutes */
            if ($second == (10*60) || $second == (30*60) || $second == (50*60)) {
                $minutes = ($second / 60);
                $minutesToSeconds = ($minutes * 60);
                if ($second == $minutesToSeconds) { //
                    echo "<option value='$second'>$minutes Minutes</option>";
                }
            }
        }

        echo "</optgroup>";
        echo "<optgroup label='Hours'>";
        for($second = 1; $second < (3600 * 24); $second++) {
            /* we want 1, 2, 4, 8, and 16 hours */
            if ($second == (1*60*60) || $second == (2*60*60) || $second == (4*60*60) ||
                $second == (8*60*60) || $second == (16*60*60)) {
                $hours = ($second / (60 * 60));
                $hoursToSeconds = ($hours * (60 * 60));
                if ($second == $hoursToSeconds) { //
                    echo "<option value='$second'>$hours Hours</option>";
                }
            }
        }

        echo "</optgroup>";
        echo "<optgroup label='Days'>";

        for($second = 1; $second <= (3600 * 24 * 3); $second++) {
            /* we want 1, 2, 3 days */
            if ($second == (1*60*60*24) || $second == (2*60*60*24) || $second == (3*60*60*24)) {
                $days = ($second / (60 * 60 * 24));
                $daysToSeconds = ($days * (60 * 60 * 24));
                if ($second == $daysToSeconds) { //
                    echo "<option value='$second'>$days Days</option>";
                }
            }
        }

        echo "</optgroup>";
        echo "<optgroup label='Weeks'>";
        
        for($second = 1; $second <= (3600 * 24 * 7 * 3); $second++) {
            /* we want 1, 2, 3 weeks */
            if ($second == (1*60*60*24*7) || $second == (2*60*60*24*7) || $second == (3*60*60*24*7)) {
                $weeks = ($second / (60 * 60 * 24 * 7));
                $weeksToSeconds = ($weeks * (60 * 60 * 24 * 7));
                if ($second == $weeksToSeconds) { //
                    echo "<option value='$second'>$weeks Weeks</option>";
                }
            }
        }

        echo "</optgroup>";
        echo "<optgroup label='Months'>";
        for($second = 1; $second <= (3600 * 24 * 30 * 3); $second++) {
            /* we want 1, 2, 3 months */
            if ($second == (1*60*60*24*30) || $second == (2*60*60*24*30) || $second == (3*60*60*24*30)) {
                $months = ($second / (60 * 60 * 24 * 30));
                $monthsToSeconds = ($months * (60 * 60 * 24 * 30));
                if ($second == $monthsToSeconds) { //
                    echo "<option value='$second'>$months Months</option>";
                }
            }
        }
        
        echo "</optgroup>";
        $admin = new Admin();
        $admin->UpdateAdminInfo($GLOBALS['steamID']);
        if ($addTag == false || $admin->DoesHaveFullAccess()) {
            echo "<optgroup label='Others'>";
            echo "<option value='0'>Permanent</option>";
            if($addTag == false) {
                echo "<option value='-1'>Session</option>";
                echo "<option value='-2'>Custom (in Minutes)</option>";
            }

            echo "</optgroup>";
        }

        if($addTag == true) {
            echo "</select>";
        }
    }

    function GetKbanLengthTypes() {
        echo "<select id='edit-select' class='select edit-select'>";
        echo "<option value='2' selected>Minutes</option>";
        echo "<option value='3'>Hours</option>";
        echo "<option value='4'>Days</option>";
        echo "<option value='5'>Weeks</option>";
        echo "<option value='6'>Months</option>";
        echo "</select>";
    }
?>
