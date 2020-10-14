<?php

//function fetch_all() {
//    if (isset($result['pages'])) {                 // If there is pages then resolve pagination
//        $count_pages = $result['pages'];
//        $result = $result['results'];
//
//        for ($i = 1; $i < $count_pages; ++$i) {
//            $response = $this->send_curl($path . "&p=" . strval($i), $request_type, $http_headers, $data);
//            if (!isset($response['results'])) {
//                return ["fail" => json_encode($response)];
//            }
//            $result = array_merge($result, $response['results']);
//        }
//    }
//}

class SoldoAPI
{
    private $endpoint;

    private $client_id;
    private $client_secret;

    private $access_token = null;
    private $access_token_expiration = 0; // time when access_token will be expired
    private $extra_time = 200;            // if current time greater than ($access_token_expiration - $extra_time) - then refresh token token, see get_access_token

    private $token;
    private $private_rsa;
    private $public_rsa;


    function __construct($soldo_creds, bool $production = false)
    {
        $this->endpoint = $production ? 'https://api.soldo.com' : 'https://api-demo.soldocloud.net';
        $this->access_token_expiration = 0;

        $this->client_id = $soldo_creds['client_id'];
        $this->client_secret = $soldo_creds['client_secret'];
        $this->token = $soldo_creds['token'];
        $this->private_rsa = $soldo_creds['private_rsa'];
        $this->public_rsa = $soldo_creds['public_rsa'];
    }

    private function request_timestamp()
    {
        return intval(microtime(true) * 1000);
    }

    private function format_fingerprint($value)
    {
        if ($value === true)
            return "true";
        if ($value === false)
            return "false";
        if ($value === null)
            return "null";
        return strval($value);
    }

    private function refresh_token()
    {
        return $this->send_curl(
            "/oauth/authorize",
            "POST",
            ["Content-Type: application/x-www-form-urlencoded"],
            "client_id={$this->client_id}&client_secret={$this->client_secret}"
        );
    }

    private function get_access_token()
    {
        if (!$this->access_token || $this->access_token_expiration > (time() - $this->extra_time)) {
            $new_token = $this->refresh_token();
            $this->access_token = $new_token['access_token'];
            $this->access_token_expiration = time() + intval($new_token['expires_in']);
        }
        return $this->access_token;
    }

    private function send_curl($path, $method, $http_headers, $data = null)
    {
        $url = $this->endpoint . $path;

        //curl
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $http_headers);
        if (isset($data) && ($method !== "GET")) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        $response = curl_exec($ch);
        curl_close($ch);

//        $response = file_get_contents($url, false, stream_context_create([
//            'http' => [
//                'method' => $method,
//                'header' => $http_headers,
//                'content' => ($method === "GET") ? null : $data
//            ]
//        ]));

//        var_dump($url);
//        var_dump($data);
//        var_dump($http_headers);
//        var_dump($response);
//        exit();

        $result = json_decode($response, true);
        return ($result) ? $result : json_encode(["fail" => $response]);
    }

    private function send_request(string $path, string $request_type = 'GET', $data = null, array $fingerprint_order = null)
    {
        $http_headers = ['Authorization: Bearer ' . $this->get_access_token()];

        if (is_array($fingerprint_order)) {
            $parameters = is_array($data) ? $data : [];
            if (is_string($data)) {
                parse_str($data, $parameters);
            }
            if (in_array("request_timestamp", $fingerprint_order)) {
                $parameters["request_timestamp"] = $this->request_timestamp();
            }
            $data_for_encrypt = "";
            foreach ($fingerprint_order as $key) {
                if (isset($parameters[$key])) {
                    $data_for_encrypt .= $this->format_fingerprint($parameters[$key]);
                }
            }
            $data_encrypted = $this->encrypt_data($data_for_encrypt);
            if (!$data_encrypted) {
                return ['fail' => "Encrypt error!"];
            }
            $http_headers[] = 'X-Soldo-Fingerprint: ' . $data_encrypted[0];
            $http_headers[] = 'X-Soldo-Fingerprint-Signature: ' . $data_encrypted[1];
        }

        if (is_array($data)) {
            $data = json_encode($data);
            $http_headers[] = "Content-Type: application/json";
        } else {
            $http_headers[] = "Content-Type: application/x-www-form-urlencoded";
        }


        if ($request_type === 'GET' && is_array($data)) {
            $path .= '?' . http_build_query($data);
        }

        $result = $this->send_curl($path, $request_type, $http_headers, $data);
        if (isset($result['error']) || (isset($result['error_code']) && $result['error_code'] !== '')) {
            return ["fail" => json_encode($result)];
        } else
            return $result;
    }

    private function encrypt_data($data)
    {
        $data .= $this->token;
        $fingerprint = hash("sha512", $data);
        $public_key_pem = openssl_pkey_get_public($this->public_rsa);
        $private_key_pem = openssl_pkey_get_private($this->private_rsa);
        openssl_sign($fingerprint, $signature, $private_key_pem, OPENSSL_ALGO_SHA512);
        if (!openssl_verify($fingerprint, $signature, $public_key_pem, "sha512WithRSAEncryption")) {
            return false;
        }
        $signature_base64_encoded = base64_encode($signature);
        return [$fingerprint, $signature_base64_encoded];
    }

    public function get_company()
    {
        $path = "/business/v2/company";
        return $this->send_request($path);
    }


    public function search_users($filters = [])
    {
        $path = "/business/v2/employees";
        return $this->send_request($path, 'GET', $filters);
    }

    public function get_user($employeeId)
    {
        $path = "/business/v2/employees/{$employeeId}";
        return $this->send_request($path);
    }

    public function add_user($parameters)
    {
        $path = "/business/v2/employees";
        $fingerprint_order = ['request_timestamp', 'name', 'surname', 'mobile_access', 'web_access'];
        return $this->send_request($path, 'POST', $parameters, $fingerprint_order);
    }

    public function update_user_data($employeeId, $parameters)
    {
        $path = "/business/v2/employees/{$employeeId}";
        $fingerprint_order = ['custom_reference_id'];
        return $this->send_request($path, "PUT", $parameters, $fingerprint_order);
    }


    public function search_wallets($filters = [])
    {
        $path = "/business/v2/wallets";
        return $this->send_request($path, 'GET', $filters);
    }

    public function get_wallet($walletId)
    {
        $path = "/business/v2/wallets/{$walletId}";
        return $this->send_request($path);
    }

    public function add_wallet($parameters)
    {
        $path = "/business/v2/wallets";
        $fingerprint_order = ['request_timestamp', 'owner_type', 'owner_public_id', 'currency', 'name'];
        return $this->send_request($path, 'POST', $parameters, $fingerprint_order);
    }

    /* TODO : update internal transfer */
//    public function internal_transfer($amount, $currencyCode, $fromWalletId, $toWalletId)
//    {
//        $path = "/business/v2/wallets/internalTransfer/{$fromWalletId}/{$toWalletId}";
//        $fingerprint_order = [''];
//        $data_for_encrypt = $amount . $currencyCode . $fromWalletId . $toWalletId;
//        $data_encrypted = $this->encrypt_data($data_for_encrypt);
//        return $this->send_request($path, "PUT", "amount={$amount}&currencyCode={$currencyCode}", $data_encrypted);
//    }


    public function search_cards($filters = [])
    {
        $path = "/business/v2/cards";
        return $this->send_request($path, 'GET', $filters);
    }

    /* TODO: update */
    public function get_card($cardId)
    {
        $path = "/business/v2/cards/{$cardId}?showSensitiveData=true";
        return $this->send_request($path);
    }

    public function add_card($parameters)
    {
        $path = "/business/v2/cards";
        $fingerprint_order = ['request_timestamp', 'owner_type', 'owner_public_id', 'wallet_id'];
        return $this->send_request($path, "POST", $parameters, $fingerprint_order);
    }

    /* TODO need check if it working*/
//    public function destroy_card($cardId)
//    {
//        $path = "/business/v2/cards/{$cardId}";
//        $fingerprint_order = ['card_id'];
//        return $this->send_request($path, "DELETE", "card_id=$cardId", $fingerprint_order);
//    }

    public function list_card_rules($cardId)
    {
        $path = "/business/v2/cards/$cardId/rules";
        return $this->send_request($path);
    }


    public function search_dictionaries($filters = [])
    {
        $path = "/business/v2/dictionaries";
        return $this->send_request($path, 'GET', $filters);
    }

    public function get_dictionary($dictionaryId)
    {
        $path = "/business/v2/dictionaries/{$dictionaryId}";
        return $this->send_request($path);
    }

    public function search_tags($dictionaryId, $filters = [])
    {
        $path = "/business/v2/dictionaries/{$dictionaryId}/tags";
        return $this->send_request($path, 'GET', $filters);
    }

    public function get_tag($dictionaryId, $tagId)
    {
        $path = "/business/v2/dictionaries/{$dictionaryId}/tags/{$tagId}";
        return $this->send_request($path);
    }


    public function search_transactions($filters = [])
    {
        $path = "/business/v2/transactions";
        $fingerprint_order = ['type', 'publicId', 'customReferenceId', 'fromDate', 'toDate', 'dateType', 'category', 'status', 'tagId', 'metadataId'];
        return $this->send_request($path, "GET", $filters, $fingerprint_order);
    }

    /* TODO update check that working*/
//    public function get_transaction($transactionId)
//    {
//        $path = "/business/v2/transactions/$transactionId?showDetails=true&showFuelDetails=true";
//        $fingerprint_order = ['transaction_id'];
//        return $this->send_request($path, "GET", "transaction_id=$transactionId", $fingerprint_order);
//    }

    /* TODO update */
//    public function transaction_metadata($transactionId)
//    {
//        $path = "/business/v2/transactions/$transactionId/metadata";
//        return $this->send_request($path);
//    }

    public function get_metadata($transactionId, $metadataId)
    {
        $path = "/business/v2/transactions/{$transactionId}/metadata/{$metadataId}";
        return $this->send_request($path);
    }

    public function add_metadata($transactionId, $metadataId, $metadata_json)
    {
        $path = "/business/v2/transactions/{$transactionId}/metadata/{$metadataId}";
        $fingerprint_order = ['transactionId', 'metadataId', 'metadata'];
        return $this->send_request($path, "POST", $metadata_json, $fingerprint_order);
    }

    public function list_attachments($transactionId)
    {
        $path = "/business/v2/transactions/{$transactionId}/attachments";
        return $this->send_request($path);
    }

    public function get_attachment($transactionId, $attachmentId)
    {
        $path = "/business/v2/transactions/{$transactionId}/attachments/{$attachmentId}";
        return $this->send_request($path);
    }


    public function search_orders($filters = [])
    {
        $path = "/business/v2/orders";
        return $this->send_request($path, 'GET', $filters);
    }

    public function get_order($orderId)
    {
        $path = "/business/v2/orders/{$orderId}";
        return $this->send_request($path);
    }

    /* TODO update */
//    public function delete_order($orderId)
//    {
//        $path = "/business/v2/orders/{$orderId}";
//        $data_encrypted = $this->encrypt_data($orderId);
//        return $this->send_request($path, "DELETE", null, $data_encrypted);
//    }
}


$client_id = 'confident_info';
$client_secret = 'confident_info';
$token = "confident_info";

$client_id = 'confident_info';
$client_secret = 'confident_info';
$token = "confident_info";


$public_rsa_string = "-----BEGIN PUBLIC KEY-----
confident_info
-----END PUBLIC KEY-----";

$private_rsa_string = "-----BEGIN RSA PRIVATE KEY-----
confident_info
-----END RSA PRIVATE KEY-----";


$soldo_creds = [
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'token' => $token,
    'private_rsa' => $private_rsa_string,
    'public_rsa' => $public_rsa_string
];

$soldoAPI_obj = new SoldoAPI(
    $soldo_creds, false
);

/*
 * In functions that starts with 'search_' you can use filters available in documentation link
 * In functions that starts with 'get_' you cat retrieve info about object by id of object
 * In functions that starts with 'add_' you can add objects just send array of parameters of new object(wallet, card, user)
 * In functions that starts with 'delet_' you can delete(destroy) object by id
 * */


//$company = $soldoAPI_obj->get_company();  // http://apidoc-demo.soldo.com/v2/zgxiaxtcyapyoijojoef.html#get-company
//var_dump($company);


//$users = $soldoAPI_obj->search_users([]);  // retrieve all users
//$users = $soldoAPI_obj->search_users(["name" => "test", "surname" => "user"]);  // http://apidoc-demo.soldo.com/v2/zgxiaxtcyapyoijojoef.html#search-user
//var_dump($users);

//$user = $soldoAPI_obj->get_user("LLCR5047-000002");  // http://apidoc-demo.soldo.com/v2/zgxiaxtcyapyoijojoef.html#get-user
//var_dump($user);


//$add_user = $soldoAPI_obj->add_user([
//    "name" => "somename",
//    "surname" => "somesurname",
//    "mobile_access" => false,
//    "web_access" => true,
//    "email" => "some@er.com"
//]); // http://apidoc-demo.soldo.com/v2/zgxiaxtcyapyoijojoef.html#add-user
//var_dump($add_user);

//$label = "sertext labeserdtextdtext label for usertxt label for usertext label for usertext label for usertext label for usertext label for usertext label for userffffft label for usertext label for usertext label t label for usertext label for usertext label ggggg";
//var_dump(strlen($label));
//$update_user = $soldoAPI_obj->update_user_data("LLCR5047-000003", $label);
//var_dump($update_user);


//$wallets = $soldoAPI_obj->search_wallets([]);  // no filters, retrieve all wallets
//var_dump($wallets);

//$wallets = $soldoAPI_obj->search_wallets([
//    "type" => "employee",
//    "publicId" => "LLCR5047-000002",
//    "customreferenceId" => "mySecondCustomReference"
//]);  // http://apidoc-demo.soldo.com/v2/zgxiaxtcyapyoijojoef.html#search-wallets
//var_dump($wallets);

//$wallet = $soldoAPI_obj->get_wallet("6e6e3597-8493-49d2-a697-1be953001aca");
//var_dump($wallet);

//$add_wallet = $soldoAPI_obj->add_wallet(['owner_type' => 'employee',
//    'owner_public_id' => 'LLCR5047-000002',
//    'currency' => "EUR",
//    'name' => 'wallet_name'
//]);  // http://apidoc-demo.soldo.com/v2/zgxiaxtcyapyoijojoef.html#add-wallet
//var_dump($add_wallet);


//$internalTransfer = $soldoAPI_obj->internal_transfer(
//    "10",
//    "EUR",
//    "01a7fad3-9371-4fc1-afd1-49e7db55df86",
//    "6e69be32-7c06-4576-b3c4-b9af87a3cb47"
//);  // http://apidoc-demo.soldo.com/v2/zgxiaxtcyapyoijojoef.html#internal-transfer
//var_dump($internalTransfer);


//$cards = $soldoAPI_obj->search_cards([
//    "type" => "employee",
//    "publicId" => "LLCR5047-000002",
//    "customreferenceId" => "mySecondCustomReference"
//]);  // http://apidoc-demo.soldo.com/v2/zgxiaxtcyapyoijojoef.html#search-cards
//var_dump($cards);

//$card = $soldoAPI_obj->get_card("c221d2b1-490b-48a1-a92b-710a8b357a36");
//var_dump($card);

// $add_card = $soldoAPI_obj->add_card([
//     "owner_type" => "employee",
//     "owner_public_id" => "LLCR5047-000002",
//     "wallet_id" => "01a7fad3-9371-4fc1-afd1-49e7db55df86",
//     "type" => "VIRTUAL",
//     "name" => "mycard1",
//     "emboss_line4" => "liner fourf"
// ]);  // http://apidoc-demo.soldo.com/v2/zgxiaxtcyapyoijojoef.html#add-card
// var_dump($add_card);

//$destroy_card = $soldoAPI_obj->destroy_card("1f01150f-8e5e-4f66-9566-b2700cf0decc");
//var_dump($destroy_card);

//$card_rules = $soldoAPI_obj->list_card_rules("1f01150f-8e5e-4f66-9566-b2700cf0decc");
//var_dump($card_rules);

//$dictionaries = $soldoAPI_obj->search_dictionaries(["visible" => "true"]);  // http://apidoc-demo.soldo.com/v2/zgxiaxtcyapyoijojoef.html#search-dictionaries
//var_dump($dictionaries);

//$dictionary = $soldoAPI_obj->get_dictionary("be492205-9961-46a6-a1f9-b28bd024aad1");
//var_dump($dictionary);

//$tags = $soldoAPI_obj->search_tags("be492205-9961-46a6-a1f9-b28bd024aad1", ["visible" => "true"]);  // http://apidoc-demo.soldo.com/v2/zgxiaxtcyapyoijojoef.html#search-tags
//var_dump($tags);

//$tag = $soldoAPI_obj->get_tag("be492205-9961-46a6-a1f9-b28bd024aad1", "e796a414-86be-473c-8c88-4bb706c39e81");
//var_dump($tag);

//$transactions = $soldoAPI_obj->search_transactions(["fromDate" => "2020-02-17", "toDate" => "2020-02-18"]);  // no filters, retrieve all transactions for last 40 days
//var_dump($transactions);

//$transactions = $soldoAPI_obj->search_transactions([
//    "type" => "employee",
//    "publicId" => "LLCR5047-000002",
//    "customreferenceId" => "mySecondCustomReference",
//    "fromDate" => "2020-01-15",
//    "toDate" => "2020-02-23",
//    "dateType" => "TRANSACTION",
//    "category" => "Payment",
//    "status" => "Settled"
//]); // http://apidoc-demo.soldo.com/v2/zgxiaxtcyapyoijojoef.html#search-transactions
//var_dump($transactions);

//$transaction = $soldoAPI_obj->get_transaction("4466-5866a79a-8cbd-43a5-ac7a-c7b9cef27d68");
//var_dump($transaction);

//$list_metadata = $soldoAPI_obj->transaction_metadata("4469-631977313-5901919750898");
//var_dump($list_metadata);

//$metadata = $soldoAPI_obj->get_metadata("4469-631977313-5901919750898", "my_metadata_id");
//var_dump($metadata);

//$add_metadata = $soldoAPI_obj->add_metadata(
//    "4469-631977313-5901919750898",
//    "my_metadata_id",
//    json_encode(['name_of_metadata' => "value_of_metadata"])
//);  // http://apidoc-demo.soldo.com/v2/zgxiaxtcyapyoijojoef.html#add-metadata
//var_dump($add_metadata);

//$attachments = $soldoAPI_obj->list_attachments("4469-631977313-5901919750898");  // http://apidoc-demo.soldo.com/v2/zgxiaxtcyapyoijojoef.html#list-attachments
//var_dump($attachments);

//$attachment = $soldoAPI_obj->get_attachment("4469-631977313-5901919750898", "attachment_id");
//var_dump($attachment);

//$orders = $soldoAPI_obj->search_orders([
//    "id" => "670df1fe-c8b9-4f15-868d-3118746f4613",
//    "status" => "PLACED",
//    "fromDate" => "2020-01-28",
//    "toDate" => "2020-03-01"
//]);  // http://apidoc-demo.soldo.com/v2/zgxiaxtcyapyoijojoef.html#search-orders
//var_dump($orders);

//$order = $soldoAPI_obj->get_order("beab4921-5ee5-4d99-a39d-4995320f3e0e");
//var_dump($order);


//$delete_order = $soldoAPI_obj->delete_order("beab4921-5ee5-4d99-a39d-4995320f3e0e");
//var_dump($delete_order);



