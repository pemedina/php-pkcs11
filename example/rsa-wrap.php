<?php

declare(strict_types=1);

require 'helper.php';

$module = new Pkcs11\Module($modulePath);
$slotList = $module->getSlotList();
$session = $module->openSession($slotList[0], Pkcs11\CKF_RW_SESSION);
$session->login(Pkcs11\CKU_USER, $pinCode);

$keypair = $session->generateKeyPair(new Pkcs11\Mechanism(Pkcs11\CKM_RSA_PKCS_KEY_PAIR_GEN), [
	Pkcs11\CKA_ENCRYPT => true,
	Pkcs11\CKA_MODULUS_BITS => 2048,
	Pkcs11\CKA_PUBLIC_EXPONENT => hex2bin('010001'),
],[
	Pkcs11\CKA_PRIVATE => true,
	Pkcs11\CKA_SENSITIVE => true,
	Pkcs11\CKA_DECRYPT => true,
]);

$key = $session->generateKey(new Pkcs11\Mechanism(Pkcs11\CKM_AES_KEY_GEN), [
	Pkcs11\CKA_CLASS => Pkcs11\CKO_SECRET_KEY,
	Pkcs11\CKA_TOKEN => false,
	Pkcs11\CKA_SENSITIVE => true,
	Pkcs11\CKA_ENCRYPT => true,
	Pkcs11\CKA_DECRYPT => true,
	Pkcs11\CKA_VALUE_LEN => 32,
	Pkcs11\CKA_KEY_TYPE => Pkcs11\CKK_AES,
	Pkcs11\CKA_LABEL => "Test AES",
	Pkcs11\CKA_PRIVATE => true,
	Pkcs11\CKA_EXTRACTABLE => true,
]);

$iv = random_bytes(16);
$aad = '';
$gcmParams = new Pkcs11\GcmParams($iv, $aad, 128);
$mechanismEnc = new Pkcs11\Mechanism(Pkcs11\CKM_AES_GCM, $gcmParams);

$data = 'Hello World!';
$ciphertextData = $key->encrypt($mechanismEnc, $data);
var_dump($data);
var_dump(bin2hex($ciphertextData));


// Yes SHA1, I know, that's all SoftHSM2 supports. Don't yell at me.
$oaepParam = new Pkcs11\RsaOaepParams(Pkcs11\CKM_SHA_1, Pkcs11\CKG_MGF1_SHA1);
$mechanism = new Pkcs11\Mechanism(Pkcs11\CKM_RSA_PKCS_OAEP, $oaepParam);

$ciphertext = $keypair->pkey->wrap($mechanism, $key);
var_dump(bin2hex($ciphertext));

$uwkey = $keypair->skey->unwrap($mechanism, $ciphertext, [
	Pkcs11\CKA_CLASS => Pkcs11\CKO_SECRET_KEY,
	Pkcs11\CKA_TOKEN => false,
	Pkcs11\CKA_SENSITIVE => true,
	Pkcs11\CKA_ENCRYPT => true,
	Pkcs11\CKA_DECRYPT => true,
	Pkcs11\CKA_KEY_TYPE => Pkcs11\CKK_AES,
	Pkcs11\CKA_PRIVATE => true,
	Pkcs11\CKA_EXTRACTABLE => true,
	Pkcs11\CKA_LABEL => "Test unwrapped",
]);

var_dump($uwkey);

$plaintext = $uwkey->decrypt($mechanismEnc, $ciphertextData);
var_dump($plaintext);

$session->logout();
