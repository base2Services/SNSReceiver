#!/bin/bash
sleep $4
ssh -t -t -i $2 $3@$1 'sudo /usr/sbin/service nagios3 reload'