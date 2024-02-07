#!/bin/bash

# ANSI escape codes for colors
red='\033[0;31m'
green='\033[0;32m'
reset='\033[0m'  # Reset to default color

target="$1"
configFile="$2"

# Fetching headers
response=$(curl -Is "$target")

if [ $? -ne 0 ]; then
    echo "Failed to fetch headers."
    exit 1
fi

# Initialize an associative array to store header content
declare -A headerContentArray

# Loop through each header line
while IFS= read -r line; do
    if [[ $line == *:* ]]; then
        headerName=$(echo "$line" | awk -F: '{print tolower($1)}')
        headerValue=$(echo "$line" | awk -F: '{gsub(/^[ \t]+/, "", $2); print tolower($2)}')

        # Check for multi-value headers
        if [[ $headerValue == *','* ]]; then
            headerContentArray["$headerName"]=$(echo "$headerValue" | tr ',' '\n')
        else
            headerContentArray["$headerName"]="$headerValue"
        fi
    fi
done <<< "$response"

# Read config file into a variable
configJson=$(cat "$configFile")

# Parse config JSON and check headers
pass_result=""
fail_result=""

while IFS= read -r line; do
    # Parsing JSON object
    test_name=$(jq -r '.test_name' <<< "$line")
    key=$(jq -r '.key' <<< "$line")
    type=$(jq -r '.type' <<< "$line")
    value=$(jq -r '.value' <<< "$line")
    matching_type=$(jq -r '.matching_type' <<< "$line")

    # Check if key exists in headers
    if [ -z "${headerContentArray[$key]}" ]; then
        fail_result+="$key is not set"$'\n'
        continue
    fi

    # Perform checks based on type
    if [ "$type" == "single-key-value" ]; then
        if [[ $matching_type == "should-not-contain" ]]; then
            if [[ "${headerContentArray[$key]}" == *"$value"* ]]; then
                fail_result+="$key contains invalid value $value"$'\n'
            else
                pass_result+="$key contains correct value $value"$'\n'
            fi
        else
            if [[ "${headerContentArray[$key]}" == *"$value"* ]]; then
                pass_result+="$key contains correct value $value"$'\n'
            else
                fail_result+="$key contains invalid value $value"$'\n'
            fi
        fi
    elif [ "$type" == "value" ]; then
        if [[ $matching_type == "should-not-be-set" ]]; then
            fail_result+="$key is set but should not be"$'\n'
        else
            if [[ "${headerContentArray[$key]}" == *"$value"* ]]; then
                pass_result+="$key contains correct value $value"$'\n'
            else
                fail_result+="$key contains invalid value $value"$'\n'
            fi
        fi
    elif [ "$type" == "multivalue" ]; then
        values=$(echo "${headerContentArray[$key]}" | tr '\n' ' ')
        passCount=0

        for val in $value; do
            if [[ $values == *"$val"* ]]; then
                passCount=$((passCount+1))
            fi
        done

        if [ $passCount -eq 0 ]; then
            fail_result+="$key is missing some values"$'\n'
        elif [ $passCount -lt $(echo "$value" | wc -w) ]; then
            fail_result+="$key contains invalid values"$'\n'
        else
            pass_result+="$key contains correct values"$'\n'
        fi
    fi
done <<< "$(jq -c '.[]' <<< "$configJson")"

# Print results
echo -e "${green}------------------------------------ PASS ---------------------------------------${reset}"
echo -e "$pass_result"

if [ ! -z "$fail_result" ]; then
    echo -e "${red}------------------------------------ FAIL ---------------------------------------${reset}"
    echo -e "$fail_result"
fi
