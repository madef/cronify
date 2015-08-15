#!/bin/bash

salt=cronify;

while true
do
    if (( $(ps aux | grep $salt'_data' | grep -v grep | wc -l) != 1))
    then
        echo Start srv/data.php
        php srv/data.php ${salt}_data &
    fi

    if (( $(ps aux | grep $salt'_buffer' | grep -v grep | wc -l) != 1))
    then
        echo Start srv/buffer.php
        php srv/buffer.php ${salt}_buffer &
    fi

    sleep 5;
done
