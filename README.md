# bwstats

**client**<br />
```
apt-get install git python3 python3-pip -y && pip3 install requests
adduser bwstats --disabled-login
su bwstats
cd; git clone https://github.com/Ne00n/bwstats.git; cd bwstats/client
cp config.example.json config.json
#edit config.json
exit
crontab -u bwstats -l 2>/dev/null | { cat; echo "5 * * * *  /home/bwstats/bwstats/client/upload.py > /dev/null 2>&1"; } | crontab -u bwstats -
```