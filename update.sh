#!/bin/sh

current_path=`dirname $(readlink -f $0)`

#从开发目录复制到本目录
current_dev=/srv/http/public_html/forms/wp-content/themes/twentythirteen
cp $current_dev/page-varnish-purge.php $current_path
cp $current_dev/page-customize-list.php $current_path
cp $current_dev/page-recommend-list.php $current_path
cp $current_dev/page-user-counter.php $current_path
cp $current_dev/client-user-counter.js $current_path
cp $current_dev/page-config.php $current_path
cp $current_dev/page-functions.php $current_path
cp $current_dev/page-lottery.php $current_path
cp $current_dev/page-exec-urls.php $current_path

#更新到github备份
git add .
git commit -a -m 'normal backup'
git push
