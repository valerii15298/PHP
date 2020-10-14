token='confident info'
page='0'

function get_card() {
  card_id="$1"
  card=$(curl -X GET "https://api.soldo.com/business/v2/cards/$card_id?showSensitiveData=true" -H "Authorization: Bearer $token")
  echo "$card" | jq -c '.name' >> cardnames.txt
}

cards=$(curl -X GET "https://api.soldo.com/business/v2/cards?p=$page" -H "Authorization: Bearer $token")
cards=$(echo "$cards" | jq '.results')

echo "$cards" | jq -c '.[]' | while read i; do
  id=$(echo "$i" | jq -rc '.id')
  get_card "$id" &
done
