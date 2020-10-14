#!/bin/bash
clear

USER=usertest
PASSWORD=passwordtest
IP=test_ip
HOSTNAME=test_hostname
PORT=test_port
COST=test_cost
STATE=IL
PROVIDER=1
CURRENCY=USD
STATUS=free
BILLING_CYCLE=Monthly

key=confident

function wrap() {
  echo -n '\\\"'
  echo -n "$1"
  echo -n '\\\"'
}

function bind() {
  echo -n "$(wrap $1)"
  echo -n ":"
  echo -n "$(wrap $2)"
}

function send_query() {
  run="{\"query\":$1}"
  resp=$(curl -X POST -H "Content-Type:application/json" -H "Authorization:$key" -d "$run" "https://api.monday.com/v2/")
  echo $resp
}

#DATE="{$(bind date $(date +'%Y-%m-%d'))}"
#STATUS="{$(bind label $STATUS)}"
#BILLING_CYCLE="{$(bind label $BILLING_CYCLE)}"
#
#COMMENT="ip:$IP user:$USER password:$PASSWORD"
#
#values="{ \
#$(bind port "$PORT"), \
#$(bind state "$STATE"), \
#$(bind provider__owner_ "$PROVIDER"), \
#$(bind hostname__testing_ "$HOSTNAME"), \
#$(bind cost "$COST"), \
#$(bind currency "$CURRENCY"), \
#$(wrap final_status) : $STATUS, \
#$(wrap date) : $DATE, \
#$(wrap billing_cycle) : $BILLING_CYCLE \
#}"
#
#query="\"mutation{\
#create_item(\
#board_id:390608298, \
#group_id:new_group31774, \
#item_name:$IP, \
#column_values:\\\"$values\\\")\
#{id}}\""
#
#response=$(send_query "$query")
#
#echo $response
#
#item_id=$(echo $response | jq -cr '.data.create_item.id')
#
#query="\"mutation{\
#create_update(\
#item_id: $item_id, \
#body: \\\"$COMMENT\\\")\
#{id}}\""
#
#response=$(send_query "$query")

# item_increment [name of item]
function item_increment() {
  query='"{items_by_column_values(board_id: 311543532, column_id: \"name\", column_value: \"'"$1"'\"){id column_values {id value}}}"'
  resp=$(send_query "$query" | jq -cr '.data.items_by_column_values[0]') # .column_values[]' | grep numbers | jq -cr '.value')
  item_id=$(echo $resp | jq -cr '.id')
  counter=$(echo $resp | jq -cr '.column_values[]' | grep numbers | jq -cr '.value')
  counter=$(eval echo $counter)
  next=$(($counter + 1))
  query='"mutation{change_column_value(board_id: 311543532, item_id: '"$item_id"', column_id: \"numbers\", value: \"\\\"'"$next"'\\\"\"){id}}"'
  send_query "$query"
}

# item_increment "music.perfect-sitebank.com"

# item_id [board_id] [name]
function item_id() {
  query='"{items_by_column_values(board_id: '"$1"', column_id: \"name\", column_value: \"'"$2"'\"){id column_values {id value}}}"'
  resp=$(send_query "$query" | jq -cr '.data.items_by_column_values[0]') # .column_values[]' | grep numbers | jq -cr '.value')
  echo $resp | jq -cr '.id'
}

# add_update [board_id] [name] [text_update]
function add_update() {
  itemid=$(item_id $1 $2)
  query="\"mutation{create_update(item_id: $itemid,body:\\\"$3\\\"){id}}\""
  send_query "$query"
}
# add_update '311543532' 'valeratest' "Valera THE BEST"

# set_field [board_id] [name] [column_id] [type] [value]
function set_field() {
  itemid=$(item_id $1 $2)
  if [ "$4" == "text" ]; then
    query='"mutation{change_column_value(board_id: '"$1"', item_id: '"$itemid"', column_id: \"'"$3"'\", value: \"\\\"'"$5"'\\\"\"){id}}"'
  fi
  echo "$query"
  send_query "$query"
}
#set_field '311543532' 'valeratest' 'text1' 'text' 'bad'

# get_template [id_board_inctances] [id_board_templates] [domain_instance]
function get_template() {
  query='"{items_by_column_values(board_id: '"$1"', column_id: \"name\", column_value: \"'"$3"'\"){column_values(ids: \"tags\"){text}}}"'
  tag=$(send_query "$query" | jq -cr '.data.items_by_column_values[0].column_values[0].text')
  query='"{boards(ids: '"$2"'){items(limit: 1000){name column_values{id text}}}}"'
  data=$(send_query "$query" | jq '.data.boards[0].items[]')
  templates=$(echo "$data" | jq -cr '.name')
  #  echo "$templates"
  tags=$(echo "$data" | jq -cr '.column_values[1].text')
  #  echo $tags
  arr=($tags)
  value="$tag"
  for i in "${!arr[@]}"; do
    if [[ ${arr[$i]} == "$value" ]]; then
      break
    fi
  done
  arr=($templates)
  echo "${arr[$i]}"
}
#get_template "85448923" '311543532' 'glirejar.com'
#item_id '311543532' 'lifestyle.perfect-sitebank.com' ''
# "{\"tag_ids\":[3479149]}"

function check_ip() {
  PROXY=$(http_proxy=http://surf:surf@$1:6128 curl -I http://google.com/)
  if [[ $PROXY =~ "Connection: keep-alive" ]]; then
    echo 0
  else
    echo 1
  fi
}

resp=$(send_query '"{boards(ids:390608298){groups(ids:[\"new_group17581\",\"new_group31774\",\"new_group\"]){items(limit:2){name}}}}"' | jq -cr '.data.boards[0].groups[].items[].name')

ips_notcheck=("31.133.102.93(DoNotTouch)" "185.229.226.184(DoNotTouch)" "185.229.226.199(DoNotTouch)")

IPs=($resp)
for ((i = 0; i < ${#IPs[@]}; i++)); do
  ip="${IPs[$i]}"
  if [[ ! " ${ips_notcheck[@]} " =~ " ${ip} " ]]; then
    echo "checking $ip"
    echo " . . . "
    status=$(check_ip "$ip")
    echo "$status"
    if [ $status -eq 1 ]; then
      echo "bad status $ip"
      ip_id="$(item_id "$ip")"
      echo "$ip_id"
      send_query '"mutation{change_column_value(board_id:390608298,item_id:'"$ip_id"',column_id:\"test\",value:\"{\\\"label\\\":\\\"Fix\\\"}\"){id}}"'
      send_query '"mutation{change_column_value(board_id:390608298,item_id:'"$ip_id"',column_id:\"people\",value:\"{\\\"personsAndTeams\\\":[{\\\"id\\\":5326596,\\\"kind\\\":\\\"person\\\"}]}\"){id}}"'
    fi
  fi
done

#send_query '"mutation{change_column_value (board_id: 390608298, item_id: 527286328, column_id:\"test\",value:\"{\\\"label\\\":\\\"Fix\\\"}\"){id}}"'

#send_query '"mutation{change_column_value(board_id:390608298,item_id:527286328,column_id:\"people\",value:\"{\\\"personsAndTeams\\\":[{\\\"id\\\":5326596,\\\"kind\\\":\\\"person\\\"}]}\"){id}}"'
