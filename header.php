<?php
    include_once('connect.php'); 
    include_once('functions_global.php');

    $ip = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP) ? $_SERVER['REMOTE_ADDR'] : '';

    $GLOBALS['steamID'] = "";
    if(isset($_COOKIE['steamID']) && isset($_COOKIE['secret_key']) && $_COOKIE['secret_key'] === $GLOBALS['SECRET_KEY']) {
        $GLOBALS['steamID'] = $_COOKIE['steamID'];
        $admin = new Admin();
        $admin->UpdateAdminInfo($_COOKIE['steamID']);
        $adminName = $admin->adminUser;

        $steam = new Steam();
        $steamID64 = $steam->SteamID_To_SteamID64($GLOBALS['steamID']);
        $adminURL = "https://steamcommunity.com/profiles/$steamID64";
    } else {
        setcookie("steamID", "", 1, "/", $_SERVER['SERVER_NAME'], true, true);
        setcookie("secret_key", "", 1, "/", $_SERVER['SERVER_NAME'], true, true);
        setcookie("aid", "", 1, "/", $_SERVER['SERVER_NAME'], true, true);
    }
?>

<head>
    <title>KnockBack Bans</title>
    <link rel="icon" href="./images/favicon.ico" />
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css?family=Inter:300,300i,400,400i,500,700,700i" rel="stylesheet" referrerpolicy="origin">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mustache.js/4.2.0/mustache.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>
    <script src="js/kbans.js"></script>
</head>
<body>
    <div class="hide" style="display: none;"></div>
    <div class="kban-action-window">
        <div class="header">
            <p id="action-header-text"></p>
        </div>
        <div class="info">
            <ul class="kban_details">

            </ul>
        </div>
        <div class="info-footer">
            <button class="button button-important" onclick="CloseWindow();">Close <i class='fa-solid fa-xmark'></i></button>
            <button class="button button-success" onclick="GoHome();">Go To Kban List <i class='fa-solid fa-house'></i></button>
        </div>
    </div>
    <div class="search-modal-body">
        <div class='search-modal'>
            <div class='header'>
                <p><i class='fa-solid fa-magnifying-glass'></i> Advanced Search</p>
                <p class='search-modal-btn-close'><i class='fa-solid fa-xmark'></i></p>
            </div>
            <form method="GET">
                <input id='hideInput' type='text' style='display: none;' name='' value='true'>
                <div class='input-group' id='search-input-div'>
                    <label for='s'>Input</label>
                    <input class='input search-modal-input' type='text' name='s'>
                </div>
                <div class='input-group'>
                    <label for='m'>Method</label>
                    <select name='m' id='search-modal-select' class='select search-modal-input'>
                        <option value='1'>Player SteamID</option>
                        <option value='2'>Player Name</option>
                        <?php if(IsAdminLoggedIn() && $admin->DoesHaveFullAccess()) { ?>
                            <option value='3'>Player IP</option>
                        <?php } ?>
                        <option value='4'>Admin Name</option>
                        <option value='5'>Admin SteamID</option>
                        <?php if(str_contains($_SERVER['REQUEST_URI'], 'logs') == false) { ?>
                            <option value='6'>Map</option>
                            <option value="7">Length</option>
                        <?php } ?>
                        </select>
                    </div>
                    <div class="input-group" id="length-group" style="display: none;">
                        <div class="input-group" style="display: flex !important; justify-content: center;">
                            <select name="length[]" class="select" style="float: right; order: 1; width: 50% !important;">
                                <option value="1">= (Equal to)</option>
                                <option value="2">> (Greater)</option>
                                <option value="3">< (Smaller)</option>
                                <option value="4">>= (Greater or equal to)</option>
                                <option value="5"><= (Smaller or equal to)</option>
                            </select>
                            &nbsp;&nbsp;&nbsp;
                            <select name="length[]" id="length-select" class="select" style="order: 2; float: left; width: 80%; margin-left: 10px;">
                                <?php GetKbanLengths(false); ?>
                            </select>
                            <input style="order: 3; width: 20%; display: none; margin-left: 10px;" type="number" class="input" name="custom" id="custom-input">
                        </div>
                    </div>
                <button type='submit' class='button button-primary kban-form-button' style='width: 90%; margin-left: 5%;'><i class='fa-solid fa-magnifying-glass'></i> Search</button>
            </form>
        </div>
    </div>
<header>
    <div class="header1">
        <div class="header1-icons">
            <?php
                echo '<a id="steam_group" target="_blank" href="'. $GLOBALS['STEAM_GROUP'] .'" rel="noopener" title="Our Steam Group">'
            ?>
                <i class="fab fa-steam-symbol"></i>
            </a>
            <a id="discord" target="_blank" href="<?php echo $GLOBALS['DISCORD']; ?>" rel="noopener" data-ipstooltip="" _title="Join us on Discord">
                <i class="fab fa-discord"></i>
            </a>
        </div>
        <div class="login">
            <?php
            if(IsAdminLoggedIn()) {
            ?>
            <p>Welcome, &nbsp;<a href=<?php echo "\"$adminURL\""; ?> target="_blank"><i class='fa-solid fa-user'></i> <?php echo $adminName; ?></a>&nbsp; &nbsp;
            <a class="button button-important" href='logout.php'><i class='fas fa-sign-out-alt'></i>
                Logout</a></p>
            <?php } else { ?>
            <a class="button button-success" href='login-init.php'>Existing user? Sign In</a>
            <?php } ?>
        </div>
    </div>
    <div class="header2">
        <?php
            echo '<a class="logo" href="'. $GLOBALS['SERVER_FORUM_URL'] .'">'
        ?>
            <img src="./images/kbans.png" alt="logo">
        </a>
        <div class="search_input">
            <form method="GET" action="index.php">
                <input type='text' style='display: none;' name='all' value='true'>
                <input type="text" class="input input-header-search" placeholder="Search KBans..." name='s' required>
                <input type='text' style='display: none;' name='m' value='8'>
                <button id="search_icon"><i class="fa-solid fa-magnifying-glass"></i></button>
            </form>
        </div>
    </div>
</header>
<div class="body_content">
    <div class="header3">
        <ul>
            <li><a class="not-active" id="allKbans" href="index.php?all"><i class="fa-solid fa-house"></i> &nbspAll Kbans</a></li>
            <li><a class="not-active" id="activeKbans"  href="index.php?active"><i class="fa-solid fa-hourglass-half"></i> &nbspActive Kbans</a></li>
            <li><a class="not-active"  id="expiredKbans" href="index.php?expired"><i class="fa-solid fa-hourglass-end"></i> &nbspExpired Kbans</a></li>
            <?php
                if(IsAdminLoggedIn()) {
                    echo "<li><a id='addkban' class='not-active' id='add' href='manage.php?add'><i class='fas fa-user-times'></i> &nbspAdd Kban</a></li>"; 
                    $admin = new Admin();
                    $admin->UpdateAdminInfo($_COOKIE['steamID']);
                    if($admin->DoesHaveFullAccess()) {
            ?>
            <li><a class="not-active" id="weblogs" href="logs.php?web"><i class='far fa-hdd'></i> &nbspWeb Logs</a></li>
            <li><a class="not-active" id="srvlogs" href="logs.php?srv"><i class="fa-solid fa-server"></i> &nbspServer Logs</a></li>
            <?php } ?>
            <?php } ?>
        </ul>
    </div>

    <script>
        $(function() {
            $('#search-modal-select').on('change', ()=>{
                let val = $('#search-modal-select').val();
                $('#length-group').css('display', (val == 7)?"block":"none");
                $('#search-input-div').css('display', (val == 7)?"none":"block");
            });

            $('#length-select').on('change', ()=>{
                let val = $('#length-select').val();
                $('#custom-input').css('display', (val == -2)?"block":"none");
            });
        });
    </script>