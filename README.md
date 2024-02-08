```
 .d8888b.                    888    d8b                   888
d88P  Y88b                   888    Y8P                   888
Y88b.                        888                          888
 "Y888b.    .d88b.  88888b.  888888 888 88888b.   .d88b.  888
    "Y88b. d8P  Y8b 888 "88b 888    888 888 "88b d8P  Y8b 888
      "888 88888888 888  888 888    888 888  888 88888888 888
Y88b  d88P Y8b.     888  888 Y88b.  888 888  888 Y8b.     888
 "Y8888P"   "Y8888  888  888  "Y888 888 888  888  "Y8888  888

                ▒▒▒▒▒▒▒█▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀█
                ▒▒▒▒▒▒▒█░▒▒▒▒▒▒▒▓▒▒▓▒▒▒▒▒▒▒░█
                ▒▒▒▒▒▒▒█░▒▒▓▒▒▒▒▒▒▒▒▒▄▄▒▓▒▒░█░▄▄
                ▒▒▄▀▀▄▄█░▒▒▒▒▒▒▓▒▒▒▒█░░▀▄▄▄▄▄▀░░█
                ▒▒█░░░░█░▒▒▒▒▒▒▒▒▒▒▒█░░░░░░░░░░░█  < We're in this together, or not at all.
                ▒▒▒▀▀▄▄█░▒▒▒▒▓▒▒▒▓▒█░░░█▒░░░░█▒░░█
                ▒▒▒▒▒▒▒█░▒▓▒▒▒▒▓▒▒▒█░░░░░░░▀░░░░░█
                ▒▒▒▒▒▄▄█░▒▒▒▓▒▒▒▒▒▒▒█░░█▄▄█▄▄█░░█
                ▒▒▒▒█░░░█▄▄▄▄▄▄▄▄▄▄█░█▄▄▄▄▄▄▄▄▄█
                ▒▒▒▒█▄▄█░░█▄▄█░░░░░░█▄▄█░░█▄▄█
                
                 (c) 2021-2022 by Seth Wallace / Maxproton
         <maxpr0t0n@protonmail.com> https://github.com/sethvoid/pwd
           Licensed under Apache License 2.0 (see LICENSE file)

       Please do not use in military or for illegal purposes.
  (This is the wish of the author and non-binding. Many people working
   in these organizations do not care for laws and ethics anyways.
        You are not one of the "good" ones if you ignore this.)
```

# SentinelScanner
SentinelScanner is a lightweight yet powerful security tool designed to scan HTTP headers for potential vulnerabilities and misconfigurations. It meticulously inspects web server headers, identifying security-related issues such as missing security headers, improper configurations, and common vulnerabilities like Cross-Origin Resource Sharing (CORS) misconfigurations and Content Security Policy (CSP) violations. With its customizable scanning capabilities and detailed reporting, SentinelScanner empowers developers, security professionals, and system administrators to proactively identify and address security weaknesses in their web applications.

### Config file
The config file is a json file which outlines the tests to perform 
here is a typical example 

```json
{
  "test1": {
    "name": "Strict Transport Security",
    "type": "single-key-value",
    "matching-type": "contain",
    "key": "strict-transport-security",
    "value": {
      "max-age": "31536000"
    }
  },
  "test2": {
    "name": "x Frame options",
    "type": "value",
    "key": "x-frame-options",
    "matching-type": "contain",
    "value": "DENY"
  },
 
  "test3": {
    "name": "Content Security Policy",
    "type": "value",
    "key": "Content-Security-Policy",
    "matching-type": "should-not-contain",
    "value": "unsafe"
  },
  "test4": {
    "name": "cache-control",
    "type": "multivalue",
    "key": "cache-control",
    "matching-type": "contain",
    "value": [
      "no-store",
      "max-age=0"
    ]
  },
  "test5": {
    "name": "feature-policy",
    "type": "value",
    "key": "feature-policy",
    "matching-type": "should-not-be-set"
  }
}

```
Name: The name of the test (this is shown in the pass fail result)
 
#### Types
+ single-key-value - A single key value pair for example header-key: valuekey=value-value
+ value - This is a simple header-key: "header-value" 
+ multivalue - This is a value with multiple values for example header-key: value1, value2, value3

#### matching-type 
The matching type has three logic parameters 
+ contain - this checks if the value contains a string/value
+ should-not-contain - this checks if a header-value contains a value if it does this is a fail
+ should-not-be-set - this checks to make sure a header-key isnt set. For example depricated headers

#### Run scan
To run the command do the following 
```bash
scan.sh "https://endpoint-to-scan.com" "testplan.json"
```
The config. being the tests to run.
