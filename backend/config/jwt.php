<?php
require_once __DIR__ . '/config.php';

class JWT
{
    private $secret_key;
    private $algorithm = 'HS256';

    public function __construct()
    {
        $this->secret_key = defined('JWT_SECRET') ? JWT_SECRET : "your-super-secret-jwt-key-change-this-in-production";
    }

    private function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode($data)
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

    public function encode($payload)
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => $this->algorithm]);
        $payload = json_encode($payload);

        $base64Header = $this->base64UrlEncode($header);
        $base64Payload = $this->base64UrlEncode($payload);

        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $this->secret_key, true);
        $base64Signature = $this->base64UrlEncode($signature);

        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }

    public function decode($jwt)
    {
        $tokenParts = explode('.', $jwt);

        if (count($tokenParts) !== 3) {
            return false;
        }

        $header = $this->base64UrlDecode($tokenParts[0]);
        $payload = $this->base64UrlDecode($tokenParts[1]);
        $signatureProvided = $tokenParts[2];

        $base64Header = $tokenParts[0];
        $base64Payload = $tokenParts[1];

        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $this->secret_key, true);
        $base64Signature = $this->base64UrlEncode($signature);

        if ($base64Signature === $signatureProvided) {
            return json_decode($payload, true);
        }

        return false;
    }

    public function isExpired($payload)
    {
        return isset($payload['exp']) && $payload['exp'] < time();
    }
}
?>
