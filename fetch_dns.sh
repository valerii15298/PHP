auth_email="X-Auth-Email: √"
auth_key="X-Auth-Key: √"
url="https://api.cloudflare.com/client/v4/"
account_id="confident_info"

# send [method] [url] [query]
function send() {
  curl -s -X $1 "$url$2" \
    -H "$auth_email" \
    -H "$auth_key" \
    -H "Content-Type: application/json" \
    --data "$3"
}

# zone_id [DOMAIN]
function zone_id() {
  send GET "/zones?name=$1" | jq -r '.result | .[0] | .id'
}

domains=$(cat domains)

for i in $domains; do
  k='publisher'
  echo -n $i
  id=$(zone_id "$i")
  dns=$(send GET "zones/$id/dns_records" | jq -cr '.result[].content')
  for j in $dns; do
    if [ ! "$j" == "194.146.39.249" -a ! "$j" == "185.229.225.188" ]; then
      k='production'
      break
    fi
  done
  echo " - $k"
done