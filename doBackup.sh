#!/bin/sh

# Title:               WEB3 Backup Tool
# Author:              Constantin-Emil MARINA
# Copyright holder:    SC EXTEON SRL
# Homepage:            http://www.exteon.ro/en/products/programming-tools/web3Backup
# License:             Creative Commons Attribution-NonCommercial-ShareAlike (CC BY-NC-SA 4.0)
# License home:        http://creativecommons.org/licenses/by-nc-sa/4.0/
#  
# This application is distributed under the terms of the Creative Commons Attribution-NonCommercial-ShareAlike 4.0 
# license
#  
# No warranties, express or implied

#   Sample script to mount a NFS share and run a backup. Note tthe following points:
#   - a backup mount should not be left mounted when not in use, in the off chance that someone might run
#     rm -rf /
#   - mounts must be made by root
#   - the backup script should NEVER be run as root (hence the sudo command)
#   - this script will not run the backup if the NFS is already mounted (ie, someone mounted it to do a
#     restore operation, and it is not a good time to delete old archives).

mount -t nfs -o tcp nfshost:/nfsdir /remoteBackup || exit $?
sudo -u restricteduser php backup.php
ret_code=$?
umount /remoteBackup || ret_code=$?
exit $ret_code

