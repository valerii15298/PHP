auth_email="X-Auth-Email: confident info";
auth_key="X-Auth-Key: confident info";
url="https://api.cloudflare.com/client/v4/";
account_id="confident info";

# send [method] [url] [query]
function send() {
	curl -X $1 "$url$2" \
    -H "$auth_email" \
    -H "$auth_key" \
    -H "Content-Type: application/json" \
    --data "$3"
}

# zone_id [DOMAIN]
function zone_id() {
	send GET "/zones?name=$1" | jq -r '.result | .[0] | .id'
}

# add_domain [DOMAIN]
function add_domain() {
	query_create=$(echo '{"name":"name","account":{"id":"id"},"jump_start":false}' | jq -c ".name = \"$1\" | .account.id = \"$account_id\"")
	response=$(send POST zones $query_create)
	echo $response
}

add_domain 'formoxi.com'
