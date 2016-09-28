#!/bin/bash
XML_FILE=guide.xml
LOG_FILE=log.txt
EPG_GRABBER_DIR=/var/www/epg/generate
WEBROOT_DIR=/var/www/epg

cd ${EPG_GRABBER_DIR}
php generate_epg_from_musortv.php
if [ $? -ne 0 ];
then
  echo "ERROR: EPG generation was not successful!"
  exit $?
fi

if [ -f ${XML_FILE}.gz ];
then
  rm ${XML_FILE}.gz
fi

gzip -k ${XML_FILE}
if [ $? -ne 0 ];
then
  echo "ERROR: EPG compression was not successful!"
  exit $?
fi

# Copy generated files to web location
cp ${XML_FILE} ${WEBROOT_DIR}
cp ${XML_FILE}.gz ${WEBROOT_DIR} 
cp ${LOG_FILE} ${WEBROOT_DIR}
