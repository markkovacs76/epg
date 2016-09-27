#!/bin/bash
XML_FILE=guide.xml
LOG_FILE=log.txt
EPG_GRABBER_DIR=/var/www/epg/generate
WEBROOT_DIR=/var/www/epg

cd ${EPG_GRABBER_DIR}
php generate_epg_from_musortv.php
if [ -f ${XML_FILE}.gz ];
then
  rm ${XML_FILE}.gz
fi
gzip -k ${XML_FILE}
cp ${XML_FILE} ${WEBROOT_DIR}
cp ${XML_FILE}.gz ${WEBROOT_DIR} 
cp ${LOG_FILE} ${WEBROOT_DIR}
