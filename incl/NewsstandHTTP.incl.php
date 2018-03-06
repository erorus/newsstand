<?php

namespace Newsstand;

class HTTP
{
    private static $curl_handle = null;
    private static $curl_seen_hosts = [];

    private static function GetCurl() {
        static $registeredShutdown = false;

        if (is_null(static::$curl_handle)) {
            static::$curl_handle = curl_init();
        } else {
            curl_reset(static::$curl_handle);
        }

        if (!$registeredShutdown) {
            $registeredShutdown = true;
            register_shutdown_function([HTTP::class, 'CloseCurl']);
        }

        return static::$curl_handle;
    }

    public static function CloseCurl() {
        if (!is_null(static::$curl_handle)) {
            curl_close(static::$curl_handle);
            static::$curl_handle = null;
        }
    }

    private static function NeedsNewConnection($url) {
        $urlParts = parse_url($url);
        if (!isset($urlParts['host'])) {
            return true;
        }
        if (!isset($urlParts['port'])) {
            $urlParts['port'] = '';
            if (isset($urlParts['scheme'])) {
                switch ($urlParts['scheme']) {
                    case 'http':
                        $urlParts['port'] = 80;
                        break;
                    case 'https':
                        $urlParts['port'] = 443;
                        break;
                }
            }
        }
        $hostKey = $urlParts['host'].':'.$urlParts['port'];
        if (isset(static::$curl_seen_hosts[$hostKey])) {
            return false;
        }
        static::$curl_seen_hosts[$hostKey] = true;
        return true;
    }

    public static function AbandonConnections() {
        $oldHosts = array_keys(static::$curl_seen_hosts);
        static::$curl_seen_hosts = [];
        return $oldHosts;
    }

    public static function Get($url, $inHeaders = [], &$outHeaders = []) {
        return static::SendRequest($url, 'GET', [], $inHeaders, $outHeaders);
    }

    public static function Post($url, $toPost, $inHeaders = [], &$outHeaders = []) {
        if (is_array($toPost)) {
            $postStr = '';
            foreach ($toPost as $k => $v) {
                $postStr .= ($postStr == '' ? '' : '&') . sprintf('%s=%s', urlencode($k), urlencode($v));
            }
            $toPost = $postStr;
        }
        return static::SendRequest($url, 'POST', $toPost, $inHeaders, $outHeaders);
    }

    public static function Head($url, $inHeaders = []) {
        static::SendRequest($url, 'HEAD', [], $inHeaders, $outHeaders);
        return $outHeaders;
    }

    private static function SendRequest($url, $method, $toPost, $inHeaders, &$outHeaders)
    {
        static $isRetry = false;
        $wasRetry = $isRetry;
        $isRetry = false;

        $outHeaders = [];
        
        $ch = static::GetCurl();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_HEADER         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SAFE_UPLOAD    => true,
            CURLOPT_FRESH_CONNECT  => static::NeedsNewConnection($url),
            CURLOPT_SSLVERSION     => 6, //CURL_SSLVERSION_TLSv1_2,
            CURLOPT_TIMEOUT        => PHP_SAPI == 'cli' ? 30 : 8,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_ENCODING       => 'gzip',
            CURLOPT_HTTPHEADER     => $inHeaders,
        ]);
        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $toPost);
                if (PHP_SAPI == 'cli') {
                    curl_setopt($ch, CURLOPT_TIMEOUT, max(30, ceil((is_array($toPost) ? strlen(implode($toPost)) : strlen($toPost))/204800)));
                }
                break;
            case 'HEAD':
                curl_setopt($ch, CURLOPT_NOBODY, true);
                break;
        }

        $data = curl_exec($ch);
        $errMsg = curl_error($ch);
        if ($errMsg) {
            $outHeaders['curlError'] = $errMsg;
            trigger_error(sprintf("cURL error fetching %s - %d %s", $url, curl_errno($ch), $errMsg), E_USER_NOTICE);
            return false;
        }

        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $downloadSize = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
        $headerLines = [];
        do {
            $pos = strpos($data, "\r\n\r\n");
            if ($pos === false) {
                break;
            }
            $headerLines = explode("\r\n", substr($data, 0, $pos));
            $data = substr($data, $pos + 4);
        } while ($data &&
            preg_match('/^HTTP\/\d+\.\d+ (\d+)/', $headerLines[0], $res) &&
            ($res[1] != $responseCode)); // mostly to handle 100 Continue, maybe 30x redirects too

        $headers = [];
        foreach ($headerLines as $headerLine) {
            if (preg_match('/^([^:]+):\s*([\w\W]+)/', $headerLine, $headerLineParts)) {
                $headers[$headerLineParts[1]] = $headerLineParts[2];
            }
        }
        $outHeaders = array_merge(['responseCode' => $responseCode, 'X-Original-Content-Length' => $downloadSize], $headers);

        if (preg_match('/^2\d\d$/', $responseCode) > 0) {
            return $data;
        } else {
            $outHeaders['body'] = $data;
            if (!$wasRetry && isset($headers['Retry-After']) && preg_match('/^5\d\d$/', $responseCode)) {
                if (preg_match('/^\d+$/', trim($headers['Retry-After']))) {
                    $delay = intval($headers['Retry-After'], 10);
                } else {
                    $delay = max(0, strtotime($headers['Retry-After']) - time());
                }
                if ($delay > 0 && $delay <= 10) {
                    sleep($delay);
                    $isRetry = true;
                    return static::SendRequest($url, $method, $toPost, $inHeaders, $outHeaders);
                }
            }
        }
        return false;
    }

}
