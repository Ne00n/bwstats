#!/usr/bin/python3
import subprocess, requests, json, time, os

print("Loading config")
with open(f'{os.path.dirname(os.path.realpath(__file__))}/config.json') as f:
    config = json.load(f)

data = {}
if config['settings']['vnstat']:
    vnstat = subprocess.run("vnstat --json", stdin=None, stdout=subprocess.PIPE, stderr=subprocess.PIPE, shell=True)
    data['vnstat'] = json.loads(vnstat.stdout.decode('utf-8'))

headers = {'content-type': 'application/json', 'Accept-Charset': 'UTF-8'}
if config['settings']['storj']:
    try:
        r = requests.get("http://localhost:14002/api/sno/",headers=headers,allow_redirects=False)
        if (r.status_code == 200): data['storj'] = r.json()
    except Exception as e:
        print(e)

payload = {"token":config['token'],"name":config['name'],"data":{}}
for key,raw in data.items():
    if key == "vnstat":
        if not "vnstat" in payload['data']: payload['data']['vnstat'] = {"rx":0,"tx":0}
        for interface in raw['interfaces']:
            payload['data']['vnstat']['rx'] += interface['traffic']['month'][0]['rx']
            payload['data']['vnstat']['tx'] += interface['traffic']['month'][0]['tx']
    elif key == "storj":
        if not "storj" in payload['data']: payload['data']["storj"] = {"storage":0,"storageAvailable":0,"bandwidth":0}
        payload['data']['storj']['storage'] = data['storj']['diskSpace']['used']
        payload['data']['storj']['storageAvailable'] = data['storj']['diskSpace']['available']
        payload['data']['storj']['bandwidth'] = data['storj']['bandwidth']['used']

print(payload)
payload = json.dumps(payload)
for run in range(4):
    try:
        r = requests.post(config['api'], data=payload, headers=headers,allow_redirects=False)
        if (r.status_code == 200): 
            print("Success")
            break
    except Exception as e:
        print(e)
    time.sleep(2)