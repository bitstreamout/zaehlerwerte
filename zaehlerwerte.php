<?php // Zählerstände anzeigen
    //
    // Based on program: "vz_read_strom.php", 2014-05-09 RudolfReuter
    // 2020-12-25 Werner Fink
    //

    if ($_ENV{"DOCUMENT_ROOT"})
	chdir($_ENV{"DOCUMENT_ROOT"});
    $debug = 0;

    // Missing... other locales
    setlocale(LC_ALL, 'de_DE.UTF-8');

    //
    // Beginn Konfiguration
    //

    $uuids = [
	'Bezug'       => "XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX",
	'Einspeisung' => "XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX",
	'Erzeugung'   => "XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX",
    ];

    $middleware = "http://127.0.0.1:8080";
//  $middleware = "http://127.0.0.1:8080/middleware";
//  $middleware = "http://127.0.0.1/middleware";

    $middleware="${middleware}/data.json?options=raw";

    //
    // Datebase exists since
    //
    $since = "2020-12-15";

    //
    // Ende Konfiguration
    //
?>
<?php
    //
    // Expand the URL to access the data json API with the spcified uuid
    //
    foreach ($uuids as $uuid) {
        $middleware="${middleware}&uuid[]=${uuid}";
    }

    //
    // Generate a timestamp within ms in UNIX time
    //
    function timestamp($time) {
        $_t = new DateTime($time);
        $_ts = $_t->format('U')*1000;
        unset($_t);
        return $_ts;
    }

    //
    // Start Month of Quarter
    //
    function start_quarter($month) {
	$_n = 10;
	$month += 0;
	if ($month <= 9) $_n = 07;
	if ($month <= 6) $_n = 04;
	if ($month <= 3) $_n = 01;
	return $_n;
    }

    //
    // Run the final curl call with the final data access URL
    // and return either the json data or false
    //
    function askmiddleware($wrdata) {
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $wrdata);
	curl_setopt($curl, CURLOPT_HTTPGET, true);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_HTTP_CONTENT_DECODING, false);
	curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type' => 'application/json',  'Accept' => 'application/json'));
	$result = curl_exec($curl);
	if (!is_string($result)) {
	    echo "Error on curl $wrdata\n";
	} else {
	    $result = json_decode($result, false, 512, 0);
	}
	curl_close($curl);
	return $result;
    }
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta http-equiv="Content-Language" content="de-DE">
<meta http-equiv="Cache-Control" content="private">
<meta http-equiv="Expires" content="<?php echo date(DATE_RFC1123,strtotime("+5 minutes")); ?>">
<style type="text/css">
  .content { display: inline; white-space: pre-wrap; word-wrap: break-word; }
  .bold { font-weight: bold; }
  span { font-size: 12pt; }
  td { padding:0 15px; }
</style>
<title>Smartmeter</title>
</head>
<body>
<pre class="content">
<h1>Stromzähler auslesen</h1>
<?php
    $t = new DateTime("today");
    $today = $t->format("Y-m-d");
    if (isset($_GET['date'])) {
	$option = $_GET['date'];
	$option = explode('-', $option);
        $year = $option[0];
        $month = $option[1];
        $day = $option[2];
	unset($option);
    } else {
        $year = $t->format("Y");
        $month = $t->format("m");
        $day = $t->format("d");
    }
    $date = sprintf("%d-%02d-%02d", $year, $month, $day);
    $thisyear = $t->format("Y");
    unset($t);
    if (! checkdate($month, $day, $year)) {
	echo "Datum $date ungültig<br>"; 
        $date = $today;
	echo "Versuche stattdessen Heute<br>";
    }

    if (strcmp($date,$today) != 0) {
	$ts = timestamp($date . "T23:59:59");
    } else {
	$ts = timestamp("now");
    }

    $values = [];
    $json = askmiddleware("${middleware}&from=$ts");
    if ($json) {
        foreach ($uuids as $key => $uuid) {
	    foreach ($json->data as $entry) {
		if ($uuid == $entry->uuid)
		    break;
	    }
	    if (count($entry->tuples) == 0)
		goto skip;
	    switch (count($entry->tuples)) {
	    case 1:
		$values[$key] = (double)$entry->tuples[0][1];
		break;
	    default;
		$values[$key] = "N/A";
		break;
	    }
        }
    } else {
    skip:
	echo "<h3>Daten für Tag nicht vollstängig, überspringe Datensatz!<h3>\n";
	    foreach ($uuids as $key => $uuid)
		$values[$key] = "N/A";
    }
    unset($json);
    if (count($values) != 3) {
	echo "Daten sind korrupt!<br>\n";
    } else {
	if (is_numeric($values["Erzeugung"]) && is_numeric($values["Einspeisung"]))
	    $values["Eigenverbrauch"]  = $values["Erzeugung"]-$values["Einspeisung"];
	else
	    $values["Eigenverbrauch"]  = "N/A";
	if (is_numeric($values["Eigenverbrauch"]) && is_numeric($values["Bezug"]))
	    $values["Gesamtverbrauch"] = $values["Eigenverbrauch"] + $values["Bezug"];
	else
	    $values["Gesamtverbrauch"] = "N/A";
    }

    $y = new DateTime($date);
    $y->modify("-1 day");
    $yesterday = $y->format("Y-m-d");
    unset($y);

    $yts = timestamp($yesterday . "T23:59:59");

    $dbefore = [];
    $json = askmiddleware("${middleware}&from=$yts&to=$yts");
    if ($json) {
        foreach ($uuids as $key => $uuid) {
	    foreach ($json->data as $entry) {
		if ($uuid == $entry->uuid)
		    break;
	    }
	    if (count($entry->tuples) == 0)
		goto yskip;
	    switch (count($entry->tuples)) {
	    case 1:
		$dbefore[$key] = (double)$entry->tuples[0][1];
		break;
	    default;
		$dbefore[$key] = "N/A";
		break;
	    }
        }
    } else {
    yskip:
	echo "<h3>Daten für Tag zuvor nicht vollstängig, überspringe Datensatz!<h3>\n";
	    foreach ($uuids as $key => $uuid)
		$dbefore[$key] = "N/A";
    }
    unset($json);
    if (count($dbefore) != 3) {
	echo "Daten sind korrupt!<br>\n";
    } else {
	if (is_numeric($dbefore["Erzeugung"]) && is_numeric($dbefore["Einspeisung"]))
	    $dbefore["Eigenverbrauch"]  = $dbefore["Erzeugung"]-$dbefore["Einspeisung"];
	else
	    $dbefore["Eigenverbrauch"]  = "N/A";
	if (is_numeric($dbefore["Eigenverbrauch"]) && is_numeric($dbefore["Bezug"]))
	    $dbefore["Gesamtverbrauch"] = $dbefore["Eigenverbrauch"] + $dbefore["Bezug"];
	else
	    $dbefore["Gesamtverbrauch"] = "N/A";
    }

    $m = new DateTime($year . '-' . $month . '-' . "01");
    $mname = strftime("%B", $m->getTimestamp());
    $m->modify("-1 day");
    $previous = $m->format("Y-m-d");
    unset($m);

    $mts = timestamp($previous . "T23:59:59");
    $mmts = $mts + 1;

    $mbefore = [];
    $json = askmiddleware("${middleware}&from=$mts&to=$mmts");
    if ($json) {
	if ($debug) {
	    echo "Begin Debug\n";
	    print_r($json);
	    echo "\nEnd Debug\n";
	}
        foreach ($uuids as $key => $uuid) {
	    foreach ($json->data as $entry) {
		if ($uuid == $entry->uuid)
		    break;
	    }
	    if (count($entry->tuples) == 0)
		goto mskip;
	    switch (count($entry->tuples)) {
	    case 1:
		$mbefore[$key] = (double)$entry->tuples[0][1];
		break;
	    default;
		$mbefore[$key] = "N/A";
		break;
	    }
        }
    } else {
    mskip:
	echo "<h3>Daten für Monat $mname nicht vollstängig, überspringe Datensatz!<h3>\n";
	    foreach ($uuids as $key => $uuid)
		$mbefore[$key] = "N/A";
    }
    unset($json);
    if (count($mbefore) != 3) {
	echo "Daten sind korrupt!<br>\n";
    } else {
	if (is_numeric($mbefore["Erzeugung"]) && is_numeric($mbefore["Einspeisung"]))
	    $mbefore["Eigenverbrauch"]  = $mbefore["Erzeugung"]-$mbefore["Einspeisung"];
	else
	    $mbefore["Eigenverbrauch"]  = "N/A";
	if (is_numeric($mbefore["Eigenverbrauch"]) && is_numeric($mbefore["Bezug"]))
	    $mbefore["Gesamtverbrauch"] = $mbefore["Eigenverbrauch"] + $mbefore["Bezug"];
	else
	    $mbefore["Gesamtverbrauch"] = "N/A";
    }

    $q = new DateTime($year . '-' . start_quarter((int)$month) . '-' . "01");
    $qname = strftime("%B", $q->getTimestamp());
    $q->modify("-1 day");
    $previous = $q->format("Y-m-d");
    unset($q);

    $qts = timestamp($previous . "T23:59:59");
    $qqts = $qts + 1;

    $qbefore = [];
    $json = askmiddleware("${middleware}&from=$qts&to=$qqts");
    if ($json) {
	if ($debug) {
	    echo "Begin Debug\n";
	    print_r($json);
	    echo "\nEnd Debug\n";
	}
        foreach ($uuids as $key => $uuid) {
	    foreach ($json->data as $entry) {
		if ($uuid == $entry->uuid)
		    break;
	    }
	    if (count($entry->tuples) == 0)
		goto qskip;
	    switch (count($entry->tuples)) {
	    case 1:
		$qbefore[$key] = (double)$entry->tuples[0][1];
		break;
	    default;
		$qbefore[$key] = "N/A";
		break;
	    }
        }
    } else {
    qskip:
	echo "<h3>Daten für Quartal ab $qname nicht vollstängig, überspringe Datensatz!<h3>\n";
	    foreach ($uuids as $key => $uuid)
		$qbefore[$key] = "N/A";
    }
    unset($json);
    if (count($qbefore) != 3) {
	echo "Daten sind korrupt!<br>\n";
    } else {
	if (is_numeric($qbefore["Erzeugung"]) && is_numeric($qbefore["Einspeisung"]))
	    $qbefore["Eigenverbrauch"]  = $qbefore["Erzeugung"]-$qbefore["Einspeisung"];
	else
	    $qbefore["Eigenverbrauch"]  = "N/A";
	if (is_numeric($qbefore["Eigenverbrauch"]) && is_numeric($qbefore["Bezug"]))
	    $qbefore["Gesamtverbrauch"] = $qbefore["Eigenverbrauch"] + $qbefore["Bezug"];
	else
	    $qbefore["Gesamtverbrauch"] = "N/A";
    }

    $y = new DateTime($year . "-01-01");
    $yname = strftime("%B", $y->getTimestamp());
    $y->modify("-1 day");
    $previous = $y->format("Y-m-d");
    unset($y);

    $yts = timestamp($previous . "T23:59:59");
    $yyts = $yts + 1;

    $ybefore = [];
    $json = askmiddleware("${middleware}&from=$yts&to=$yyts");
    if ($json) {
	if ($debug) {
	    echo "Begin Debug\n";
	    print_r($json);
	    echo "\nEnd Debug\n";
	}
        foreach ($uuids as $key => $uuid) {
	    foreach ($json->data as $entry) {
		if ($uuid == $entry->uuid)
		    break;
	    }
	    if (count($entry->tuples) == 0)
		goto yyskip;
	    switch (count($entry->tuples)) {
	    case 1:
		$ybefore[$key] = (double)$entry->tuples[0][1];
		break;
	    default;
		$ybefore[$key] = "N/A";
		break;
	    }
        }
    } else {
    yyskip:
	echo "<h3>Daten für Jahr ab $yname nicht vollstängig, überspringe Datensatz!<h3>\n";
	    foreach ($uuids as $key => $uuid)
		$ybefore[$key] = "N/A";
    }
    unset($json);
    if (count($ybefore) != 3) {
	echo "Daten sind korrupt!<br>\n";
    } else {
	if (is_numeric($ybefore["Erzeugung"]) && is_numeric($ybefore["Einspeisung"]))
	    $ybefore["Eigenverbrauch"]  = $ybefore["Erzeugung"]-$ybefore["Einspeisung"];
	else
	    $ybefore["Eigenverbrauch"]  = "N/A";
	if (is_numeric($ybefore["Eigenverbrauch"]) && is_numeric($ybefore["Bezug"]))
	    $ybefore["Gesamtverbrauch"] = $ybefore["Eigenverbrauch"] + $ybefore["Bezug"];
	else
	    $ybefore["Gesamtverbrauch"] = "N/A";
    }

    $maxcols = 6;
    $startid = 0;
    $ids  = [ "Art", "Total", "&Delta;Tag", "&Delta;Monat", "&Delta;Quartal", "&Delta;Jahr" ];
    $arts = [ "Bezug", "Einspeisung", "Erzeugung", "Eigenverbrauch", "Gesamtverbrauch" ];

    echo "<span>";
    if ($date == $today)
	echo "<h3>Heute</h3>";
    else
	echo "<h3>Am $date</h3>";

    echo "<table id='werte'>";
    echo " <tr>";

    for ($j=0;$j<$maxcols;$j++) {
	if ($startid <= $maxcols)
	    echo '  <td class="mark"><span class="bold">' . $ids[$startid++] . '<span class="bold"></td>';
	else
	    echo "  <td></td>";
    }

    echo "</tr>\n<tr>\n";
    foreach ($arts as $art) {
	echo "  <tr><td align='left'>$art: ";
	for ($j=1;$j<$maxcols;$j++) {
	    if (!isset($values[$art])  || $values[$art]  < 0)
		$values[$art] = "N/A";
	    if (!isset($dbefore[$art]) || $dbefore[$art] < 0 || $dbefore[$art] > $values[$art])
		$dbefore[$art] = "N/A";
	    if (!isset($mbefore[$art]) || $mbefore[$art] < 0 || $mbefore[$art] > $values[$art])
		$mbefore[$art] = "N/A";
	    if (!isset($qbefore[$art]) || $qbefore[$art] < 0 || $qbefore[$art] > $values[$art])
		$qbefore[$art] = "N/A";
	    if (!isset($ybefore[$art]) || $ybefore[$art] < 0 || $ybefore[$art] > $values[$art])
		$ybefore[$art] = "N/A";
	    switch ($j) {
	    case 1:
		printf("<td align='right'>%10.3f&thinsp;kWh</td>", $values[$art]/1000);
		break;
	    case 2:
		if (is_numeric($dbefore[$art]))
		    printf("<td align='right'>%10.3f&thinsp;kWh</td>", ($values[$art]-$dbefore[$art])/1000);
		else
		    printf("<td align='right'>%s&thinsp;kWh</td>", $dbefore[$art]); 
		break;
	    case 3:
		if (is_numeric($mbefore[$art]))
		    printf("<td align='right'>%10.3f&thinsp;kWh</td>", ($values[$art]-$mbefore[$art])/1000);
		else
		    printf("<td align='right'>%s&thinsp;kWh</td>", $mbefore[$art]); 
		break;
	    case 4:
		if (is_numeric($qbefore[$art]))
		    printf("<td align='right'>%10.3f&thinsp;kWh</td>", ($values[$art]-$qbefore[$art])/1000);
		else
		    printf("<td align='right'>%s&thinsp;kWh</td>", $qbefore[$art]); 
		break;
	    default:
		if (is_numeric($ybefore[$art]))
		    printf("<td align='right'>%10.3f&thinsp;kWh</td>", ($values[$art]-$ybefore[$art])/1000);
		else
		    printf("<td align='right'>%s&thinsp;kWh</td>", $ybefore[$art]); 
		break;
	    }
	}
	echo "  </tr>";
    }
    echo " </tr>";
    echo "</table>";
    echo "</span>";
?>
</pre>
<form id="user_form" action="zaehlerwerte.cgi" method="get">
  <fieldset>
  <label>
    Wähle das Datum:
<?php
    setlocale(LC_TIME, 'de_DE.UTF-8');
    printf("<input type='date' id='date' name='date' min='%s' max='%s' value='%s' required class='date'>\n", $since, $today, $date);
?>
    <span class="validity"></span>
  </label>
  <button>Submit</button>
  </fieldset>
</form>
<script>
  var today = new Date(),
      year  = ''+today.getFullYear(),
      month = ''+(today.getMonth()+1),
      day   = ''+today.getDate();
  if (month.length < 2)
      month = '0'+month;
  if (day.length < 2)
      day = '0'+day;
  var curr = document.querySelector("form input[name='date']");
  curr.max = [year,month,day].join('-');
</script>
</body>
</html>
