#!/usr/bin/python
# coding=utf8

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

#Required modules from Python Standard Library
import commands, os, sys
from datetime import datetime

#Try to import rrdtool module
try:
  import rrdtool
except:
  print("ERROR: Unable to import the rrdtool module, is python-rrdtool installed?")
  sys.exit(1)

#Fall back to simplejson for versions of python < 2.5 (simplejson requires seperate install)
try:
  import json
except:
  try:
    import simplejson as json
  except:
    print("ERROR: Unable to find a usable json module, is simplejson installed?")
    sys.exit(1)

#ARTEMIS Components
from artemis_core import *
from nodetypes import base

#Try to load config module
try:
  from artemis_config import *
except:
  print("ERROR: Unable to import the artemis configuration module, have you created artemis_config.py?")
  sys.exit(1)

# Setup base nodes from store
base_nodes = []

for n in session.query(Node).all():
  base_nodes.append(load_class(n.module, n.object)(n.ip))

# Setup sensors from store
sensors = {}

for p in session.query(Probe).all():
  sensors[p.id] = [p.name, float(p.x), float(p.y), float(p.w), float(p.h)]

#Configuration
this_dir = os.path.dirname(os.path.realpath( __file__ )) + "/"
rrd_dir  = this_dir + rrd_dir

print("Starting run...")

#Start collection
g = grabData(base_nodes)

snapshot_list = [];

print("---- Data grab complete ----")

for serial, value, units in g:
  print(str(datetime.today()) + ": Found sensor " + serial + " with value " + str(value) + units)
  rrd = str(rrd_dir + serial + rrd_ext)

  if not os.path.isfile(rrd):
    #create rrd if none exists
    print("Creating new RRD " + rrd)
    rrdtool.create(rrd, "--step", "60", "DS:temp:GAUGE:120:0:100", "RRA:AVERAGE:0.5:1:10080", "RRA:AVERAGE:0.5:60:720")

  #update data
  rrdtool.update(rrd, "N:" + str(value))

  #store latest values
  try:
    (n, x, y, h, w) = sensors[serial]
  except:
    (n, x, y, h, w) = ("Auto-detected", 0, 0, 0, 0)
    session.add(Probe(serial, n, x, y, h, w))

  session.commit()

  row = [serial, value, n, x, y, h, w]

  snapshot_list.append(row)


#Dump data
dump_prep = {
  "config" : config,
  "probes" : snapshot_list,
}

try:
  file_json_dump = open(this_dir + "data/data-dump.json", "w")
  json.dump(dump_prep, file_json_dump)
except:
  print("Error while writing data dump file - %s" % sys.exc_info()[0])
