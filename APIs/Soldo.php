<?php

class SoldoAPI
{
    private $encrypt_error = null;
//    private $endpoint = 'https://api-demo.soldocloud.net';
    private $endpoint = 'https://api.soldo.com';

    private $client_id;
    private $client_secret;

    private $access_token = null;
    private $access_token_expiration = 0; // time when access_token will be expired
    private $extra_time = 200;            // if current time greater than ($access_token_expiration - $extra_time) - take new token, see get_access_token

    private $token;
    private $private_rsa;
    private $public_rsa;


    function __construct($soldo_creds)
    {
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
            $this->endpoint . "/oauth/authorize",
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

    private function send_curl($url, $request_type, $http_headers, $data = null)
    {
        if ($url !== ($this->endpoint . "/oauth/authorize")) {
            $http_headers[] = 'Authorization: Bearer ' . $this->get_access_token();
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request_type);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $http_headers);
        if (isset($data) && ($request_type !== "GET")) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
//        var_dump($data);
//        var_dump($http_headers);
        $response = curl_exec($ch);
//        var_dump($response);
//        exit();
//        var_dump(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);
        $result = json_decode($response, true);
        return ($result) ? $result : json_encode(["response" => $response]);
    }

    private function send_request($path, $request_type = 'GET', $data = null, $data_encrypted = null)
    {
        if ($this->encrypt_error) {
            $res = ["status" => "Error", "data" => [$this->encrypt_error]];
            $this->encrypt_error = null;
            return $res;
        }
        $http_headers = [];
        if (isset($data_encrypted)) {
            $http_headers[] = 'X-Soldo-Fingerprint: ' . $data_encrypted[0];
            $http_headers[] = 'X-Soldo-Fingerprint-Signature: ' . $data_encrypted[1];
        }
        if ($request_type !== "GET" && $request_type !== "DELETE") {
            $http_headers[] = "Content-Type: application/" . ((json_decode($data) !== NULL) ? 'json' : 'x-www-form-urlencoded');
        }
        $url = $this->endpoint . $path;
        $result = $this->send_curl($url, $request_type, $http_headers, $data);
        if (isset($result['error'])) {
            return ["status" => "Error", "data" => $result];
        }

        if (isset($result['pages'])) {                 // If there is pages then resolve pagination
            $count_pages = $result['pages'];
            $result = $result['results'];

            for ($i = 1; $i < $count_pages; ++$i) {
                $response = $this->send_curl($url . "&p=" . strval($i), $request_type, $http_headers, $data);
                if (!isset($response['results'])) {
                    return ["status" => "Error", "data" => $response];
                }
                $result = array_merge($result, $response['results']);
            }
        }

        return ["status" => "Done", "data" => $result];
    }

    private function encrypt_data($data)
    {
        $data .= $this->token;
        $fingerprint = hash("sha512", $data);
        $public_key_pem = openssl_pkey_get_public($this->public_rsa);
        $private_key_pem = openssl_pkey_get_private($this->private_rsa);
        openssl_sign($fingerprint, $signature, $private_key_pem, OPENSSL_ALGO_SHA512);
        if (!openssl_verify($fingerprint, $signature, $public_key_pem, "sha512WithRSAEncryption")) {
            $this->encrypt_error = "Invalid RSA keys(or key)";
        }
        $signature_base64_encoded = base64_encode($signature);
        return [$fingerprint, $signature_base64_encoded];
    }


    private function advanced_request($path, $fingerprint_order, $parameters, $request_type)
    {
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

        return $this->send_request($path, $request_type, json_encode($parameters), $data_encrypted);
    }


    public function get_company()
    {
        $path = "/business/v2/company?s=50";
        return $this->send_request($path);
    }


    public function search_users($filters = [])
    {
        $path = "/business/v2/employees?s=50";
        foreach ($filters as $key => $value) {
            $path .= "&$key=$value";
        }
        return $this->send_request($path);
    }

    public function get_user($employeeId)
    {
        $path = "/business/v2/employees/{$employeeId}?s=50";
        return $this->send_request($path);
    }

    public function add_user($parameters)
    {
        $path = "/business/v2/employees";
        $fingerprint_order = ['request_timestamp', 'name', 'surname', 'mobile_access', 'web_access'];
        return $this->advanced_request($path, $fingerprint_order, $parameters, "POST");
    }

    public function update_user_data($employeeId, $custom_reference_id)
    {
        $path = "/business/v2/employees/{$employeeId}";
        $data_encrypted = $this->encrypt_data($custom_reference_id);
        return $this->send_request($path, "PUT", json_encode(["custom_reference_id" => $custom_reference_id]), $data_encrypted);
    }


    public function search_wallets($filters = [])
    {
        $path = "/business/v2/wallets?s=50";
        foreach ($filters as $key => $value) {
            $path .= "&$key=$value";
        }
        return $this->send_request($path);
    }

    public function get_wallet($walletId)
    {
        $path = "/business/v2/wallets/{$walletId}?s=50";
        return $this->send_request($path);
    }

    public function add_wallet($parameters)
    {
        $path = "/business/v2/wallets";
        $fingerprint_order = ['request_timestamp', 'owner_type', 'owner_public_id', 'currency', 'name'];
        return $this->advanced_request($path, $fingerprint_order, $parameters, "POST");
    }

    public function internal_transfer($amount, $currencyCode, $fromWalletId, $toWalletId)
    {
        $path = "/business/v2/wallets/internalTransfer/{$fromWalletId}/{$toWalletId}";
        $data_for_encrypt = $amount . $currencyCode . $fromWalletId . $toWalletId;
        $data_encrypted = $this->encrypt_data($data_for_encrypt);
        return $this->send_request($path, "PUT", "amount={$amount}&currencyCode={$currencyCode}", $data_encrypted);
    }


    public function search_cards($filters = [])
    {
        $path = "/business/v2/cards?s=50";
        foreach ($filters as $key => $value) {
            $path .= "&$key=$value";
        }
        return $this->send_request($path);
    }

    public function get_card($cardId)
    {
        $path = "/business/v2/cards/{$cardId}?s=50";
        return $this->send_request($path);
    }

    public function add_card($parameters)
    {
        $path = "/business/v2/cards";
        $fingerprint_order = ['request_timestamp', 'owner_type', 'owner_public_id', 'wallet_id'];
        return $this->advanced_request($path, $fingerprint_order, $parameters, "POST");
    }

    public function destroy_card($cardId)
    {
        $path = "/business/v2/cards/{$cardId}";
        $data_encrypted = $this->encrypt_data($cardId);
        return $this->send_request($path, "DELETE", null, $data_encrypted);
    }

    public function list_card_rules($cardId)
    {
        $path = "/business/v2/cards/$cardId/rules?s=50";
        return $this->send_request($path);
    }


    public function search_dictionaries($filters = [])
    {
        $path = "/business/v2/dictionaries?s=50";
        foreach ($filters as $key => $value) {
            $path .= "&$key=$value";
        }
        return $this->send_request($path);
    }

    public function get_dictionary($dictionaryId)
    {
        $path = "/business/v2/dictionaries/{$dictionaryId}?s=50";
        return $this->send_request($path);
    }

    public function search_tags($dictionaryId, $filters = [])
    {
        $path = "/business/v2/dictionaries/{$dictionaryId}/tags?s=50";
        foreach ($filters as $key => $value) {
            $path .= "&$key=$value";
        }
        return $this->send_request($path);
    }

    public function get_tag($dictionaryId, $tagId)
    {
        $path = "/business/v2/dictionaries/{$dictionaryId}/tags/{$tagId}?s=50";
        return $this->send_request($path);
    }


    public function search_transactions($filters = [])
    {
        $fingerprint_order = ['type', 'publicId', 'customReferenceId', 'fromDate', 'toDate', 'dateType', 'category', 'status', 'tagId', 'metadataId'];
        $path = "/business/v2/transactions?s=50";
        foreach ($filters as $key => $value) {
            $path .= "&$key=$value";
        }
        return $this->advanced_request($path, $fingerprint_order, $filters, "GET");
    }

    public function get_transaction($transactionId)
    {
        $path = "/business/v2/transactions/$transactionId?s=50&showDetails=true&showFuelDetails=true";
        $data_for_encrypt = "$transactionId";
        $data_encrypted = $this->encrypt_data($data_for_encrypt);
        return $this->send_request($path, "GET", null, $data_encrypted);
    }

    public function transaction_metadata($transactionId)
    {
        $path = "/business/v2/transactions/$transactionId/metadata?s=50";
        return $this->send_request($path);
    }

    public function get_metadata($transactionId, $metadataId)
    {
        $path = "/business/v2/transactions/{$transactionId}/metadata/{$metadataId}?s=50";
        return $this->send_request($path);
    }

    public function add_metadata($transactionId, $metadataId, $metadata_json)
    {
        $path = "/business/v2/transactions/{$transactionId}/metadata/{$metadataId}";
        $fingerprint_order = ['transactionId', 'metadataId', 'metadata'];
        return $this->advanced_request($path, $fingerprint_order, $metadata_json, "POST");
    }

    public function list_attachments($transactionId)
    {
        $path = "/business/v2/transactions/{$transactionId}/attachments?s=50";
        return $this->send_request($path);
    }

    public function get_attachment($transactionId, $attachmentId)
    {
        $path = "/business/v2/transactions/{$transactionId}/attachments/{$attachmentId}?s=50";
        return $this->send_request($path);
    }


    public function search_orders($filters = [])
    {
        $path = "/business/v2/orders?s=50";
        foreach ($filters as $key => $value) {
            $path .= "&$key=$value";
        }
        return $this->send_request($path);
    }

    public function get_order($orderId)
    {
        $path = "/business/v2/orders/{$orderId}?s=50";
        return $this->send_request($path);
    }

    public function delete_order($orderId)
    {
        $path = "/business/v2/orders/{$orderId}";
        $data_encrypted = $this->encrypt_data($orderId);
        return $this->send_request($path, "DELETE", null, $data_encrypted);
    }
}


//$client_id = 'confident info';
//$client_secret = 'confident info';
//$token = "confident info";
//
//$client_id = 'confident info';
//$client_secret = 'confident info';
//$token = "confident info";
//
//$client_id = 'confident info';
//$client_secret = 'confident info';
//$token = "confident info";
//
//$public_rsa_string = "confident info";
//
//$private_rsa_string = "confident info";
//
//$public_rsa_string = "confident info";
//
//$private_rsa_string = "confident info";
//
//$soldo_creds = [
//    'client_id' => $client_id,
//    'client_secret' => $client_secret,
//    'token' => $token,
//    'private_rsa' => $private_rsa_string,
//    'public_rsa' => $public_rsa_string
//];
//
//$soldoAPI_obj = new SoldoAPI(
//    $soldo_creds
//);

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

//$card = $soldoAPI_obj->get_card("1f01150f-8e5e-4f66-9566-b2700cf0decc");
//var_dump($card);

//$add_card = $soldoAPI_obj->add_card([
//    "owner_type" => "employee",
//      "owner_public_id" => "LLCR5047-000002",
//      "wallet_id" => "01a7fad3-9371-4fc1-afd1-49e7db55df86",
//      "type" => "VIRTUAL",
//      "name" => "my card name",
//      "emboss_line4" => "line four"
//]);  // http://apidoc-demo.soldo.com/v2/zgxiaxtcyapyoijojoef.html#add-card
//var_dump($add_card);

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

//$transaction = $soldoAPI_obj->get_transaction("4466-INV_363201");
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



