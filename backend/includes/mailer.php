<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

class CustomMailer {
    public static function send($to, $subject, $message, $config = null) {
        if (!$config) {
            $config = [
                'host' => SMTP_HOST,
                'port' => SMTP_PORT,
                'user' => SMTP_USERNAME,
                'pass' => SMTP_PASSWORD,
                'from_email' => FROM_EMAIL,
                'from_name' => FROM_NAME
            ];
        }

        $host = (strpos($config['host'], 'ssl://') === 0 || $config['port'] == 465) ? "ssl://" . str_replace('ssl://', '', $config['host']) : $config['host'];
        $socket = fsockopen($host, $config['port'], $errno, $errstr, 15);
        if (!$socket) return false;

        fgets($socket); // banner
        fwrite($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\r\n");
        while ($line = fgets($socket, 512)) if (substr($line, 3, 1) == ' ') break;

        fwrite($socket, "AUTH LOGIN\r\n"); fgets($socket);
        fwrite($socket, base64_encode($config['user']) . "\r\n"); fgets($socket);
        fwrite($socket, base64_encode($config['pass']) . "\r\n"); 
        $authResp = fgets($socket);
        if (strpos($authResp, '235') !== 0) { fclose($socket); return false; }

        fwrite($socket, "MAIL FROM:<{$config['user']}>\r\n"); fgets($socket);
        fwrite($socket, "RCPT TO:<$to>\r\n"); fgets($socket);
        fwrite($socket, "DATA\r\n"); fgets($socket);

        $headers = "MIME-Version: 1.0\r\n" .
                   "Content-type: text/html; charset=UTF-8\r\n" .
                   "From: {$config['from_name']} <{$config['from_email']}>\r\n" .
                   "To: $to\r\n" .
                   "Subject: $subject\r\n" .
                   "Date: " . date('r') . "\r\n\r\n";

        fwrite($socket, $headers . $message . "\r\n.\r\n");
        $dataResp = fgets($socket);
        
        fwrite($socket, "QUIT\r\n");
        fclose($socket);
        return strpos($dataResp, '250') === 0;
    }
}
?>
