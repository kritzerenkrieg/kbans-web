function showKbanInfo(button) {
    let id = $(button).attr('id-data');
    let diva = '#diva-' + id;
    $(diva + '-tr').slideToggle();
    if ($(diva).attr('is_slided') == 0) {
        $(diva).attr('is_slided', 1);
        $(diva).slideDown();
        $(diva).css('display', 'flex');
    } else {
        $(diva).attr('is_slided', 0);
        $(diva).slideUp();
    }
}

function GoTop() {
    if (document.documentElement.scrollTop > 450 || document.body.scrollTop > 450) {
        document.body.scrollTop = 200;
        document.documentElement.scrollTop = 200;
    }
}

function ConfirmUnban(id, name, steamid) {
    let reason = prompt('Please type the reason why you would Kunban ' + name + '[' + steamid + ']');
    let confirmMessage = 'Are you sure you want to Kunban ' + name + '[' + steamid + ']';
    let confirmHandler = confirm(confirmMessage);
    if (confirmHandler == true) {
        UnBanByID(encodeURIComponent(id), encodeURIComponent(reason));
    }
}

function UnBanByID(id, reason) {
    var xmlResponse1 = new XMLHttpRequest();
    xmlResponse1.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            $('#diva-' + id).html(this.responseText);

            let trDiva = document.getElementById('diva-tr-' + id);
            trDiva.className = "row-expired";
            let oldHtml = $('#length-' + id).html();
            let newHtml = (oldHtml + ' (Removed)');
            $('#length-' + id).html(newHtml);
        }
    };

    xmlResponse1.open("GET", "functions_url.php?oldid=" + encodeURIComponent(id) + '&reason=' + encodeURIComponent(reason), true);
    xmlResponse1.send();

    var xmlResponse2 = new XMLHttpRequest();
    xmlResponse2.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            // Process xmlResponse2.responseText here
        }
    };

    xmlResponse2.open("GET", "functions_url.php?id=" + encodeURIComponent(id), true);
    xmlResponse2.send();
}

function ViewPlayerHistory(steamid, method) {
    let url = "index.php?all=true&s=" + encodeURIComponent(steamid) + "&m=" + encodeURIComponent(method);
    window.location.replace(url);
}

function RebanFromID(id) {
    let url = "manage.php?reban&oldid=" + encodeURIComponent(id);
    window.location.replace(url);
}

function EditFromID(id) {
    let url = "manage.php?edit&oldid=" + encodeURIComponent(id);
    window.location.replace(url);
}

function addNewKban(playerName, playerSteamID, length, reason) {
    var xmlResponse = new XMLHttpRequest();
    xmlResponse.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            $('.error').html(xmlResponse.responseText);
        }
    };

    let url = "functions_url.php?add=1&playerName=" + encodeURIComponent(playerName) + '&playerSteamID=' + encodeURIComponent(playerSteamID) + '&length=' + encodeURIComponent(length) + '&reason=' + encodeURIComponent(reason);
    xmlResponse.open("GET", url, true);
    xmlResponse.send();
}

function EditKban(id, playerName, playerSteamID, length, reason) {
    var xmlResponse1 = new XMLHttpRequest();
    xmlResponse1.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            $('.error').html(xmlResponse1.responseText);
        }
    };

    let url = "functions_url.php?edit=1&id=" + encodeURIComponent(id) + '&playerName=' + encodeURIComponent(playerName) + '&playerSteamID=' + encodeURIComponent(playerSteamID) + '&length=' + encodeURIComponent(length) + '&reason=' + encodeURIComponent(reason);
    xmlResponse1.open("GET", url, true);
    xmlResponse1.send();
}

function RemoveKbanFromDBCheck(id) {
    let confirmMessage = "Are you sure you want to delete this kban from DB?";
    let confirmHandler = confirm(confirmMessage);
    if (confirmHandler == true) {
        RemoveKbanFromDB(encodeURIComponent(id));
    }
}

function RemoveKbanFromDB(id) {
    var xmlResponse1 = new XMLHttpRequest();
    xmlResponse1.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            $('.hide').html(xmlResponse1.responseText);
        }
    };

    xmlResponse1.open("GET", "functions_url.php?delete=1&deleteid=" + encodeURIComponent(id), true);
    xmlResponse1.send();
}

function setActive(num) {
    /* 0 = All Kbans, 1 = Active Kbans, 2 = Expired Kbans, 3 = Add Kban, 4 = Web Logs, 5 = Server Logs */
    if (num == 0) {
        const bar = document.getElementById("allKbans");
        bar.className = "active";
    } else if (num == 1) {
        const bar = document.getElementById("activeKbans");
        bar.className = "active";
    } else if (num == 2) {
        const bar = document.getElementById("expiredKbans");
        bar.className = "active";
    } else if (num == 4) {
        const bar = document.getElementById("weblogs");
        bar.className = "active";
    } else if (num == 5) {
        const bar = document.getElementById("srvlogs");
        bar.className = "active";
    } else {
        const bar = document.getElementById("addkban");
        bar.className = "active";
    }
}

function Login() {
    window.location.replace('login-init.php');
}

$(function() {
    $('.search-modal-btn-open').on('click', function(evt) {
        if ($(this).attr('id') == "main-search") {
            setModalSearch("all");
        } else {
            let type = $(this).attr('data-page');
            setModalSearch(type);
        }

        $('.search-modal-body').toggle();
    });

    $('.search-modal-btn-close').on('click', function() {
        $('.search-modal-body').toggle();
    });
});

window.addEventListener('click', function(e) {
    if (e.target.className == "search-modal-body") {
        $('.search-modal-body').toggle();
    }
});

function setModalSearch(type) {
    $('#hideInput').attr('name', type);
}

function showKbanWindowInfo(type, playerName = "", playerSteamID = "", reason = "", length = "", id = 0) {
    /* type values: 0 = Add Kban, 1 = Edit Kban, 2 = Unban Kban, 3 = Delete Kban */
    const titles = [
        "Kban Added",
        "Kban Edited",
        "Kban Unbanned",
        "Kban Deleted"
    ];

    const title = titles[type];
    $('#action-header-text').html(title + " <i class='fa-solid fa-check' style='color: var(--button-success);'></i>");

    let html = "";
    if (playerName[0]) {
        html += "<li>";
        html += "<span>";
        html += "<i class='fas fa-user'></i> Player";
        html += "</span>";
        html += "<span>";
        html += playerName;
        html += "</span>";
        html += "</li>";
    }

    if (playerSteamID[0]) {
        html += "<li>";
        html += "<span>";
        html += "<i class='fab fa-steam-symbol'></i> Steam ID";
        html += "</span>";
        html += "<span>";
        html += playerSteamID;
        html += "</span>";
        html += "</li>";
    }

    if (reason[0]) {
        html += "<li>";
        html += "<span>";
        html += "<i class='fas fa-question'></i> Reason";
        html += "</span>";
        html += "<span>";
        html += reason;
        html += "</span>";
        html += "</li>";
    }

    if (length[0]) {
        html += "<li>";
        html += "<span>";
        html += "<i class='fas fa-hourglass-half'></i> Duration";
        html += "</span>";
        html += "<span>";
        html += length;
        html += "</span>";
        html += "</li>";
    }

    if (type == 3) {
        let diva1 = '#diva-' + encodeURIComponent(id);
        $(diva1 + '-tr').hide();

        let diva2 = '#diva-tr-' + encodeURIComponent(id);
        $(diva2).hide();

        let totalResults = $('#totalText').attr('results');
        let totalHtml = $('#totalText').html();

        let totalResultsAfter = totalResults - 1;
        let totalResultsString = totalResults.toString();
        let totalResultsAfterString = totalResultsAfter.toString();

        let newTotalHtml = totalHtml.replace(totalResultsString, totalResultsAfterString);
        $('#totalText').attr('results', totalResultsAfter);
        $('#totalText').html(newTotalHtml);

        let startResults = $('#displaying-text').attr('results');
        let endResults = $('#displaying-text').attr('totalresults');
        let displayHtml = $('#displaying-text').html();

        let startResultsString = startResults.toString();
        let startResultsAfter = 0;

        if (startResults != 0) {
            startResultsAfter = startResults - 1;
        }

        let startResultsAfterString = startResultsAfter.toString();

        let endResultsString = endResults.toString();
        let endResultsAfter = endResults - 1;
        let endResultsAfterString = endResultsAfter.toString();

        $('#displaying-text').attr('results', startResultsAfter);
        $('#displaying-text').attr('totalresults', endResultsAfter);

        let newDisplayHtml = "";
        if (startResults == endResults) {
            let first = displayHtml.replace('-' + ' ' + totalResultsString, '-' + ' ' + totalResultsAfterString);
            newDisplayHtml = first.replace(startResultsString, startResultsAfterString);
        } else {
            let first = displayHtml.replace(startResultsString, startResultsAfterString);
            let second = first.replace('-' + ' ' + totalResultsString, '-' + ' ' + totalResultsAfterString);
            newDisplayHtml = second.replace(endResultsString, endResultsAfterString);
        }

        $('#displaying-text').html(newDisplayHtml);

        let count = $('#' + encodeURIComponent(id) + '-count').attr('count');
        let newCountHtml = count - 1;

        var allCounts = document.getElementsByClassName('count');
        for (var i = 0; i < allCounts.length; i++) {
            let steamID = $('#' + allCounts[i].id).attr('steamid');
            if (steamID == playerSteamID) {
                $('#' + allCounts[i].id).attr('count', newCountHtml);
                let Html = $('#' + allCounts[i].id).html();
                let newHtml = Html.replace(count.toString(), newCountHtml.toString());
                $('#' + allCounts[i].id).html(newHtml);
            }
        }
    }

    $('.kban-action-window .info .kban_details').html(html);

    $('.kban-action-window').css('display', 'block');

    let sec = 3;
    setTimeout(CloseWindow, (sec * 1000));
}

function CloseWindow() {
    $('.kban-action-window').css('display', 'none');
}

function GoHome() {
    window.location.href = 'index.php?all';
}