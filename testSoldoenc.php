<?php
$public_rsa_string = "-----BEGIN PUBLIC KEY-----
confident_info
-----END PUBLIC KEY-----";
file_put_contents('rsa_public.pem', $public_rsa_string);

$private_rsa_string = "-----BEGIN RSA PRIVATE KEY-----
confident_info
-----END RSA PRIVATE KEY-----";

file_put_contents('rsa_private.pem', $private_rsa_string);

//$pkey_private = openssl_pkey_get_private($private_rsa_string);
//$pkey_public = openssl_pkey_get_public($public_rsa_string);

$masked_pan_enc = "TgL7Tl8WAD6BEZSuHnbUZOTpkAcYFsXLGOYZ2R8VqCfOqGol6WQS1g1puOHHXvWs08rH+0XMgDwLvnIPFniTWDeOZEFniri03p7IR1bIm6aCM6gohQLWtDY+KQX7DsE3bjU5jMejqAhFHY8+ER5wYusMiHTyP2rpNUPoo0nBtXy8IWhRwyY/8TxjgMVVev5sRjgIhn8UWibD7Df+J7LW1YWcX4Ov2CwSFJVfaV/F5Tvc0eeQGAONryhjPRJBds1fuMaUDGeCdcQ46DTviXpBcTr+Rh6pkhWms5gzZ1Qo0On6Dqa/URBLU15JfFA7xvNz8PPkZyYucT2j36YBkL+Onw==";
$cvv_enc = "Hx9dG+h6ryOgBhS4Umfu5iWnW6DT4IdUFTJwP2ECsowtJhL4xZBJU3+p/Ad0dgfqaZkLxJ6MpQuxy0LS7TqoVZPA9/oD1Ysacy8Y0QnYdlp6c1IvfMSa8C5lbize8riMdaOrZp362S4yg4eCwlAC5BldkavGZxXkm6l4VZw5WRrEb764jt0Eqdi4ewZ4d4CbJX3bmH+uHsrL74KOq/3xP6q+vy0pHVkAF0rZNcqX8XxwXFOVZqFSSkmk+6BGpjSQbLNesuYRUY/UZu3Hzp5otHfLcSRyewjRrKaisym52RbAcGwoN4jICPyaLl0UAYwTmEH9Z0WHtZdDs+ymSMC0Aw==";

//$res = 'gt';
//$status = openssl_private_decrypt(base64_decode($masked_pan_enc), $res, $private_rsa_string);
//var_dump($status);
//var_dump($res);

//$secret = '1234';
//var_dump(openssl_private_decrypt($secret, $res, $pkey_private));
//var_dump($res);

//function oaes_decrypt($ciphertext, $privatekey) {
//    $rsa = new \Crypt_RSA();
//    $rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_OAEP);
//    $rsa->setMGFHash('sha1');
//    $rsa->setHash('sha256');
//    $rsa->loadKey($privatekey);
//
//    return $rsa->decrypt($ciphertext);
//}
//
//var_dump(oaes_decrypt($masked_pan_enc, $private_rsa_string));

include("chilkat-9.5.0-php-7.4-x86_64-linux/chilkat_9_5_0.php");

$rsa = new CkRsa();

// First load a public key object with a public key.
// In this case, we'll load it from a file.
$pubkey = new CkPublicKey();
$success = $pubkey->LoadFromFile('rsa_public.pem');
if ($success != true) {
    print $pubkey->lastErrorText() . "\n";
    echo "Public load error!";
    exit;
}

// RSA encryption is limited to small amounts of data. The limit
// is typically a few hundred bytes and is based on the key size and
// padding (OAEP vs. PKCS1_5).  RSA encryption is typically used for
// encrypting hashes or symmetric (bulk encryption algorithm) secret keys.
$plainText = 'Time is an illusion. Lunchtime doubly so.';

// Import the public key to be used for encrypting.
$success = $rsa->ImportPublicKeyObj($pubkey);

// To get OAEP padding, set the OaepPadding property:
$rsa->put_OaepPadding(true);

// To use SHA1 or SHA-256, set the OaepHash property
$rsa->put_OaepHash('sha256');
// for SHA1 --
$rsa->put_OaepHash('sha1');

// Indicate we'll want hex output
$rsa->put_EncodingMode('hex');

// Encrypt..
$usePrivateKey = false;
$encryptedStr = $rsa->encryptStringENC($plainText,$usePrivateKey);
print $encryptedStr . "\n";

// -------------------------------------------------
// Now decrypt with the matching private key.
$rsa2 = new CkRsa();

$privKey = new CkPrivateKey();
$success = $privKey->LoadPem($private_rsa_string);
if ($success != true) {
    print $privKey->lastErrorText() . "\n";
    echo "Error!";
    exit;
}

$success = $rsa2->ImportPrivateKeyObj($privKey);

// Make sure we have the same settings used for encryption.
$rsa2->put_OaepPadding(true);
$rsa2->put_EncodingMode('hex');
$rsa2->put_OaepHash('sha256');

$originalStr = $rsa2->decryptStringENC($encryptedStr,true);

print $originalStr . "\n";
