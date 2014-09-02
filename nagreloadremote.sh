#!/bin/bash
sleep $4
ssh -t -t -i $2 $3@$1 'sudo /etc/init.d/icinga reload'