import sys
import re
import json
import requests

# ANSI escape codes for colors
red = "\033[0;31m"
green = "\033[0;32m"
reset = "\033[0m"  # Reset to default color

target = sys.argv[1]
configFile = sys.argv[2]

# Fetching headers using requests library
response = requests.head(target)

if response.status_code != 200:
    print("Failed to fetch headers.")
    sys.exit(1)

headerContentArray = {}
for key, value in response.headers.items():
    headerArray = {}
    headerArray[0] = key.lower()
    headerArray[1] = value.strip().lower()
    multiValues = None
    singleValueKeyPair = None
    if ',' in headerArray[1]:
        multiValues = [val.strip() for val in headerArray[1].split(',')]
    if '=' in headerArray[1]:
        a = headerArray[1].split('=', 1)
        if len(a) == 2:
            abrevValue = a[1].split(';', 1)
            if len(abrevValue) > 0:
                singleValueKeyPair = {a[0].strip(): abrevValue[0].strip()}
    if multiValues:
        headerContentArray[headerArray[0]] = multiValues
    elif singleValueKeyPair:
        headerContentArray[headerArray[0]] = singleValueKeyPair
    else:
        headerContentArray[headerArray[0]] = headerArray[1]

configArray = json.load(open(configFile))

pass_result = []
fail_result = []
for test_name, test in configArray.items():
    test['key'] = test['key'].lower()
    if test['key'] not in headerContentArray:
        fail_result.append({'name': test['key'], 'result': 'Header is not set.'})
        continue

    if test['type'] == 'single-key-value':
        singleValueKey = ''
        singleValueValue = ''
        for key, val in test['value'].items():
            singleValueKey = key.lower()
            singleValueValue = val.lower()

        if singleValueKey not in headerContentArray[test['key']]:
            fail_result.append({'name': test['key'], 'result': f"Header {test['key']} does not contain the key value {singleValueKey}"})
            continue

        if test['matching-type'] == 'should-not-contain':
            failFlag = False
            failFlag = headerContentArray[test['key']][singleValueKey] == singleValueValue

            if failFlag:
                fail_result.append({'name': test['key'], 'result': f"Header {test['key']} contains invalid value {singleValueValue}"})
                continue
        else:
            passFlag = False
            passFlag = headerContentArray[test['key']][singleValueKey] == singleValueValue

            if passFlag:
                pass_result.append({'name': test['key'], 'result': f"Header {test['key']} contains correct value {singleValueValue}"})
                continue

        fail_result.append({'name': test['key'], 'result': f"Header {test['key']} contains invalid value {singleValueValue}"})

    elif test['type'] == 'value':
        if test['matching-type'] == 'should-not-be-set':
            if test['key'] in headerContentArray:
                fail_result.append({'name': test['key'], 'result': f"Prohibited header set {test['key']}"})
                continue

        if test['key'] not in headerContentArray:
            fail_result.append({'name': test['key'], 'result': 'Header is not set or is missing.'})
            continue

        if test['matching-type'] == 'should-not-contain':
            failFlag = False
            failFlag = test['value'].lower() in headerContentArray[test['key']]

            if failFlag:
                fail_result.append({'name': test['key'], 'result': f"Header {test['key']} contains invalid value {test['value']}"})
                continue
        else:
            passFlag = False
            passFlag = headerContentArray[test['key']] == test['value'].lower()

            if passFlag:
                pass_result.append({'name': test['key'], 'result': f"Header {test['key']} contains correct value {test['value']}"})
                continue

        fail_result.append({'name': test['key'], 'result': f"Header {test['key']} contains invalid value {test['value']}"})

    elif test['type'] == 'multivalue':
        if test['key'] not in headerContentArray:
            fail_result.append({'name': test['key'], 'result': 'Header is not set or is missing.'})
            continue

        passCount = 0
        count = len(test['value'])
        for val in test['value']:
            val = val.lower()
            if test['matching-type'] == 'should-not-contain':
                if val in headerContentArray[test['key']]:
                    fail_result.append({'name': test['key'], 'result': f"Header {test['key']} contains invalid value {val}"})
                    continue
                passCount += 1
            else:
                if val in headerContentArray[test['key']]:
                    pass_result.append({'name': test['key'], 'result': f"Header {test['key']} contains correct value {val}"})
                    passCount += 1

        if passCount < count:
            fail_result.append({'name': test['key'], 'result': f"Header {test['key']} is missing some values "})

print(green + ' ------------------------------------ PASS --------------------------------------- ' + reset)
for p in pass_result:
    print(green + p['name'] + ' ' + p['result'] + reset)

print()

if fail_result:
    print(red + ' ------------------------------------ FAIL --------------------------------------- ' + reset)
    for f in fail_result:
        print(red + f['name'] + ' ' + f['result'] + reset)

if '-vvv' in sys.argv:
    print(headerContentArray)
