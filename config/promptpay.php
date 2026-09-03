<?php
/**
 * Generate EMVCo PromptPay QR Code Payload
 * 
 * @param string $promptpayId Phone number (08xxxxxxxx) or NID/TaxID (13 digits)
 * @param float $amount The amount to charge. If 0, the amount field is omitted.
 * @return string The payload string to encode in QR Code
 */
function generatePromptPayPayload(string $promptpayId, float $amount = 0): string {
    $promptpayId = preg_replace('/[^0-9]/', '', $promptpayId);
    if (empty($promptpayId)) {
        return '';
    }

    $target = '';
    if (strlen($promptpayId) >= 13) {
        // NID / Tax ID
        $target = "0213" . str_pad(substr($promptpayId, 0, 13), 13, '0', STR_PAD_LEFT);
    } else {
        // Phone number
        if (str_starts_with($promptpayId, '0')) {
            $promptpayId = '0066' . substr($promptpayId, 1);
        } else if (!str_starts_with($promptpayId, '0066')) {
            $promptpayId = '0066' . $promptpayId;
        }
        $target = "0113" . str_pad($promptpayId, 13, '0', STR_PAD_LEFT);
    }
    
    $merchantInfo = "0016A000000677010111" . $target;
    $merchantInfoField = "29" . str_pad((string)strlen($merchantInfo), 2, '0', STR_PAD_LEFT) . $merchantInfo;

    $amountField = "";
    if ($amount > 0) {
        $amountStr = number_format($amount, 2, '.', '');
        $amountField = "54" . str_pad((string)strlen($amountStr), 2, '0', STR_PAD_LEFT) . $amountStr;
    }
    
    $poi = $amount > 0 ? "010212" : "010211";

    $payload = "000201" . $poi . $merchantInfoField . "5802TH5303764" . $amountField . "6304";
    
    // Calculate CRC16 CCITT (false)
    $crc = 0xFFFF;
    for ($i = 0; $i < strlen($payload); $i++) {
        $x = (($crc >> 8) ^ ord($payload[$i])) & 0xFF;
        $x ^= $x >> 4;
        $crc = (($crc << 8) ^ ($x << 12) ^ ($x << 5) ^ $x) & 0xFFFF;
    }
    
    return $payload . strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}
