<!DOCTYPE html>
<html prefix="og: http://ogp.me/ns#">
<head>
<meta charset=utf-8 />

<link rel="icon" type="image/png" href="favicon.ico" />
<?php

include 'configPaulo.php';

function get_json($date) {
  $file_day = "../OK/".$date."/config.txt";
  if (file_exists($file_day))
    return json_decode(file_get_contents($file_day));
  else
    return null;
}

function get_program_at($y, $m, $d, $hour) {

  $jsonObject = json_decode(file_get_contents("http://" . $_SERVER['HTTP_HOST'] . "/ws/index.php?req=onair&y={$y}&m={$m}&d={$d}&h={$hour}"));

  if($jsonObject->type == "emission") {
    return array($jsonObject->titre, $jsonObject->podcastable);
  }
  else {
    return array();
  }
  
}

function get_current_date(&$date, &$day, &$month, &$year) {
  $date = date ("Y-m-d");
  $day = date("d");
  $month = date("m");
  $year = date("Y");
}

function get_details_from_date($date, &$day, &$month, &$year) {
  $pattern = '/^([0-9][0-9][0-9][0-9])-([0-9][0-9])-([0-9][0-9])/';
  preg_match($pattern, $date, $matches);
  if (checkdate ($matches[2], $matches[3], $matches[1])) {
    $day = $matches[3];
    $month = $matches[2];
    $year = $matches[1];
  }
}
function get_date(&$date, &$day, &$month, &$year) {
  $date = $_GET['date'];
  $pattern = '/^([0-9][0-9][0-9][0-9])-([0-9][0-9])-([0-9][0-9])/';
  preg_match($pattern, $date, $matches);
  if (count($matches) != 4) {
    get_current_date($date, $day, $month, $year);
  }
  else if (checkdate ($matches[2], $matches[3], $matches[1])) {
    $day = $matches[3];
    $month = $matches[2];
    $year = $matches[1];
  }
  else {
    get_current_date($date, $day, $month, $year);
  }

}

function get_time(&$time) {
  $time = $_GET['time'];
  if (!ctype_digit($time) || ($time < 0) || ($time > 24))
    $time = "";
}

function load_ecoutes($date) {
  $result = array();
  
  for($i = 0; $i < 24; ++$i) {
    $result[$i][0] = "0";
    $result[$i][1] = "0";
    }

  $datetime = $date . " 00:00:00";

	$options = array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION);

	try {
	$bdd = new PDO('mysql:host='._PAULODB_SERVEUR.';dbname='._PAULODB_BD, _PAULODB_LOGIN, _PAULODB_MDP, $options);

  $sql = "Select timeslot, download, count(*) as nb From log_ecoute where (timeslot >= ".$bdd->quote($datetime)." and timeslot <= DATE_ADD(".$bdd->quote($datetime).", INTERVAL 24 HOUR)) group by timeslot, download;";
	
  $prep = $bdd->query($sql);
	
  $prep->execute();
  for($i=0; $row = $prep->fetch(); $i++){
    $elems = explode(" ", $row["timeslot"]);
    $elems2 = explode(":", $elems[1]);
    $h = intval($elems2[0]);
    $download = intval($row["download"]);
    $result[$h][$download] = $row["nb"];
  }
	}
	catch (PDOException $Exception ) {
	}


  return $result;
}


class Podcast {
  var $mp3;
  var $time;
  var $title;
  var $titleItems;
  var $ok;
  var $paulo_entries;
  var $duration;
  var $shortTitle;
  var $future;
  var $url;
  var $podcastable;
  var $image;


  function __construct() {
    $a = func_get_args(); 
        $i = func_num_args(); 
        if (method_exists($this,$f='__construct'.$i)) { 
            call_user_func_array(array($this,$f),$a); 
        } 
  }

  function __construct3($time, $entries, $duration) {
    $this->time = $time;
    $this->ok = false;
    $this->paulo_entries = $entries;
    $this->duration = $duration;
    $this->future = false;
    $this->url = "";
    $this->podcastable = false;
    $this->image = "";
  }

  function __construct2($jsonEntry, $date = "") {
    $this->mp3 = ltrim($jsonEntry->mp3);
    $this->future = $this->mp3 == "future";
    if ($this->future)
      $this->mp3 = "";
    $this->time = intval($jsonEntry->time);
    $titles = explode('|', $jsonEntry->title);
    $this->title = $jsonEntry->title;
    if (count($titles) == 1) {
      $this->titleItems = $jsonEntry->title;
    }
    else {
      $this->titleItems = "<ul>";
      foreach($titles as $t) {
	  $this->titleItems = $this->titleItems . "<li>" . $t . "</li>";
      }
      $this->titleItems = $this->titleItems . "</ul>";
    }
    $this->ok = true;
    $this->duration = 1;
    $this->url = ltrim($jsonEntry->url);
    $this->podcastable = $jsonEntry->podcastable;
    
    if ($date != "") {
      get_details_from_date($date, $day, $month, $year);
      $jsonObject = json_decode(file_get_contents("http://" .$_SERVER['HTTP_HOST']. "/ws/?req=image&t=" . urlencode($this->title) . "&h=" . $this->time . "&y=" . $year . "&m=" . $month . "&d=" . $day));
      $this->image = $jsonObject[0]->uri;
    }
    else 
      $this->image = "";
  }

  static function emptyToItem($hour) {
      echo "<p class=\"time_empty\">".$hour."h</p>";
  }

  function toItem($date, $ecoutes) {
    if ($this->ok) {
      if (strlen($this->mp3) != 0) {
	echo '<p class="time_elem';
      }
      else {
	echo '<p class="time_titles';
      }
      if ($this->duration != 1 || isset($this->$shortTitle))
	echo " large";
      if (strlen($this->mp3) != 0) {
	echo '" onclick="';
	$this->toLaunchTrack($date, true, true);
	echo '" ';
      }
      else {
	echo " time_empty\"";
	if ($this->future && $this->podcastable)
	  echo ' title="Bientôt en ligne&nbsp;!"';
      }
      echo  'onmouseover="document.getElementById(\'title'.$this->time.'\').style.display=\'block\';"  onmouseout="document.getElementById(\'title'.$this->time.'\').style.display=\'none\';">';
      if (isset($this->shortTitle))
	echo $this->shortTitle;
      else {
	echo $this->time.'h';
	if ($this->duration != 1)
	  echo "-".($this->time+$this->duration - 1).'h';
      }
      echo '';
      echo "<div id='title".$this->time."' class=\"time_popup\">".$this->titleItems;

      $h = intval($this->time);
      if (isset($ecoutes[$h]) && strlen($this->mp3) != 0) {
	$add = false;
	$nb = $ecoutes[$h][0] + $ecoutes[$h][1];
	if ($nb != 0) {
	  echo "<br /><span style=\"font-size: 70%; text-align:right\">".$nb . " écoute";
	  $add = true;
	  if ($nb > 1)
	    echo "s";
	  echo "</span>";
	}
      }
      if (strlen($this->mp3) == 0 && $this->future && $this->podcastable) {
	 echo "<br /><span style=\"font-size: 70%; text-align:right\">Bientôt en ligne&nbsp;!</span>";
      }
      echo "</div></p>\n";
    }
    else {
      echo '<p class="';
      if (count($this->paulo_entries) == 0) {
	echo 'time_empty';
	if ($this->duration != 1 || isset($this->$shortTitle))
	  echo " large";
	echo '">';
	if (isset($this->shortTitle))
	  echo $this->shortTitle;
	else {
	  echo $this->time.'h';
	  if ($this->duration != 1)
	    echo "-".($this->time+$this->duration).'h';
	}
	echo '</p>';
      } else {
	echo 'time_titles';
	if ($this->duration != 1 || isset($this->$shortTitle))
	  echo " large";
	echo '" onclick="';
	$this->toDisplayEntries(true);
	echo '" onmouseover="document.getElementById(\'title'.$this->time.'\').style.display=\'block\';"  onmouseout="document.getElementById(\'title'.$this->time.'\').style.display=\'none\';" >';
	if (isset($this->shortTitle))
	  echo $this->shortTitle;
	else {
	  echo $this->time.'h';
	  if ($this->duration != 1)
	    echo "-".($this->time+$this->duration).'h';
	}
	echo '';
	echo "<div id='title".$this->time."' class=\"time_popup\">Programmation musicale</div></p>\n";
      }
    }
  }

  function toMusicEntries() {
    echo "<ul>";
    $id = 0;
    foreach($this->paulo_entries as $entry) {
      echo "<li id=\"entry-".$this->time."-".$id."\" title=\"".$entry["time"].": ".$entry["title"].", ".$entry["author"]."\"><span>".$entry["time"]. "</span><em>" .$entry["title"] ."</em>, ".$entry["author"]."</li>";
      $id = $id + 1;
    }
    echo "</ul>";
  }

  function toLaunchTrack($date, $play, $quotes = false) {
    $t = str_replace("'", "\'", $this->title);
    if ($quotes)
      $t = str_replace("\"", "&quot;", $t);
    echo 'launch_track(\''.$date.'/'.$this->mp3.'\',\''.$t.'\',\''.$this->time.'\'';
    if ($play)
      echo ", true";
    else
      echo ", false";
    echo ", '".$this->url."')";
  }

  function toDisplayEntries($active) {
    if ($active)
      echo 'display_entries('.$this->time.', true)';
    else
      echo 'display_entries('.$this->time.', false)';
  }
  

}

function load_podcasts($jsonDay, $date, $time) {
  $result = array();
  if ($jsonDay->track)
    foreach($jsonDay->track as $track) {
	$result[intval($track->time)] = new Podcast($track, $time == $track->time ? $date : "");
    }
  return $result;
}

	$options = array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION);

	try {
		$bdd = new PDO('mysql:host='._PAULODB_SERVEUR.';dbname='._PAULODB_BD, _PAULODB_LOGIN, _PAULODB_MDP, $options);
	}
	catch (PDOException $Exception ) {
	$bdd = null;
	//exit("Des problèmes techniques nous empêchent temporairement de vous proposer les podcasts... Veuillez nous excuser pour la gêne occasionnée.");
	}

        include("lib/paulo_entries.php");


	$url = "http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
	$prefix_url = parse_url($url, PHP_URL_PATH);

	setlocale (LC_TIME, 'fr_FR.utf8','fra'); 
	date_default_timezone_set('Europe/Paris');
	get_date($date, $day, $month, $year);
	get_time($time);

	$datex = explode('-',$date);
	$datprev = date ("Y-m-d", mktime (0,0,0,$datex[1],$datex[2]-1,$datex[0]));
	$datnext = date ("Y-m-d", mktime (0,0,0,$datex[1],$datex[2]+1,$datex[0]));


	$jsonDay = get_json($date);

	$ecoutes = load_ecoutes($date);
	
	$podcasts = load_podcasts($jsonDay, $date, $time);

	$first = -1;
	$second = -1;
	for($i = 0; $i != 8; $i++)
	  if (isset($podcasts[$i])) {
            if ($first == -1)
                $first = $i;
            else if ($second == -1) {
                $second = -1;
                break;
            } 
	  }
        if ($first == -1)
            $first = 7;
        if ($second == -1)
            $second = 7;
        

	if ($first != 0) {
	  $entries = get_paulo_entries($date, 0, $bdd, ".", $first);
	  $podcasts[0] = new Podcast(0, $entries, $first);
	  if ($first != 1)
	    $podcasts[0]->shortTitle = "la nuit";
	}
	if ($first == 0 && $second > 0) {
	  $entries = get_paulo_entries($date, $first + 1, $bdd, ".", $second);
	  $podcasts[$first + 1] = new Podcast($first + 1, $entries, $second-1);
	  if ($second != 1)
	    $podcasts[$first + 1]->shortTitle = "la nuit";
	 }
	 
	// ajout des créneaux de podcast pas encore récupérés, mais qui vont arriver
	$heureCourante = intval(date("G"));
	$firstH = $heureCourante - 2;
	if ($firstH < 0)
	  $firstH = 0;
	for($i = $firstH; $i != $heureCourante + 1; $i++) {
	  if (!isset($podcasts[$i])) {
	    $program = get_program_at($year, $month, $day, $i);
	    if (count($program) != 0) {
	      $elem->mp3 = "future";
	      $elem->time = $i;
	      $elem->title = $program[0];
	      $elem->podcastable = $program[1];
	      $podcasts[$i] = new Podcast($elem, $date);
	    }
	  }
	}

	for($i = $second; $i != 24; $i++)
	  if (!isset($podcasts[$i])) {
	    $entries = get_paulo_entries($date, $i, $bdd, ".");
	    if ($entries && count($entries) > 0) {
	      $podcasts[$i] = new Podcast($i, $entries, 1);
	    }
	  }

	$actionSearch = $_GET["search"];
	$actionLive = $_GET["live"];
	
	$actionRecent = $_GET["recent"];
	if (isset($actionRecent) && $time == "") {
	  $time = intval(date("H"));
	  if (!isset($podcasts[$time]) || $podcasts[$time]->ok) {
	    $time = "";
	    $actionLive = true;
	    unset($actionRecent);
	    }
	}
	
	$fulldate = strftime("%A %e %B %Y",strtotime($date));
?>

<title><?php 
if (!isset($time) || $time == "")
  echo "Podcast Radio Campus Clermont-Ferrand";
else
 echo  htmlspecialchars ($podcasts[$time]->title) . " - Radio Campus, ".$fulldate . ", ".$time."h";
?></title>
<meta property="og:locale" content="fr_FR" />
<meta property="og:type" content="article" />
<?php if (!isset($time) || $time == "") { ?>
<meta property="og:title" content="<?php echo htmlspecialchars ($podcasts[$time]->title) . " - Radio Campus, ".$fulldate . ", ".$time."h";?>" />
<meta property="og:description" content="Podcast de l'émission <?php echo htmlspecialchars ($podcasts[$time]->title) . " du ".$fulldate . " ".$time."h sur Radio Campus Clermont-Ferrand";?>" />
<meta property="og:site_name" content="Le podcast de Radio Campus Clermont-Ferrand" />
<?php }
else if (isset($actionLive)) { ?>
<meta property="og:title" content="Streaming Radio Campus Clermont-Ferrand" />
<meta property="og:description" content="Streaming en direct de Radio Campus Clermont-Ferrand" />
<meta property="og:site_name" content="Le streaming de Radio Campus Clermont-Ferrand" />
<?php } ?>

<meta http-equiv="Content-Type" content="text/html; charset=utf8" />
<link rel="stylesheet" href="skin/circle.skin/circle.player.css?date=<?php echo filemtime('skin/circle.skin/circle.player.css');?>" />
<link rel="stylesheet" href="skin/circle.skin/jquery.mCustomScrollbar.css" />
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js"></script>
<script type="text/javascript" src="js/jquery.jplayer.min.js"></script>
<script type="text/javascript" src="js/jquery.transform2d.js"></script>
<script type="text/javascript" src="js/jquery.grab.js"></script>
<script type="text/javascript" src="js/mod.csstransforms.min.js"></script>
<script type="text/javascript" src="js/circle.player.js"></script>
<script type="text/javascript" src="js/jquery.mCustomScrollbar.concat.min.js"></script>
<script type="text/javascript" src="js/jquery.ui.datepicker-fr.js"></script>
<script type="text/javascript" src="js/jquery-ui-1.10.4.custom.min.js"></script>

<link rel="alternate" type="application/rss+xml" title="Tous les podcasts de Radio Campus" href="http://<?php echo $_SERVER['HTTP_HOST'];?>/onair/podcast/player/rss/" />

<?php 
foreach($podcasts as $podcast) {
  if ($podcast->ok && $podcast->mp3 != "") { ?>
    <link rel="alternate" type="application/rss+xml" title="Tous les podcasts de l'émission <?php echo str_replace("\"", "&quot;", $podcast->title); ?>" href="http://<?php echo $_SERVER['HTTP_HOST'];?>/onair/podcast/player/rss/?q=<?php echo rawurlencode($podcast->title); ?>" />
    <?php
  }
}
?>
<!-- load Twitter script -->
<script type="text/javascript">!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>


<script type="text/javascript">
//<![CDATA[
var myCirclePlayer;
$(document).ready(function(){

	/*
	 * Instance CirclePlayer inside jQuery doc ready
	 *
	 * CirclePlayer(jPlayerSelector, media, options)
	 *   jPlayerSelector: String - The css selector of the jPlayer div.
	 *   media: Object - The media object used in jPlayer("setMedia",media).
	 *   options: Object - The jPlayer options.
	 *
	 * Multiple instances must set the cssSelectorAncestor in the jPlayer options. Defaults to "#cp_container_1" in CirclePlayer.
	 *
	 * The CirclePlayer uses the default supplied:"m4a, oga" if not given, which is different from the jPlayer default of supplied:"mp3"
	 * Note that the {wmode:"window"} option is set to ensure playback in Firefox 3.6 with the Flash solution.
	 * However, the OGA format would be used in this case with the HTML solution."../OK/2014-04-29-1800.mp3"
	 */
      window.number_entries = [];
      window.time_entries = [];
      window.logged = [];
      window.entryOpen = [];
      window.loadedSimilaires = [];
      window.pImages = [];
      
      $('#submit').click(function(){
      $.post("send.php", $("#mycontactform").serialize() + "&url=" + encodeURIComponent(window.location.href),  function(response) {
      $('#success').html(response);
      });
      return false;
      });

      <?php 
	  for($i = 0; $i != 24; $i++)
	    if (isset($podcasts[$i]) && !$podcasts[$i]->ok)  {
	      echo "window.number_entries[".$podcasts[$i]->time."] = ".count($podcasts[$i]->paulo_entries).";";
	      $j = 0;
	      if ($podcasts[$i]->paulo_entries) {
		  echo "window.time_entries[".$podcasts[$i]->time."] = [];\n";
	      foreach($podcasts[$i]->paulo_entries as $entry) {
		echo "window.time_entries[".$podcasts[$i]->time."][".$j."] = '".$entry["time"]."';\n";
		$j = $j + 1;
	      }
	    }
	    }
	    else if (isset($podcasts[$i]) && isset($podcasts[$i]->image) && $podcasts[$i]->image != "")
	      	      echo "window.pImages[".$podcasts[$i]->time."] = '".addslashes($podcasts[$i]->image) ."';";

	if ($time || isset($actionRecent)) {
		if (isset($podcasts[$time])) {
		  if ($podcasts[$time]->ok) {
		    if (!isset($actionRecent))
		      $podcasts[$time]->toLaunchTrack($date, false);
		  }
		  else {
		      $podcasts[$time]->toDisplayEntries(false);
		  }
		    echo ";";
	    }
      }
	?>
		var showTimeLeft = function(event) {
			if (!window.live) {
			  var time = event.jPlayer.status.currentTime;
			  var timeDisplay = window.activeTime+":"+$.jPlayer.convertTime(time);
			  var myDiv = document.getElementById("time");
			  myDiv.innerHTML = timeDisplay;
			}
		};
	<?php    if ($time) {
		if (isset($podcasts[$time]) && $podcasts[$time]->ok) {
		  echo 'window.var_time_string = "'.$time.'";';
		}
		} ?>
	myCirclePlayer = new CirclePlayer("#jquery_jplayer_1",
	{
		<?php    if ($time) {
		if (isset($podcasts[$time]) && $podcasts[$time]->ok) {
        		echo 'mp3: "../OK/'.$date.'/'.$date.'-'.$time.'00.mp3",'; 
	  } }
		    else {
		      if (isset($actionLive)) {
			echo 'mp3: "http://campus.abeille.com:8000/campus",';
		      }
		  }
		?>
	
	}, {
		timeupdate: showTimeLeft,
		durationchange: showTimeLeft,
		supplied: "mp3",
		cssSelectorAncestor: "#cp_container_1",
		swfPath: "js",
		preload: "auto",
		wmode: "window",
<?php if (isset($actionLive)) { ?>
		ready: function (event) {
			play_live(true, true);
		},
<?php } ?>
		keyEnabled: true
	});
	
	jQuery('#jquery_jplayer_1').bind(jQuery.jPlayer.event.play, function(event) { 
		  if (event.jPlayer.status.paused===false) {
		  if (window.var_time_string != "-1") {
		      //alert("http://" + window.location.hostname + "/onair/podcast/player/ws/log_ecoute.php?d=<?php echo $date;?>&h=" + window.var_time_string);
		      $.ajax({
 			type: "GET",
 			async: false,
 			timeout: 5000,
 			url: "http://" + window.location.hostname + "/onair/podcast/player/ws/log_ecoute.php?d=<?php echo $date;?>&h=" + window.var_time_string,
 			success:function(data) {},
 			error: function (textStatus, errorThrown) {}});
		    }}
		  });

<?php if (isset($actionSearch)) { 
      echo "open_search();";
      echo "rechercher(false);";

} ?> 
	precharger_image("images/chargement.gif");

});

$(window).on("popstate", function () {
  // if the state is the page you expect, pull the name and load it.
  if (history.state) {
    if (history.state.state == "live") {
      play_live(true, false);
    } 
    else if (history.state.state == "play") {
	launch_track(history.state.mp3, history.state.title, history.state.time, false, history.state.url);
    } 
    else if (history.state.state == "list") {
      display_entries(history.state.time, false);
    } 
  }
});

function add_telecharger(var_time) {
	var myDiv = document.getElementById("dl_podcast");
	myDiv.innerHTML = "<a href=\"mp3.php?d=<?php echo $date; ?>&h=" + var_time + "&dl=true\">▼ télécharger</a>";
}

function escapeHtml(text) {
  return text
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "\\\"")
      .replace(/\'/g,"\\\'")
}

function fill_similaires(data, var_title, var_time) {
				var span = $("#similaire_results");
				var result = "";
				//
  				
				if (span != undefined) {
					result += "<p class=\"rechercher-similaires\">flux RSS de cette émission <a style=\"margin-left: 10px\" href=\"/onair/podcast/player/rss/?q=" + encodeURIComponent(var_title) + "\" title=\"actualités de " + var_title + "\"><img src=\"images/rss-small.png\" alt=\"flux rss\"/></p>";
					if (data.hasOwnProperty(var_title)) {
					  if (data[var_title].hasOwnProperty("-1")) {
					    result += '<a href="' + getUrlFromDateTime(data[var_title]["-1"]) + '" class="pred_pst bouton"><span class="logo">◀</span>précédente émission de <span class="titre_podcast">' + var_title + '</span> <span class="quand_podcast">'+getDateHumanFormat(data[var_title]["-1"])+'</span></a>';
					  }
					  else {
					    result += '<span class="no_pst" />';
					  }
					  if (data[var_title].hasOwnProperty("1")) {
					    result += '<a href="' + getUrlFromDateTime(data[var_title]["1"]) + '" class="next_pst bouton"><span class="logo">▶</span>émission suivante de <span class="titre_podcast">' + var_title + '</span> <span class="quand_podcast">'+getDateHumanFormat(data[var_title]["1"])+'</span></a>';
					  }
					}
					result += '<p class="rechercher-similaires">Rechercher <a href="?search=' + encodeURIComponent(var_title) + '">toutes les émissions de ' + escapeHtml(var_title) + '</a></p>';
					if (data.hasOwnProperty("podcast")) {
					  if (data["podcast"].hasOwnProperty("-1")) {
					    result += '<a href="' + getUrlFromDateTime(data["podcast"]["-1"]) + '" class="pred_pst bouton"><span class="logo">◀</span>podcast précédent&nbsp;: <span class="titre_podcast">' + data["podcast"]["-1"][2]  + '</span> <span class="quand_podcast">'+getDateHumanFormat(data["podcast"]["-1"])+'</span></a>';
					  }
					  else {
					    result += '<span class="no_pst" />';
					  }
					  if (data["podcast"].hasOwnProperty("1")) {
					    result += '<a href="' + getUrlFromDateTime(data["podcast"]["1"]) + '" class="next_pst bouton"><span class="logo">▶</span>podcast suivant&nbsp;: <span class="titre_podcast">' + data["podcast"]["1"][2] + '</span> <span class="quand_podcast">'+getDateHumanFormat(data["podcast"]["1"])+'</span></a>';
					  }
					}
					
					
					var keys = [];
					for (var key in data) {
					  if (data.hasOwnProperty(key) && key != var_title && key != "podcast") {
					    keys.push(key);
					  }
					}
					var l = keys.length;
					if (l != 0) {
					  result += "<h3>Vous aimez "+ var_title + "&nbsp;? Vous aimerez sans doute...</h3>";
					}
					
					for(i = 0; i < l; i++) {
					  result += '<a href="' + getUrlFromDateTime(data[keys[i]]) + '" class="mid_pst bouton"><span class="titre_podcast">' + keys[i]  + '</span> <span class="quand_podcast">'+getDateHumanFormat(data[keys[i]])+'</span></a>';
					}
					span.html(result);
					$("#similaire_box_scroll").mCustomScrollbar("update");
					}

}

function add_similaire(var_time, var_title) {
	var myDiv = document.getElementById("dl_similaires");
	myDiv.innerHTML = "<a onclick=\"display_similaires(" + var_time + ", '" + escapeHtml(var_title) + "')\">◍ émissions similaires</a>";
	if (window.loadedSimilaires[var_time] == undefined) {
	  var myDiv = document.getElementById("similaire_results");
	  myDiv.innerHTML = "Chargement...";
	  setTimeout(function(){
     $.ajax({
			type: "GET",
			async: false,
			timeout: 5000,
			url: "http://" + window.location.hostname + "/onair/podcast/player/ws/search.php?action=similaire&q=" + encodeURIComponent(var_title) + "&y=<?php echo $datex[0];?>&m=<?php echo $datex[1];?>&d=<?php echo $datex[2];?>&h=" + var_time,
			success:function(data)
			{
				//alert("http://" + window.location.hostname + "/onair/podcast/player/ws/search.php?action=similaire&q=" + encodeURIComponent(var_title) + "&y=<?php echo $datex[0];?>&m=<?php echo $datex[1];?>&d=<?php echo $datex[2];?>&h=" + var_time);
				window.loadedSimilaires[var_time] = data;
				//alert(JSON.stringify(data));
				fill_similaires(data, var_title, var_time);
			},
			error: function (textStatus, errorThrown) {
            }});
	  }, 50);
	  
	  }
	  else
	    fill_similaires(window.loadedSimilaires[var_time], var_title, var_time);
}


function remove_telecharger() {
var myDiv = document.getElementById("dl_podcast");
	myDiv.innerHTML = "";
}

function remove_similaire(var_time) {
	var myDiv = document.getElementById("dl_similaires");
	myDiv.innerHTML = "";
	clear_similaires_box();
}

function clear_previous() {
  $("#jquery_jplayer_1").jPlayer("clearMedia");
  var play = document.getElementById("main-play");
  play.style.display = "none";
  var play = document.getElementById("main-control");
  play.style.display = "none";
  var play = document.getElementById("progess-holder");
  play.style.display = "none";
  var play = document.getElementById("buffer-holder");
  play.style.display = "none";	


    var myDiv = document.getElementById("time");
	myDiv.innerHTML = "";
    var myDiv = document.getElementById("time"+window.activeTime);
    if (myDiv != undefined) {
      var lastIndex = myDiv.className.lastIndexOf(" ")
      myDiv.className = myDiv.className.substring(0, lastIndex);
    }

    var play = document.getElementById("titres_musique" + window.activeTime);
    if (play != undefined) {
      play.className = "titres_musicaux";
    }
    var play = document.getElementById("searchBox");
    if (play != undefined) {
      play.className = "hidden";
    }
    var play = document.getElementById("agendaBox");
    if (play != undefined) {
      play.className = "hidden";
    }
    var play = document.getElementById("contactBox");
    if (play != undefined) {
      play.className = "hidden";
    }
    var play = document.getElementById("rssBox");
    if (play != undefined) {
      play.className = "hidden";
    }
    window.activeTime = undefined;
    window.searchBoxOpen = undefined;
    window.agendaBoxOpen = undefined;
    window.contactBoxOpen = undefined;
    window.rssBoxOpen = undefined;
    
    var myDiv = document.getElementById("directlink");
    if (myDiv != undefined)
      myDiv.className = "";
    window.live = false;

    var myDiv = document.getElementById("hour_of_podcast");
    myDiv.innerHTML = "";

    var myDiv = document.getElementById("description_of_podcast");
    myDiv.innerHTML = "";

    var myDiv = document.getElementById("ecoutes_of_podcast");
    myDiv.innerHTML = "";
    
    remove_telecharger();
    remove_similaire();
    remove_image();
}

function set_image_default(var_time) {
  var t = parseInt(var_time);
  if (t < 6) {
    window.pImages[var_time] = 'http://<?php echo $_SERVER["HTTP_HOST"]?>/onair/podcast/player/images/fond-bleu.png';
  }
  else if (t < 12) {
    window.pImages[var_time] = 'http://<?php echo $_SERVER["HTTP_HOST"]?>/onair/podcast/player/images/fond-jaune.png';
  }
  else if (t < 18) {
    window.pImages[var_time] = 'http://<?php echo $_SERVER["HTTP_HOST"]?>/onair/podcast/player/images/fond-rouge.png';
  }
  else {
    window.pImages[var_time] = 'http://<?php echo $_SERVER["HTTP_HOST"]?>/onair/podcast/player/images/fond-vert.png';
  }
  set_image(window.pImages[var_time]);

}

function set_image(url) {
  
				  // crate a new image
				  var pic_real_width, pic_real_height;
				  var span = $("#img-pst");
				  // get its size
				  $("<img/>") // Make in memory copy of image to avoid css issues
					.attr("src", url).load(function() {
					pic_real_width = this.width;   // Note: $(this).width() will not
					pic_real_height = this.height; // work for in memory images.
					var spdiv = $("#image-podcast");
					span.css("width", "");
					span.css("height", "");
					span.css("margin-left", "");
					span.css("margin-top", "");
					if (pic_real_height < pic_real_width) {
					  span.css("height", "300px");
					  var new_width = pic_real_width * 300 / pic_real_height;
					  var left = (new_width - 300) / 2;
					  span.css("margin-left", "-" + left.toString() + "px");
					}
					else {
					  span.css("width", "300px");
					  var new_height = pic_real_height * 300 / pic_real_width;
					  var top = (new_height - 300) / 2;
					  span.css("margin-top", "-" + top.toString() + "px");
					}

					span.attr("src", url);
					spdiv.css("display", "block");
				  });

}

function add_image(var_title, var_time, var_year, var_month, var_day, force) {

		
		if (window.pImages[var_time] != undefined && !force)
		  set_image(window.pImages[var_time]);
		else
		  $.ajax({
			type: "GET",
			async: false,
			timeout: 5000,
			url: "http://" + window.location.hostname + "/ws/?req=image&t=" + encodeURIComponent(var_title) + "&h=" + var_time + "&y=" + var_year + "&m=" + var_month + "&d=" + var_day,
			success:function(data)
			{
			
				var span = $("#img-pst");
				if (span != undefined) {
				if (data != undefined && data.length != 0 && data[0].uri != null)
				{
				  window.pImages[var_time] = 'http://<?php echo $_SERVER["HTTP_HOST"]?>' + data[0].uri;
 				  set_image(window.pImages[var_time]);
				}
				else
				  set_image_default(var_time);
				  }
				else
				  remove_image();
			},
			error: function (textStatus, errorThrown) {
            }
		});

}

function remove_image() {
    var img = document.getElementById("img-pst");
    img.src = "";
    var mydiv = document.getElementById("image-podcast");
    mydiv.style.display = "none";
}

function display_downloads(var_time) {
  var ecoutes = <?php echo json_encode($ecoutes); ?>;
  
  if (ecoutes[var_time] === undefined) {
    return "";
  }
  else {
    var result = "";

    var add = false;
    if (ecoutes[var_time][0] != 0) {
      result += ecoutes[var_time][0] + " écoute";
      add = true;
      if (ecoutes[var_time][0] > 1)
	result += "s";
    }
    if (ecoutes[var_time][1] != 0) {
      if (add)
	result += ", ";
      result += ecoutes[var_time][1] + " téléchargement";
      if (ecoutes[var_time][1] > 1)
	result += "s";
    }
    
    return result;
  }
}

function launch_track(var_mp3, var_title, var_time, var_play, var_url)
	{	

		if (window.activeTime == var_time) {
		  if ($("#jquery_jplayer_1").data().jPlayer.status.paused == false)
		    $("#jquery_jplayer_1").jPlayer("pause");
		  else
		    $("#jquery_jplayer_1").jPlayer("play");
		  return;
		}

		clear_previous();

		var play = document.getElementById("main-play");
		play.style.display = "block";
		var play = document.getElementById("main-control");
		play.style.display = "block";
		var play = document.getElementById("progess-holder");
		play.style.display = "block";	
		var play = document.getElementById("buffer-holder");
		play.style.display = "block";
		
			
		window.activeTime = var_time;

		var myDiv = document.getElementById("time"+window.activeTime);
		myDiv.className = "time_active";
		
		var hourDisplay = var_time+"h";
		if (hourDisplay == "0h")
		  hourDisplay = "minuit";

		var myDiv = document.getElementById("hour_of_podcast");
		myDiv.innerHTML = hourDisplay;

		var myDiv = document.getElementById("description_of_podcast");
		if (var_url != "")
		  myDiv.innerHTML = '<span><a href="' + var_url + '" target="_blank">' + var_title + '</a></span>';
		else
		  myDiv.innerHTML = var_title;

		var myDiv = document.getElementById("ecoutes_of_podcast");
		myDiv.innerHTML = display_downloads(var_time);
		
		if (var_play)
		  window.history.pushState({ state: 'play', mp3: var_mp3,  title: var_title, time: var_time, url: var_url}, 'Radio Campus <?php echo $date;?>'+var_time+'h', '<?php echo $prefix_url;?>?date=<?php echo $date;?>&time='+var_time);

		$("#jquery_jplayer_1").jPlayer("clearMedia");
		
		var complement = "";
		if (window.logged[var_time] !== undefined) {
		    complement = "&nl=true";
		}
		
		if (var_time < 10)
		  window.var_time_string = "0" + var_time;
		else
		  window.var_time_string = var_time;
		$("#jquery_jplayer_1").jPlayer("setMedia", { 
			mp3: "../OK/<?php echo $date; ?>/<?php echo $date; ?>-" + var_time_string + "00.mp3",
		});
		jQuery('#jquery_jplayer_1').bind(jQuery.jPlayer.event.ended +'.jp-repeat', function() { 
		  display_similaires(var_time, var_title);
		  });


		window.logged[var_time] = true;
		
		if (var_play) {
		  $("#jquery_jplayer_1").jPlayer("play");
		}
		
		document.title = var_title + ' - Radio Campus, <?php echo $fulldate;?>, '+var_time+'h';
		
		update_reseaux_sociaux('http://<?php echo $_SERVER["HTTP_HOST"].$prefix_url;?>?date=<?php echo $date;?>&time='+var_time);
		add_telecharger(var_time);
		add_similaire(var_time, var_title);
		add_image(var_title, var_time, <?php echo $datex[0];?>, <?php echo $datex[1];?>, <?php echo $datex[2]; ?>, false);
	}


function loop_reload(){
		$.ajax({
			type: "GET",
			async: false,
			timeout: 5000,
			url: "http://" + window.location.hostname + "/ws/?req=onair&d=" + new Date().getTime(),
			success:function(data)
			{
				var span = $("#titre-live");
				if (span != undefined) {
				
				var date = new Date();
				var time = date.getHours();
				if (data.type == null || data.type == "paulo")
				{
					span.html(data.titre+" - "+data.auteur);
					set_image_default(time);
				}
				else if (data.type == "emission")
				{
					span.html('émission <a href="' + data.url + '">' + data.titre + "</a>");
					if (window.lastHour != time)
					  add_image(data.titre, time, date.getUTCFullYear(), date.getUTCMonth() + 1, date.getUTCDate(), true);
				}
				else
				{
					span.html("-");
				}
				}
				else {
				  clearInterval(window.interval);
				}
				window.lastHour = time;
			},
			error: function (textStatus, errorThrown) {
            }
		});
	return false;
}

function play_live(var_start, var_history) 
      {

		if (window.live == true) {
		  clear_previous();
		  return;
		}
		clear_previous();

		var play = document.getElementById("main-play");
		play.style.display = "block";
		var play = document.getElementById("main-control");
		play.style.display = "block";
		var play = document.getElementById("progess-holder");
		play.style.display = "none";	
		var play = document.getElementById("buffer-holder");
		play.style.display = "none";	


		window.live = true;
		window.var_time_string = -1;

		var myDiv = document.getElementById("directlink");
		myDiv.className = "live_active";

		var myDiv = document.getElementById("hour_of_podcast");
		myDiv.innerHTML = "direct";

		var myDiv = document.getElementById("description_of_podcast");
		myDiv.innerHTML = "<span><span id=\"titre-live\"></span></span>";

		var myDiv = document.getElementById("ecoutes_of_podcast");
		myDiv.innerHTML = "<span class=\"small\">en cas de problème, <a href=\"http://campus.abeille.com:8000/campus\">ouvrir directement le flux</a></span>";

    ;

		var myDiv = document.getElementById("time");
		myDiv.innerHTML = "<em>en direct</em>";

		if (var_history)
		  window.history.pushState({ state: 'direct' }, 'Radio Campus en direct', '<?php echo $prefix_url;?>?live=true');


		$("#jquery_jplayer_1").jPlayer("clearMedia");
		
		$("#jquery_jplayer_1").jPlayer("setMedia", { 
			title: "Radio Campus live",
			mp3: "http://campus.abeille.com:8000/campus",
		});
		jQuery('#jquery_jplayer_1').bind(jQuery.jPlayer.event.ended +'.jp-repeat', function() { 
		  false;
		  });

		
		if (var_start)
		  $("#jquery_jplayer_1").jPlayer("play");

		if (window.interval == undefined)
		  clearInterval(window.interval);
		set_image_default(<?php echo date("G"); ?>);
		window.lastHour = -1;
		loop_reload();
		window.interval = setInterval('loop_reload();',10000);
  
		document.title = "Radio Campus live";
		update_reseaux_sociaux('http://<?php echo $_SERVER["HTTP_HOST"].$prefix_url;?>?live=true');
		
}


function display_entries(var_time, var_active) {

		if (window.activeTime == var_time) {
		clear_previous();
		  return;
		}
		clear_previous();

		var titres = document.getElementById("titres_musique" + var_time);

		titres.className = "titres_musicaux popupBox";

		window.activeTime = var_time;


		if (window.entryOpen[var_time] === undefined) {
		  $("#titres_musique" + var_time+ "_scroll").mCustomScrollbar();
		  window.entryOpen[var_time] = true;
		}

		var hourDisplay = var_time+"h";
		if (hourDisplay == "0h")
		  hourDisplay = "minuit";
		var myDiv = document.getElementById("titres_musique" + var_time + "_title");
		myDiv.innerHTML = hourDisplay;

		var myDiv = document.getElementById("description_of_podcast");
		myDiv.innerHTML = "<span>Programmation musicale</span>";

		var myDiv = document.getElementById("ecoutes_of_podcast");
		myDiv.innerHTML = "";

		var myDiv = document.getElementById("time"+window.activeTime);
		myDiv.className = myDiv.className + " time_active";

		if (var_active)
		  window.history.pushState({ state: 'list', time: var_time }, 'Radio Campus <?php echo $date;?>'+var_time+'h', '<?php echo $prefix_url;?>?date=<?php echo $date;?>&time='+var_time);
		  
		  document.title = 'Programmation musicale - Radio Campus, <?php echo $fulldate;?>, '+var_time+'h';
		  update_reseaux_sociaux('http://<?php echo $_SERVER["HTTP_HOST"].$prefix_url;?>?date=<?php echo $date;?>&time='+var_time);
}

function open_search() {

		if (window.searchBoxOpen == true) {
		clear_previous();
		  return;
		}
		clear_previous();

		var titres = document.getElementById("searchBox");
		titres.className = "superbox popupBox";
		titres.style.width = "0";
		titres.style.height = "0";
		$('#searchBox').animate({
		  width: "+=700px",
		  height: "+=350px"
		}, 100, function() {
		  // Animation complete.
		});		

		if (window.searchBoxOpenFirst === undefined) {
		  $("#searchBox_scroll").mCustomScrollbar();
		  window.searchBoxOpenFirst = true;
		}
		  window.searchBoxOpen = true;
}



function pressSearch(event) {
 if (event.keyCode == 13)
    rechercher(true);
}

function open_rss() {
		if (window.rssBoxOpen == true) {
		clear_previous();
		  return;
		}
		clear_previous();


		var titres = document.getElementById("rssBox");
		titres.className = "superbox popupBox";
		titres.style.width = "0";
		titres.style.height = "0";
		$('#rssBox').animate({
		  width: "+=700px",
		  height: "+=550px"
		}, 100, function() {
		  // Animation complete.
		});

		  window.rssBoxOpen = true;
}
	
function open_contact() {
		if (window.contactBoxOpen == true) {
		clear_previous();
		  return;
		}
		clear_previous();


		var titres = document.getElementById("contactBox");
		titres.className = "superbox popupBox";
		titres.style.width = "0";
		titres.style.height = "0";
		$('#contactBox').animate({
		  width: "+=700px",
		  height: "+=550px"
		}, 100, function() {
		  // Animation complete.
		});

		  window.contactBoxOpen = true;
}
		  
function open_agenda() {
		if (window.agendaBoxOpen == true) {
		clear_previous();
		  return;
		}
		clear_previous();


		var titres = document.getElementById("agendaBox");
		titres.className = "superbox popupBox";
		titres.style.width = "0";
		titres.style.height = "0";
		$('#agendaBox').animate({
		  width: "+=350px",
		  height: "+=350px"
		}, 100, function() {
		  // Animation complete.
		});

		  window.agendaBoxOpen = true;

      $( "#datepicker" ).datepicker({dateFormat: "yy-mm-dd",
				      regional: "fr",
				      defaultDate: "<?php echo $date; ?>"});

      $( "#godate" ).button().click(function() {changeDate()});


}

function changeDate() {
    var date = $.datepicker.formatDate("yy-mm-dd", $("#datepicker").datepicker('getDate'));
    window.location.assign("/onair/podcast/player/?date=" + date);
}

function precharger_image(url)
{
        var img = new Image();
	img.src=url;
        return img;
}
function rechercher(var_add) {

	    var box_requ = document.getElementById("champsRecherche");
	    var var_search = box_requ.value;

	    var results = document.getElementById("results");
	    results.innerHTML = "<div class=\"chargement\"><img src=\"images/chargement.gif\" alt=\"chargement\" /><p>chargement...</p></div>";
	    $("#searchBox_scroll").mCustomScrollbar("update");


	    var m2Txt = [ "janvier", "février", "mars", "avril", "mai", "juin", "juillet", "août", "septembre", "octobre", "novembre", "décembre" ];
	    var url = "http://" + window.location.hostname + "/onair/podcast/player/ws/search.php?q=" + var_search;

$.ajax({
			type: "GET",
			async: false,
			timeout: 5000,
			url: url,
			success:function(data)
			{

			  if (data.length !=0) {
			    var elements = "<div style=\"font-size:80%; float: right; text-align: right\"><em>flux RSS de cette recherche <a style=\"margin-left: 10px\" href=\"/onair/podcast/player/rss/?q=" + encodeURIComponent(var_search) + "\" title=\"actualités de " + var_search + "\"><img src=\"images/rss-small.png\" alt=\"flux rss\"/></a></em></div>";
			    elements += "<p>" + data.length + " résultats.</p>";
			    elements += "<ul>";			  
			    for(i = 0; i != data.length; ++i) {
			      var jour = data[i][0];
			      jour = jour.split("-");
			      jour = parseInt(jour[2], 10) + " " + m2Txt[parseInt(jour[1], 10) - 1] + " " + jour[0];
			      var heure = parseInt(data[i][1], 10);
			      elements += "<li>" + jour + ", " + heure + "h&nbsp;: <a href=\"/onair/podcast/player/?date=" + data[i][0] + "&amp;time=" + heure + "\">" + data[i][2] + "</a></li>";
			    }
			    elements += "</ul>";
			  }
			  else
			    var elements = "<p>Aucun résultat</p>";


			  var results = document.getElementById("results");
			  results.innerHTML = elements;

			  $("#searchBox_scroll").mCustomScrollbar("update");
			},
			error: function (textStatus, errorThrown) {
				message = document.getElementById("results");
				message.innerHTML = "Impossible d'accéder à l'outil de recherche";
            }
		});


		if (var_add)
		  window.history.pushState({ state: 'search', request: var_search}, 'Rechercher', '<?php echo $prefix_url;?>?search=' + var_search);

}

function getUrlFromDateTime(var_datetime) {
  return "?date=" + var_datetime[0] + '&time=' + parseInt(var_datetime[1]);
}
function getDateHumanFormat(var_datetime) {
  var m2Txt = [ "janvier", "février", "mars", "avril", "mai", "juin", "juillet", "août", "septembre", "octobre", "novembre", "décembre" ];
  var jour = var_datetime[0].split("-");
  return parseInt(jour[2], 10) + " " + m2Txt[parseInt(jour[1], 10) - 1] + " " + jour[0] + ", " + parseInt(var_datetime[1], 10) + "h";
}

function display_similaires(var_time, var_title) {
  if (document.getElementById('similaires_box').style.display == 'block') {
    document.getElementById('similaires_box').style.display = 'none';
  }
  else {


    document.getElementById('similaires_box').style.display = 'block';
    document.getElementById('similaires_box').style.width = "0";
    document.getElementById('similaires_box').style.height = "0";
    $('#similaires_box').animate({
    width: "+=700px",
    height: "+=320px"
  }, 100, function() {
    // Animation complete.
  });
    if (window.similBoxFirst === undefined) {
      $("#similaire_box_scroll").mCustomScrollbar();
    }
    window.similBoxFirst = true;
 
  }
}
function clear_similaires_box() {
    document.getElementById('similaires_box').style.display = 'none';
}


function update_reseaux_sociaux(url) {

$('.fb-like').attr('data-href',url);
$("#gpluswrapper").html('<div class="g-plusone" data-size="medium"></div>');

	$('#twitterwrapper').html('<a href="https://twitter.com/share" class="twitter-share-button" data-url="' + url + '" data-text="'+ document.title + ' ' + url +'">Tweet</a>');
	
    try{
	    gapi.plusone.render("plusone", { "href": url });
            twttr.widgets.load();
            FB.XFBML.parse();
            gapi.plusone.go();
        }catch(ex){}

}


//]]>
</script>
<style type="text/css">

</style>


<!-- load Google+ script :: this should go just before </body> tag -->
<script type="text/javascript">
  (function() {
	var po = document.createElement('script'); po.type = 'text/javascript'; po.async = true;
	po.src = 'https://apis.google.com/js/plusone.js';
	var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(po, s);
  })();
</script>

</head>


<body>

<!-- load Facebook script :: this should go right after <body> tag -->
<div id="fb-root"></div>
<script type="text/javascript">(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/fr_FR/all.js#xfbml=1";
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));</script>

			<!-- The jPlayer div must not be hidden. Keep it at the root of the body element to avoid any such problems. -->
			<div id="jquery_jplayer_1" class="cp-jplayer"></div>
<div id="main"><div id="fixed-size">
                        <!--<a href="http://www.campus-clermont.net/"><img src="image_aleatoire.php" alt="Radio Campus" /><br /></a>-->
                        
                        <a href="http://www.campus-clermont.net/"><img src="images/logo20ans.png " alt="Radio Campus"/></a>



			<div id="date_of_podcast">
				<?php echo "<p>".$fulldate."</p>"; ?>
				<div id="time">
				</div>
			</div>
			  <div id="hour_of_podcast">			
			  </div>
			<div id="center_description">
			  <div id="description_of_podcast">			
			  </div>
			  <div id="ecoutes_of_podcast">			
			  </div>
			</div>

			<div />
			<!-- The container for the interface can go where you want to display it. Show and hide it as you need. -->
			<div id="date_prev">
				<?php
					if($date!="2014-04-29")
					{

						echo "<a href='".$prefix_url."?date={$datprev}'>";
						echo "<img src='./skin/circle.skin/prev.png'/>";
						echo "<p>".strftime("%A %e %B %Y",strtotime($datprev))."</p></a>";
					}
				?>
	
			</div>

			<div id="campus_player" <?php 
  if (!isset($time) || !$time || $time == "") {
      echo 'class="hidden"';
      }; ?>>
				<div id="cp_container_1" class="cp-container">
					<div id="image-podcast"><img id="img-pst" src="<?php if ($time != "") {
					  echo $podcasts[$time]->image;
					}?>" /></div>
					<div class="cp-buffer-holder" id="buffer-holder"> <!-- .cp-gt50 only needed when buffer is > than 50% -->
						<div class="cp-buffer-1"></div>
						<div class="cp-buffer-2"></div>
					</div>
					<div class="cp-progress-holder" id="progess-holder"> <!-- .cp-gt50 only needed when progress is > than 50% -->
						<div class="cp-progress-1"></div>
						<div class="cp-progress-2"></div>
					</div>
					<div class="cp-circle-control" id="main-control"></div>
					<ul class="cp-controls" id="main-play">
						<li><a class="cp-play" tabindex="1">play</a></li>
						<li><a class="cp-pause" style="display:none;" tabindex="1">pause</a></li> <!-- Needs the inline style here, or jQuery.show() uses display:inline instead of display:block -->
					</ul>
				</div>
			</div>
			
			<div id="date_next">
				<?php
					if($date!= date ("Y-m-d"))
					{

						echo "<a href='".$prefix_url."?date={$datnext}'>";
						echo "<img src='./skin/circle.skin/next.png'/>";
						echo "<p>".strftime("%A %e %B %Y",strtotime($datnext))."</p></a>";
					}
				?>
	
			</div>
			<div id="dl_podcast">
			</div>
			<div id="dl_similaires">
			</div>
			<div id="similaires_box" class="popupBox">
			      <div class="close" onclick="clear_similaires_box()">X</div>
			      <h2><span>Émissions similaires</span></h2>
			      <div id="similaire_box_scroll" class="fenetre-scroll">
			      <div id="similaire_results" class="fenetre-contenu">
			      </div>
			      </div>
			</div>
			<div id="titres_musique">
<?php 
  for($i = 0; $i != 24; $i++) {
    if (isset($podcasts[$i]) && !$podcasts[$i]->ok)  { ?>
      <div id="titres_musique<?php echo $i;?>" class="titres_musicaux fenetre">
      <div class="close" onclick="clear_previous()">X</div>
      <h2><span>Programmation musicale - <span id="titres_musique<?php echo $i;?>_title"></span></span></h2>
      <div class="scrollregion fenetre-scroll" id="titres_musique<?php echo $i;?>_scroll" ><?php 
	$podcasts[$i]->toMusicEntries();
      ?></div></div>
    <?php }
  }
?>		      
			</div>
			<div id="searchBox" <?php if (!isset($actionSearch)) { echo 'class="hidden"'; }?> >
			  <div class="close" onclick="clear_previous()">X</div>
			  <h2><span>Rechercher un podcast</span></h2>
			  <div class="scrollregion fenetre-scroll" id="searchBox_scroll">
				<input type="text" name="recherche" id="champsRecherche" value="<?php echo $actionSearch; ?>" onkeypress="pressSearch(event)" />
			    <div class="buttonRechercher" onclick="rechercher(true)">Rechercher</div>
			    <div id="results">
			    </div>
			  </div>
			</div>
			<div id="rssBox" class="hidden" >
			  <div class="close" onclick="clear_previous()">X</div>
			  <h2><span>Les flux RSS des podcasts</span></h2>
			  <div class="fenetre-scroll">
			    <p><a style="margin-right: 10px" href="/onair/podcast/player/rss/" target="_blank" title="Les derniers podcasts"><img src="images/rss-small.png" alt="flux rss"/></a> le flux RSS de tous les podcasts</p>
			    <p>Liste des flux RSS des émissions du jour&nbsp;:</p>
			    <ul>
			      <?php
				foreach($podcasts as $podcast) {
					  if ($podcast->ok && $podcast->mp3 != "") { ?>
					    <li><a style="margin-right: 10px" title="Tous les podcasts de l'émission <?php echo str_replace("\"", "&quot;", $podcast->title); ?>" href="http://<?php echo $_SERVER['HTTP_HOST'];?>/onair/podcast/player/rss/?q=<?php echo rawurlencode($podcast->title); ?>" target="_blank"><img src="images/rss-small.png" alt="flux rss"/></a> podcasts de l'émission <?php echo $podcast->title;?></li>
					  <?php } } ?>
			    </ul>
			    </div>
			  </div>		
			<div id="agendaBox" class="hidden" >
			  <div class="close" onclick="clear_previous()">X</div>
			  <h2><span>Changer de jour</span></h2>
			    <div class="fenetre-scroll"><div id="datepicker"></div>
				  <div id="godate" title="Aller au jour sélectionné">Aller au jour</div>
				  <a class="ui-button" href="/onair/podcast/player/" title="aller à aujourd'hui">Aujourd'hui</a>
			    </div>
			  </div>
			<div id="contactBox" class="hidden" >
			  <div class="close" onclick="clear_previous()">X</div>
			  <h2><span>Signaler un problème</span></h2>
			    <div class="fenetre-scroll">
			    <p>Utiliser le formulaire ci-dessous pour signaler un problème de fonctionnement du podcast, ou envoyer un courrier à <a href="mailto:webmaster@clermont.radio-campus.org">webmaster@clermont.radio-campus.org</a>.</p>
			    <form action="" method="post" id="mycontactform" >
<label for="name">Nom&nbsp;:</label><br />
<input type="text" name="name" id="name" /><br />
<label for="email">Courriel&nbsp;:</label><br />
<input type="text" name="email" id="email" /><br />
<label for="raison">Problème rencontré&nbsp;:</label><br />
<select id="raison" name="raison">>
  <option>Le podcast ne fonctionne pas</option>
  <option>Le podcast n'est pas complet</option>
  <option>L'émission annoncée n'est pas la bonne</option>
  <option>La durée affichée est incohérente</option>
  <option>L'affichage est cassé</option>
  <option>Problème de navigation</option>
  <option>Autre...</option>
</select><br />
<label for="message">Message&nbsp;:</label><br />
<textarea name="message" id="message"></textarea><br />
<input type="button" value="Envoyer" id="submit" /><div id="success" style="color:red;"></div>
</form>
		    </div>
			  </div>
			</div>

<div id="time_list">
			
			<?php
			
				
				
				$first = 0;
				for($i = 0; $i != 24; $i++) {
				    if (isset($podcasts[$i])) {
				      $first = $i;
				      break;
				  }
				}

				$limit = 24;
				$aujourdhui = date ("Y-m-d") == $date;
				$now = intval(date("H"));
				if ($aujourdhui) {
				  if ($now < 23) {
				    $limit = $now;
				    if (!isset($podcasts[$i]) || !($podcasts[$i]->ok))
				      $limit = $now + 1;
				  }
				}
		

				$taille = 0;
				$first = false;
				$notempty = 0;
				//print_r($podcasts);
				for($i = 0; $i != $limit; $i++) {
				
				  if (isset($podcasts[$i]) || $first) {
				    if ($notempty == 0) {
				      if (($first && !isset($podcasts[$i])) || $podcasts[$i]->duration == 1)
					$taille  +=  40;
				      else {
					$taille  +=  73;
					$notempty = $podcasts[$i]->duration;
				      }
				    }
				    if ($notempty != 0)
				      $notempty = $notempty - 1;
				    $first = true;
				  }
				}
				if ($aujourdhui) $taille = $taille + 115;
				echo "<ul id=\"tracklist\" style=\"width: ".$taille."px\">";
				$first = false;
				$notempty = 0;
				for($i = 0; $i != $limit; $i++) {
				    if (isset($podcasts[$i])) {
					echo "<li id=\"time".$i."\"";
					if ($podcasts[$i]->duration > 1)
					  echo " class=\"large\"";
					echo ">";
					$podcasts[$i]->toItem($date, $ecoutes);
					$first = true;
					$notempty = $podcasts[$i]->duration - 1;
					echo "</li>\n";
				    }
				    else if ($first && ($notempty == 0)) {
					echo "<li id=\"time".$i."\">";
					Podcast::emptyToItem($i);
					echo "</li>\n";
				    }
				    else if ($notempty != 0) {
				      $notempty = $notempty - 1;
				    }
				}
				if ($aujourdhui) {
				    if ($limit != $now + 1)
				      echo '<li class="suspension"><p title="Patientez un peu, le podcast sera bientôt en ligne...">...</p></li>';
				    echo '<li id="directlink"><p onclick="play_live(true, true)"';
				    echo ' onmouseover="document.getElementById(\'direct\').style.display=\'block\';"  onmouseout="document.getElementById(\'direct\').style.display=\'none\';" >direct</p>';
				    echo "<div id=\"direct\" class=\"time_popup\">Écouter la radio en direct</div>";
				}
				echo "</ul>";

				echo "<div style=\"clear:both\"></div>";
		
			?>
			</div></div>
			<div id="tools">
<div id="reseauxsociaux">
<div class="fb-like" data-send="false" data-layout="button_count" data-width="90" data-show-faces="false" data-font="arial"></div>
<div id="twitterwrapper"><a href="https://twitter.com/share" class="twitter-share-button">Tweet</a></div>
<div id="gpluswrapper"><div class="g-plusone" data-size="medium"></div></div>
</div>
<div id="wrapper-tools">
  <a id="buttonRSS" class="button" title="S'abonner aux podcasts de Radio Campus Clermont-Ferrand" onclick="open_rss()" alt="S'abonner (RSS)"> </a>
  <p id="buttonSearch" class="button" title="Rechercher" onclick="open_search()" alt="rechercher"></p>
  <p id="buttonAgenda" class="button" title="Choisir un jour" onclick="open_agenda()" alt="choisir un jour"></p>
  <a id="buttonDirect" class="button" href="/onair/podcast/player/?live=true" title="Aller au direct" alt="Aller au direct">direct</a>
  </div>
 <div id="menu-pied">
 <div class="colonne">
 <h3>Radio Campus Clermont-Ferrand</h3>
  <ul>
     <li><a href="http://campus-clermont.net">L'actualité</a></li>
     <li><a href="http://www.campus-clermont.net/bons-plans-venir">Les bons plans</a></li>
     <li><a href="http://campus-clermont.net/les-emissions.html">Toutes les émissions</a></li>
  </ul>
  </div>
  <div class="colonne">
 <h3>L'association</h3>
  <ul><li><a href="http://campus-clermont.net/appel-a-projet">Rejoignez Radio Campus&nbsp;!</a></li>
  </ul>
  </div>
  <div class="colonne">
 <h3>Le podcast</h3>
  <ul>
     <li><a onclick="open_rss()" href="#rssBox">S'abonner aux podcasts (RSS)</a></li>
     <li><a onclick="open_search()" href="#searchBox">rechercher un podcast...</a></li>
     <li><a onclick="open_agenda()" href="#agendaBox">changer de jour...</a></li>
     <li><a href="/onair/podcast/player/?live=true">écouter le direct</a></li>
     <li><a onclick="open_contact()" href="#contactBox">signaler un problème...</a></li>
  </ul>
  </div>
 </div>
 <div id="pied">
  <p>Radio Campus Clermont-Ferrand - 16 rue Degeorges 63000 Clermont-Ferrand <br />
    tél: 04.73.140.158 - fax: 04.73.902.877 - mail: <a href="mailto:antenne@clermont.radiocampus.org">antenne@clermont.radiocampus.org</a><br />
    <a href="/mentions-legales.html">mentions légales</a></p>
  <p style="text-align: right;"><a href="/onair/podcast/admin/?date=<?php echo $date; ?>" style="text-decoration: none; color: #888;">administration</a></p>
 </div>
</div>
</body>

</html>