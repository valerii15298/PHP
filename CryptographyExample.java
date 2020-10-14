package com.soldo.crypto;

import org.bouncycastle.jce.provider.BouncyCastleProvider;
import org.bouncycastle.util.io.pem.PemObject;
import org.bouncycastle.util.io.pem.PemReader;

import javax.crypto.Cipher;
import java.io.IOException;
import java.io.StringReader;
import java.security.KeyFactory;
import java.security.Security;
import java.security.NoSuchAlgorithmException;
import java.security.PrivateKey;
import java.security.PublicKey;
import java.security.Signature;
import java.security.interfaces.RSAPrivateKey;
import java.security.spec.InvalidKeySpecException;
import java.security.spec.PKCS8EncodedKeySpec;
import java.security.spec.X509EncodedKeySpec;
import java.util.Base64;

import static java.nio.charset.StandardCharsets.UTF_8;

public class CryptographyExample {

    public static final String RSA_ECB_OAEP_WITH_SHA256_AND_MGF1_PADDING = "RSA/ECB/OAEPWithSHA-256ANDMGF1Padding";
    private static final String SHA_512_WITH_RSA = "SHA512withRSA";
    private static final String RSA = "RSA";
    /*
        private and public are key generated using KeyPairGenerator java class:
        java.security.KeyPairGenerator keyPairGen = java.security.KeyPairGenerator.getInstance("RSA");
        keyPairGen.initialize(2048);
        KeyPair pair = keyPairGen.generateKeyPair();
     */
    private static final String privateKeyStr = "-----BEGIN RSA PRIVATE KEY-----\n" +
            "confident_info\n" +
            "-----END RSA PRIVATE KEY-----";

    // format X.509 SubjectPublicKeyInfo/OpenSSL PEM public key
    private static final String publicKeyStr = "-----BEGIN PUBLIC KEY-----\n" +
            "confident_info\n" +
            "-----END PUBLIC KEY-----";

    public static String getSignatureByPrivateKey(String message) {
        String result = null;
        try {
            Signature signature = Signature.getInstance(SHA_512_WITH_RSA);
            signature.initSign(getPrivateKeyByString());
            signature.update(message.getBytes(UTF_8));
            byte[] encodedSignedString = Base64.getEncoder().encode(signature.sign());
            result = new String(encodedSignedString, UTF_8);
        } catch (Exception e) {
            System.out.println("getSignature " + e.getMessage());
        }
        return result;
    }

    private static PrivateKey getPrivateKeyByString() throws IOException, NoSuchAlgorithmException, InvalidKeySpecException {
        PemObject pem = new PemReader(new StringReader(CryptographyExample.privateKeyStr)).readPemObject();
        byte[] der = pem.getContent();
        KeyFactory keyFactory = KeyFactory.getInstance(RSA);
        PKCS8EncodedKeySpec ks = new PKCS8EncodedKeySpec(der);
        RSAPrivateKey privKey = (RSAPrivateKey) keyFactory.generatePrivate(ks);
        return privKey;
    }

    public static boolean checkSignatureByPublicKey(String message, String signedMessage) {
        try {
            Signature signature = Signature.getInstance(SHA_512_WITH_RSA);
            signature.initVerify(getPublicKeyByString());
            signature.update(message.getBytes(UTF_8));
            final byte[] bytes = Base64.getDecoder().decode(signedMessage);
            return signature.verify(bytes);
        } catch (Exception e) {
            System.out.println("checkSignature " + e.getMessage());
        }
        return false;
    }

    private static PublicKey getPublicKeyByString() throws IOException, NoSuchAlgorithmException, InvalidKeySpecException {
        PemObject pem = new PemReader(new StringReader(CryptographyExample.publicKeyStr)).readPemObject();
        X509EncodedKeySpec ks = new X509EncodedKeySpec(pem.getContent());
        KeyFactory kf = KeyFactory.getInstance(RSA);
        return kf.generatePublic(ks);
    }

    public static String cryptMessageByPublicKey(String message) throws Exception {
        return encrypt(getPublicKeyByString(), message.getBytes(UTF_8));

    }

    private static String encrypt(PublicKey key, byte[] plainText) throws Exception {
        Cipher cipher = Cipher.getInstance(RSA_ECB_OAEP_WITH_SHA256_AND_MGF1_PADDING, BouncyCastleProvider.PROVIDER_NAME);
        cipher.init(Cipher.ENCRYPT_MODE, key);
        byte[] result = Base64.getEncoder().encode(cipher.doFinal(plainText));
        return new String(result, UTF_8);
    }

    public static String decryptMessageByPrivateKey(String cryptedMessage) throws Exception {
        return decrypt(getPrivateKeyByString(), cryptedMessage.getBytes(UTF_8));
    }

    private static String decrypt(PrivateKey key, byte[] cipherText) throws Exception {
        final byte[] decode = Base64.getDecoder().decode(cipherText);
        Cipher cipher = Cipher.getInstance(RSA_ECB_OAEP_WITH_SHA256_AND_MGF1_PADDING, BouncyCastleProvider.PROVIDER_NAME);
        cipher.init(Cipher.DECRYPT_MODE, key);
        return new String(cipher.doFinal(decode), UTF_8);
    }

    public static void main(String[] args) throws Exception {
        Security.addProvider(new BouncyCastleProvider());

//        String clearMessage = "Hello world!";
//        System.out.println("Clear message: " + clearMessage);
//
//        final String signature = getSignatureByPrivateKey(clearMessage);
//        System.out.println("Signed message: " + signature);
//
//        final boolean check = checkSignatureByPublicKey(clearMessage, signature);
//        System.out.println("Check signature: " + check);
//
//        final String cryptMessage = cryptMessageByPublicKey(clearMessage);
//        System.out.println("Crypt message: " + cryptMessage);

        final String cryptMessage = args[0];
        final String decryptMessage = decryptMessageByPrivateKey(cryptMessage);
        System.out.println(decryptMessage);
    }
}
