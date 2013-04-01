#!/usr/bin/env python

import optparse
import os
from pprint import pprint
import sys

parser = optparse.OptionParser()
parser.add_option("-v", "--verbose", help="increase output verbosity",
                    action="store_true")
(options, args) = parser.parse_args()

toppath = os.path.abspath('..')
modulespath = os.path.join(toppath, "modules")
wsmodpath = os.path.join(toppath, "ws-mod")
gallerymodpath = os.path.join(toppath, "gallery3-contrib", "3.0", "modules")

if options.verbose:
  print "toppath=%s" % toppath
  print "modulespath=%s" % modulespath
  print "wsmodpath=%s" % wsmodpath
  print "gallerymodpath=%s" % gallerymodpath

if not os.path.isdir(modulespath):
  raise Exception("Missing gallery3 module path %s" % modulespath)
  
if not os.path.isdir(wsmodpath):
  raise Exception("Missing ws-mod checkout module path %s" % wsmodpath)
  
if not os.path.isdir(gallerymodpath):
  raise Exception("Missing gallery3-contrib checkout module path %s" % gallerymodpath)

module_dirs = {}

for root, dirs, files in os.walk(gallerymodpath):
  if root == gallerymodpath:
    for module_dir in dirs:
      module_dirs[module_dir] = os.path.join(root, module_dir)
      
for root, dirs, files in os.walk(wsmodpath):
  if root == wsmodpath:
    for module_dir in dirs:
      if module_dir in module_dirs:
        print "ws-mod/%s module overrides contrib" % module_dir
      module_dirs[module_dir] = os.path.join(root, module_dir)
      
for module_name, module_dir in module_dirs.iteritems():
  src_path = os.path.join(modulespath, module_name)
  dest_path = os.path.join(module_dir)
  if os.path.islink(src_path):
    if options.verbose: print "Removing link %s" % src_path
    os.remove(src_path)
  if os.path.isfile(src_path):
    if options.verbose: print "Not overriding module directory %s" % src_path
  else:
    if options.verbose: print "Symlinking %s to %s" % (src_path, dest_path)
    os.symlink(dest_path, src_path)

