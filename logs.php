<?php
    include('header.php');

    if(!IsAdminLoggedIn()) {
        echo "<div class='container'>
        <div class='error-box'>
        <p><i class='fa-solid fa-triangle-exclamation'></i> You do not have access to this page.</p>
        </div>
        </div>
        </div>";
        die();
    }
    
    $admin = new Admin();
    $admin->UpdateAdminInfo($_COOKIE['steamID']);
    if(!$admin->DoesHaveFullAccess()) {
        echo "<div class='container'>
        <div class='error-box'>
        <p><i class='fa-solid fa-triangle-exclamation'></i> You do not have access to this page.</p>
        </div>
        </div>
        </div>";
        die();
    }

    if(isset($_GET['page'])) {
        $currentPage = ($_GET['page'] <= 0) ? 1 : $_GET['page'];
    } else {
        $currentPage = 1;
    }

    $isWeb = false;
    $isSrv = false;
    if(isset($_GET['web'])) {
        $isWeb = true;
    }

    if(isset($_GET['srv'])) {
        $isSrv = true;
    }

    
    $resultsPerPage = 20;
    $resultsStart = (($currentPage - 1) * $resultsPerPage);

    $fromSql = " FROM ";
    $fromSql .= ($isWeb) ? "`KbRestrict_weblogs`" : "`KbRestrict_srvlogs`";
    $whereClauses = [];
    $paramTypes = "";
    $paramValues = [];

    if(isset($_GET['s'])) {
        $input = trim((string)$_GET['s']);
        $method = formatMethod(intval($_GET['m'] ?? 1));
        $allowedMethods = ["client_steamid", "client_name", "admin_name", "admin_steamid"];
        if(!in_array($method, $allowedMethods, true)) {
            $method = "client_name";
        }

        $whereClauses[] = "LOWER(`$method`) LIKE LOWER(?)";
        $paramTypes .= "s";
        $paramValues[] = "%$input%";
    }

    $whereSql = empty($whereClauses) ? "" : " WHERE " . implode(" AND ", $whereClauses);

    $countSql = "SELECT COUNT(*) AS total" . $fromSql . $whereSql;
    $countStmt = $GLOBALS['DB']->prepare($countSql);
    DbBindParams($countStmt, $paramTypes, $paramValues);
    $countStmt->execute();
    $countResult = $countStmt->get_result()->fetch_assoc();
    $resultsCount = intval($countResult['total']);
    $countStmt->close();

    $totalPages = ceil(($resultsCount / $resultsPerPage));

    if($totalPages != 0 && $currentPage > $totalPages) {
        $currentPage = $totalPages;
    }

    $num = ($isWeb) ? 4 : 5;
    $pageType = ($isWeb) ? "web" : "srv";
    echo "<script>setActive($num); setModalSearch(\"$pageType\");</script>";
?>

<!DOCTYPE html>
<html>
    <?php
    $dataSql = "SELECT *" . $fromSql . $whereSql . " ORDER BY time_stamp DESC LIMIT ?, ?";
    $dataTypes = $paramTypes . "ii";
    $dataParams = $paramValues;
    $dataParams[] = intval($resultsStart);
    $dataParams[] = intval($resultsPerPage);

    $dataStmt = $GLOBALS['DB']->prepare($dataSql);
    DbBindParams($dataStmt, $dataTypes, $dataParams);
    $dataStmt->execute();
    $query = $dataStmt->get_result();
    $results1 = $query->fetch_all(MYSQLI_ASSOC);
    $resultsRealCount = $query->num_rows;
    $dataStmt->close();

    $url = $_SERVER['REQUEST_URI'];
    if(str_contains($url, '&page')) {
        $url = substr($url, 0, strpos($url, '&page'));
    }
    ?>
    <div class="container">
        <div class="container-header">
			<h1><i class="fa-regular fa-hard-drive"></i> <?php echo ($isSrv) ? "Server" : "Web";?> Logs</h1>
            </div>
			<div class="breadcrumb">
<i class="fas fa-angle-right"></i> <a href="index.php?all">Home</a>
<i class="fas fa-angle-right"></i> <a href="logs.php?<?php echo ($isSrv) ? "srv" : "web";?>"><?php echo ($isSrv) ? "Server" : "Web";?> Logs</a>
</div>
        <div class="container-search">
            <div class="search-button search-modal-btn-open" id="search-button" data-page=<?php echo "\"$pageType\""; ?>>
                <p><strong>Advanced Search (Click)</strong></p>
            </div>
        </div>
        <div class="container-box1">
            <div class="order1">
                <i>&nbsp Total Logs: <?php echo $resultsCount; ?></i>
            </div>
            <div class="order2">
                <?php
                    $resultsEnd = $resultsStart + $resultsRealCount;
                ?>
                <p>displaying <?php echo "$resultsStart - $resultsEnd"; ?> of <?php echo $resultsCount; ?> results |
                <?php
                    $nextPage = $currentPage + 1;
                    $previousPage = $currentPage - 1;

                    if($previousPage > 0) {
                        $href = $url . "&page=$previousPage";
                        echo "<a href='$href'><i class='fa fa-arrow-circle-left'></i> previous</a> |";
                    }

                    if($nextPage > 0 && $nextPage <= $totalPages) {
                        $href = $url . "&page=$nextPage";
                        echo "&nbsp;<a href='$href'>next <i class='fa fa-arrow-circle-right'></i></a>";
                    }

                    echo "&nbsp;<select class='select_' style='width: 60px;' data-href='$url'>";
                    for($i = 1; $i <= $totalPages; $i++) {
                        if($currentPage == $i) {
                            echo "<option value='$i' selected>$i</option>";
                        } else {
                            echo "<option value='$i'>$i</option>";
                        }
                    }

                    echo "</select>";
                ?>
                </p>
            </div>
        </div>
        <div class="container-box2">
            <div class="container-box2-table">
                <div class="table">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 15%;">Date</th>
                                <th style="width: 20%;">Player</th>
                                <th style="width: 15%;">Admin</th>
                                <th style="width: 30%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                $admin = new Admin();
                                $date = new DateTime("now", new DateTimeZone(DATE_TIME_ZONE));
                                foreach($results1 as $result1) {
                                    $clientName         = $result1['client_name'];
                                    $clientSteamID      = $result1['client_steamid'];
                                    $adminSteamID       = $result1['admin_steamid'];
                                    $message            = $result1['message'];
                                    $time_stamp         = $result1['time_stamp'];
                                    
                                    $adminName = $admin->GetAdminNameFromSteamID($adminSteamID);


                                    $date->setTimestamp($time_stamp);
                                    $dateFormated = $date->format(DATE_TIME_FORMAT);

                                    echo "<tr class='row-expired'>";
                                    echo "<td>$dateFormated</td>";
                                    echo "<td>$clientName ($clientSteamID)</a></td>";
                                    echo "<td>$adminName</td>";
                                    echo "<td>$message</td>";
                                    echo "</tr>";
                                }
                            ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include('footer.php'); ?>
</div>
<script>
    $(function() {
        $('.select_').on('change', function() {
            let value = $(this).val();
            let href = $(this).attr('data-href');
            href += '&page='+value;
            window.location.replace(href);
        });
    });
</script>
