#!/bin/sh

current_path=`dirname $(readlink -f $0)`

#从开发目录复制到本目录
current_dev=/srv/http/public_html/forms/wp-content/themes/twentythirteen
cp $current_dev/page-customize-list.php $current_path
cp $current_dev/page-user-counter.php $current_path
cp $current_dev/client-user-counter.js $current_path

#更新到github备份
git add .
git commit -m 'normal backup'
git push
