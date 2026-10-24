<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Input;

use ImranDevBd\AiOcr\Exceptions\ExtractionFailedException;
use ImranDevBd\AiOcr\Exceptions\FileTooLargeException;
use ImranDevBd\AiOcr\Exceptions\PrivacyPolicyViolationException;

final class UrlFetcher
{
    /** @return array{host: string, ip: string, port: int} */
    public function validate(string $url): array
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! in_array($parts['scheme'] ?? '', ['https', 'http'], true) || isset($parts['user']) || isset($parts['pass']) || empty($parts['host'])) {
            throw new PrivacyPolicyViolationException('Only public HTTP(S) URLs without credentials are allowed.');
        }
        $host = trim($parts['host'], '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : array_values(array_unique(array_filter(array_map(fn ($r) => $r['ip'] ?? $r['ipv6'] ?? null, dns_get_record($host, DNS_A | DNS_AAAA) ?: []))));
        if ($ips === []) {
            throw new PrivacyPolicyViolationException('URL host could not be resolved.');
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) || str_contains(strtolower($ip), '::ffff:')) {
                throw new PrivacyPolicyViolationException('Private, reserved and metadata network addresses are blocked.');
            }
        }
        $port = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);
        if (! in_array($port, [80, 443], true)) {
            throw new PrivacyPolicyViolationException('Only HTTP and HTTPS default ports are allowed.');
        }

        return ['host' => $host, 'ip' => $ips[0], 'port' => $port];
    }

    public function fetch(string $url): string
    {
        if (! extension_loaded('curl')) {
            throw new ExtractionFailedException('URL input requires ext-curl for DNS-pinned downloads.');
        }
        $target = $this->validate($url);
        $body = '';
        $oversized = false;
        $max = (int) (config('ai-ocr.limits.max_size_mb', 25) * 1048576);
        $curl = curl_init($url);
        if ($curl === false) {
            throw new ExtractionFailedException('Cannot initialize URL download.');
        }
        try {
            curl_setopt_array($curl, [CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_PROXY => '', CURLOPT_RESOLVE => [$target['host'].':'.$target['port'].':'.(str_contains($target['ip'], ':') ? '['.$target['ip'].']' : $target['ip'])], CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$oversized, $max): int {
                if (strlen($body) + strlen($chunk) > $max) {
                    $oversized = true;

                    return 0;
                } $body .= $chunk;

                return strlen($chunk);
            }]);
            $ok = curl_exec($curl);
            $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($oversized) {
                throw new FileTooLargeException('URL response exceeds the document size limit.');
            }
            if ($ok === false || $status < 200 || $status >= 300) {
                throw new ExtractionFailedException('URL download failed; redirects are disabled.');
            }

            return $body;
        } finally {
            curl_close($curl);
        }
    }
}
