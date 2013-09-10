<?php
#
#  Copyright Science and Technology Facilities Council, 2009.
#  
#  This file is part of ARTEMIS.
#  
#  ARTEMIS is free software: you can redistribute it and/or modify
#  it under the terms of the GNU General Public License as published by
#  the Free Software Foundation, either version 3 of the License, or
#  (at your option) any later version.
#  
#  ARTEMIS is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU General Public License for more details.
#  
#  You should have received a copy of the GNU General Public License
#  along with ARTEMIS. If not, see <http://www.gnu.org/licenses/>.
#
#  $Revision: 9148 $
#  $Date: 2011-09-28 14:22:35 +0100 (Wed, 28 Sep 2011) $
#  $LastChangedBy: tkk76468@FED.CCLRC.AC.UK $
#
?>
<?php

  require("prepost.inc.php");

  pre();

?>
  <nav class="navbar navbar-default" role="navigation">
    <div class="navbar-header">
      <a class="navbar-brand" href="./"><img src="images/logo-header.png" alt="ARTEMIS - Almost Real-Time Enviromental Monitoritoring &amp; Information System"></a>
    </div>
    <div class="collapse navbar-collapse navbar-ex1-collapse">
      <ul class="nav navbar-nav">
        <li class="active"><a href="#"><a href=".">Display</a></li>
        <li><a href="#"><a href="admin.php">Admin</a></li>
      </ul>
    </div>
  </nav>
  <div id="controls">
    <form action="#" method="get" onchange="updateProbesJSON();">
      <input type="checkbox" name="temperature" id="inputTemperature" checked="checked" />Temperature
      <input type="checkbox" name="humidity"    id="inputHumidity" />Humidity
      <input type="checkbox" name="airflow"     id="inputAirflow" />Airflow
      <input type="checkbox" name="current"     id="inputCurrent" />Current
    </form>
  </div>
<?php
  //Get size of room from image
  $w = 600;
  $h = 600;
  $s = "";
  if (file_exists('rooms/room.png')) {
    $s = getimagesize('rooms/room.png');
    $w = $s[0];
    $h = $s[1];
    if (!isset($_REQUEST['nobg'])) {
      $s = " background-image: url('rooms/room.png');";
    }
  }

  echo "    <div id=\"divRoom\" style=\"width: ${w}px; height: ${h}px; $s\"></div>";
?>
<?php flush(); ?>
    <div id="divGraph">
      <form role="form" class="form-inline">
        <div class="form-group">
          <label>Start:</label>
          <input class="form-control" type="text" size="8" name="date-start" id="inputDateStart" value="<?php echo Date("Y-m-d", time()-604800); ?>" />
        </div>
        <div class="form-group">
          <label>End:</label>
          <input class="form-control" type="text" size="8" name="date-end"   id="inputDateEnd"   value="<?php echo Date("Y-m-d"); ?>" />
        </div>
        <div class="form-group">
          <label>Baseline Mode</label>
          <input  type="checkbox"  name="baseline"        id="inputBaseline"  title="Use first probe as baseline and normalise other probes against it." onchange="updateGraph()">
        </div>
        <div class="form-group">
          <label>Auto Trend</label>
          <input  type="checkbox"  name="trend"           id="inputTrend"     title="Automatically smooth noisy data to a trendline." onchange="updateGraph()">
        </div>
      </form>
      <div id="minfo">&nbsp;</div>
      <img id="imgGraph" src="drawgraph.php" alt="Select probes to view" />
      <script type="text/javascript">
        function update() {
          $.getJSON("data/data-dump.json", callbackJSON);
        }
        setInterval('update()',30000);
        update();

        $( "#inputDateStart" ).change(updateGraph);
        $( "#inputDateStart" ).datepicker({
          dateFormat : "yy-mm-dd",
        });
        
        $( "#inputDateEnd" ).change(updateGraph);
        $( "#inputDateEnd" ).datepicker({
          dateFormat : "yy-mm-dd",
        });

        $("#imgGraph").click(function(stuff) {
          if (ids.length > 0) {
            $u = stuff.target.src;
            $u = $u.replace(/width=[0-9]+/g, "width=" + window.innerWidth);
            $u = $u.replace(/height=[0-9]+/g, "height=" + window.innerHeight);
            window.location = $u;
          }
        });

        var minfo_vis = false;

        $("#imgGraph").mousemove(function(e) {
          if (meta != null) {
            var x = e.pageX - this.offsetLeft - meta["graph_left"];
            var y = meta["graph_height"] - (e.pageY - this.offsetTop - meta["graph_top"]);
            if (x >= 0 && y >= 0 && x <= meta["graph_width"] && y <= meta["graph_height"]) {

              x = meta["graph_start"] + ((x / meta["graph_width"]) * (meta["graph_end"] - meta["graph_start"]));
              x = moment.unix(x).format('YYYY-MM-DD HH:mm') 

              y = meta["value_min"]   + ((y / meta["graph_height"]) * meta["value_max"]);
              y = y.toFixed(1);

              $("#minfo").html(x + "<br />" + y + "C");
              if (! minfo_vis) {
                $("#minfo").show();
                minfo_vis = true;
              }
              $("#imgGraph").css("cursor", "crosshair");
            }
            else {
              if (minfo_vis) {
                $("#minfo").hide();
                minfo_vis = false;
              }
              $("#imgGraph").css("cursor", "");
            }
          }
        });
      </script>
    </div>
<?php
  post();
?>
