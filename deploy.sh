#!/bin/sh

current_path=`dirname $(readlink -f $0)`

#从开发目录复制到本目录
current_dev=/srv/http/public_html/forms/wp-content/themes/twentythirteen
cp $current_dev/page-customize-list.php $current_path
cp $current_dev/page-user-counter.php $current_path
cp $current_dev/client-user-counter.js $current_path

#从本目录复制到其他wordpress站点
deploy_path=/srv/http/public_html/gof2/wp-content/themes/twentyeleven
cp $current_path/page-customize-list.php $deploy_path
cp $current_path/page-user-counter.php $deploy_path
cp $current_path/page-config.php.sample $deploy_path
cp $current_path/client-user-counter.js $deploy_path

