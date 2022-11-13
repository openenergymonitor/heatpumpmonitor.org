#!/bin/bash

SCRIPT=$(realpath "$0")
cd $(dirname "$SCRIPT")
echo $(dirname "$SCRIPT")

URL='https://docs.google.com/spreadsheets/d/e/2PACX-1vQ-eqigAmjwwSIc6snCYTWRYZW6wsVK98fsJ8kn4aiG_pDw8qgpc4y_ZkiHC_OtWpchDCk1nBwxza8W/pub?gid=447603213&single=true&output=tsv'

FILE='data.tsv'
TEMP='temp.tsv'

wget -q -nv $URL -O $TEMP

if diff -q $FILE $TEMP > /dev/null
then
  echo "no change"
  # no change
  rm $TEMP
else
  # new content
  echo "new content"
  mv $TEMP $FILE
  cp $FILE www/$FILE
fi

