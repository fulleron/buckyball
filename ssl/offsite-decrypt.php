<?php

/**
 * 1. Copy this file to secure web service that does not have any other access
 * 2. Restrict IP access to secure web service only to main site server
 * 3. Copy your private key into the same folder, or paste it directly inside this file
 * 4. DELETE your private key from the original location
 * 5. Configure modules/BRSA/decrypt_url to point to this url
 *
 * @todo implement logging and throttling
 * @todo create default .htaccess for more restriction?
 *
 * If main site is compromised, regenerate the keys and re-encrypt all data.
 *
 * This will help only when DB or config files were stolen without ability for attacker to run
 * custom scripts from main server.
 */

if (empty($_POST['encrypted'])) {
    return '';
}

// $privateKey = "";
$privateKey = file_get_contents('{hash-key-file-name-here}.key');

$encrypted = base64_decode($_POST['encrypted']);

openssl_private_decrypt($encrypted, $decrypted, $privateKey);

echo base64_encode($decrypted);
