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
#  $Revision$
#  $Date$
#  $LastChangedBy$
#

  $RRD_DIR = "rrds/";
  $DATE_FORMAT = "Y-m-d H:i:s";

  $run_mode = "web";
  if (isset($_SERVER["TERM"])) {
    $run_mode = "cli";
  }

  require('functions.php');

  if ($run_mode == "web") {
    //We need to masquerade as an image of the png variety
    ob_start();
    header("Content-type: image/png");
    ob_end_clean();
    session_write_close();
  }

  //Get list of probe ids to graph
  if (isset($_REQUEST['ids']) && strlen($_REQUEST['ids']) > 0) {
    $ids = $_REQUEST['ids'];
    $ids = split(',', $ids); //arrays are better than comma seperated strings :-P
  }
  else {
    if ($run_mode == "web") {
      readfile("icons/status/image-missing.png");
    }
    else {
      echo "No probes specified";
    }
    exit();
  }

  //Time range of graph
  $range = null;

  //Casting the timestamps as integers is my lazy form of input validation
  //Get start timestamp and check validity, abort nicely if bad
  if (isset($_GET['start'])) {
    $t_start = (int) $_GET['start'];
    $range .= "-s $t_start ";
  }
  else {
    if ($run_mode == "web") {
      readfile("icons/status/dialog-error.png");
    }
    else {
      echo "Start time not specified";
    }
    exit();
  }

  //Get end timestamp and check validity, abort nicely if bad
  if (isset($_GET['end'])) {
    $t_end = (int) $_GET['end'];
    $range .= "-e $t_end ";
  }
  else {
    if ($run_mode == "web") {
      readfile("icons/status/dialog-warning.png");
    }
    else {
      echo "Start time not specified";
    }
    exit();
  }

  //Abort if start is before or the same as end
  if ($t_start >= $t_end) {
    if ($run_mode == "web") {
      readfile("icons/status/image-loading.png");
    }
    else {
      echo "Time range inverted";
    }
    exit();
  }

  if (isset($_GET['mode'])) {
    $mode = $_GET['mode'];
  }
  else {
    $mode = null;
  }
  
  $trend = false;

  if (isset($_GET['trend'])) {
    $trend = true;
  }

  $width = 300;

  if (isset($_GET['width'])) {
    $width = $_GET['width'];
  }

  //other usefuls
  $colours = array(
    'cc0000',
    '73d216',
    '3465a4',
    'f57900',
    '75507b',
    'edd400'
  );
  $alpha = 'dd';

  //Trend window size, based on size of view
  $window = round(($t_end - $t_start) / 120);

  $defs = null; //need this later to build the graph definitions

  $j = sizeof($ids);

  if ($mode == "baseline") {
    $base_id = array_shift($ids);
    $base_rrd="$RRD_DIR$base_id.rrd";
    $defs .= " DEF:$base_id=$base_rrd:temp:AVERAGE";
    $colour = array_shift($colours);
    $defs .= " HRULE:0#$colour$alpha:'$base_id\t<b>(Baseline Probe)</b>\\n'";
  }
  else {
    $base_id = null;
  }

  foreach ($ids as $i => $id) {
    $label = $id;
    $rrd="$RRD_DIR$id.rrd";  //datasource
    $colour=$colours[$i]; //grab colour
    //describe each probe's graph drawing here
    $defs .= " DEF:$id=$rrd:temp:AVERAGE";

    //Apply baseline if present
    if ($base_id != null) {
      $defs .= " CDEF:$id-norm=$id,$base_id,-";
      $id = $id."-norm";
    }

    //Scale airflow sensors
    if (strpos($id, 'AIRFLOW') !== false) {
      $defs .= " CDEF:$id-scaled=$id,2,/";
      $id .= "-scaled";
    }

    //Use trendline?
    if ($trend) {
      //Calculate trend line
      $defs .= " CDEF:$id-trend=$id,$window,TREND";

      //Switch to trendline
      $id .= "-trend";
    }

    //Plot line
    $defs .= " LINE:$id#$colour$alpha:'$label\t'";

    //Min & Max
    $defs .= " GPRINT:$id:LAST:'<b>Now</b>\: %.0lf\t'";
    $defs .= " GPRINT:$id:AVERAGE:'<b>Mean</b>\: %.0lf\t'";
    $defs .= " GPRINT:$id:MIN:'<b>Min</b>\: %.0lf\t'";
    $defs .= " GPRINT:$id:MAX:'<b>Max</b>\: %.0lf\\n'";

    //Draw nodata markers
    $defs .= " CDEF:$id-nodata=$id,UN,$j,*,2,/";
    $defs .= " AREA:$id-nodata#7F7F7F7F";

    $j--;
  }

  $bms_colours = array(
    'c4a000',
    '5c3566',
    'ce5c00',
    '204a87',
    '4e9a06',
    'a40000'
  );

  //Insert BMS Alerts
#  $defs .= " COMMENT:\"---- Events ----\"";
#  $events = file_get_contents("/tmp/snmpee.json");
#  $events = json_decode($events);
#  foreach ($events as $i => $e) {
#    $t = $e[0];
#    $d = $e[1];
#    $n = $e[2];
#    $defs .= " VRULE:".(strtotime($t))."#".$bms_colours[$i].":\"".$n."\t".$d."\"";
#  }
  
  //draw the graph to stdout, which is this page :P
  $cmd= ("rrdtool graph - "
    ." -a PNG"                 //Output as an PNG Image
    ." --pango-markup"         //Render text with Pango
#    ." --border 1"            //Disable border
    ." -R light"               //Slight hinting and anti-aliasing
    ." -T 64"                  //Set tabstop width
    ." -n AXIS:8:Helvetica"
    ." -n UNIT:8:Helvetica"
    ." -n LEGEND:7:Helvetica"
    ." -c BACK#ffffff"       //Background colour
    ." -c CANVAS#ffffff"     //Graph Background colour
    ." -c SHADEA#ffffff"     //Top and left shade
    ." -c SHADEB#ffffff"     //Bottom and right shade
    ." -c FONT#2e3436"       //Font colour
    ." -c AXIS#2e3436"       //Axis colour
    ." -c ARROW#2e3436"      //Axis arrow colour
    ." -c MGRID#babdb6"        //Major grid colour
    ." -c GRID#babdb6"         //Minor grid colour
    ." -c FRAME#2e3436"      //Frame colour
#    ." -t '"/*.date($DATE_FORMAT, $start)*/."2008-08-08 23:23 to 34538945'"
#    ." -E"                    //Sloping edges
    ." -h 480"                 //Height
    ." -w $width"              //Width
    ." --full-size-mode"       //Specify image size
    ." -u 50"                  //Upper limit of graph
    ." -l 0"                   //Lower limit of graph
    ." $range"                 //Time range
    ." -v '<b>Temperature</b> °C        <b>Humidity</b> %        <b>Current</b> A'" //Vertical axis label
    ." --right-axis 2:0" //Vertical axis label
    ." --right-axis-label '<b>Airflow</b> %'" //Vertical axis label
    ."$defs"); 

  if ($mode == null) {
    $cmd .= " -r"; //Rigid limits
  }

  //execute
  system($cmd);
?>
