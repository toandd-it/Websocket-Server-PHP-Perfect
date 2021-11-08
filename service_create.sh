sudo touch /usr/lib/systemd/system/phpWs.service
echo '[Unit]' >> /usr/lib/systemd/system/phpWs.service
echo 'Description=PBX log service' >> /usr/lib/systemd/system/phpWs.service
echo 'After=network.target' >> /usr/lib/systemd/system/phpWs.service
echo '' >> /usr/lib/systemd/system/phpWs.service
echo '[Service]' >> /usr/lib/systemd/system/phpWs.service
echo 'ExecStart=/usr/local/lsws/lsphp73/bin/php /var/www/public_html/ws.zetadmin.com/wss.php' >> /usr/lib/systemd/system/phpWs.service
echo 'Restart=always' >> /usr/lib/systemd/system/phpWs.service
echo 'User=nobody' >> /usr/lib/systemd/system/phpWs.service
echo '' >> /usr/lib/systemd/system/phpWs.service
echo '[Install]' >> /usr/lib/systemd/system/phpWs.service
echo 'WantedBy=multi-user.target' >> /usr/lib/systemd/system/phpWs.service
sudo chmod 644 /usr/lib/systemd/system/phpWs.service

sudo ln -s /usr/lib/systemd/system/phpWs.service /etc/systemd/system/
sudo ls -l /usr/lib/systemd/system/phpWs.service

sudo firewall-cmd --zone=public --add-port=8090/tcp --permanent
sudo firewall-cmd --reload

sudo systemd-analyze verify phpWs.service 
sudo systemctl daemon-reload
sudo systemctl start phpWs.service