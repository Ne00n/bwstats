import subprocess, requests, json, time

print("Loading config")
with open('config.json') as f:
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

payload = {"token":config['token']}
for key,raw in data.items():
    if key == "vnstat":
        if not "vnstat" in payload: payload['vnstat'] = 0
        for interface in raw['interfaces']:
            payload['vnstat'] += interface['traffic']['month'][0]['rx']
            payload['vnstat'] += interface['traffic']['month'][0]['tx']
    elif key == "storj":
        if not "storj" in payload: payload["storj"] = {"storage":0,"bandwidth":0}
        payload['storj']['storage'] = data['storj']['diskSpace']['used']
        payload['storj']['bandwidth'] = data['storj']['bandwidth']['used']

print(payload)
for run in range(4):
    try:
        payload = json.dumps(payload)
        r = requests.post(config['api'], data=payload, headers=headers,allow_redirects=False)
        if (r.status_code == 200): print("Success")
    except Exception as e:
        print(e)
    time.sleep(2)