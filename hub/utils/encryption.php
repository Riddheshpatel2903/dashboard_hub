<?php
/**
 * Encryption / Decryption Utilities.
 * Uses AES-256-CBC to encrypt sensitive platform tokens.
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Encrypts a plaintext string.
 *
 * @param string $plaintext
 * @return string Base64 encoded IV + Ciphertext
 */
function encrypt($plaintext) {
    if (empty($plaintext)) {
        return $plaintext;
    }
    
    $cipher = 'aes-256-cbc';
    // Generate key derivative to guarantee exactly 32-bytes
    $key = hash('sha256', ENCRYPTION_KEY, true);
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivlen);
    
    $ciphertext_raw = openssl_encrypt($plaintext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
    if ($ciphertext_raw === false) {
        return false;
    }
    
    return base64_encode($iv . $ciphertext_raw);
}

/**
 * Decrypts a base64 encoded IV + Ciphertext.
 *
 * @param string $ciphertext
 * @return string|false Decrypted plaintext or false on failure
 */
function decrypt($ciphertext) {
    if (empty($ciphertext)) {
        return $ciphertext;
    }
    
    $cipher = 'aes-256-cbc';
    $key = hash('sha256', ENCRYPTION_KEY, true);
    $c = base64_decode($ciphertext);
    $ivlen = openssl_cipher_iv_length($cipher);
    
    if (strlen($c) <= $ivlen) {
        return false;
    }
    
    $iv = substr($c, 0, $ivlen);
    $ciphertext_raw = substr($c, $ivlen);
    
    return openssl_decrypt($ciphertext_raw, $cipher, $key, OPENSSL_RAW_DATA, $iv);
}
