<?php
declare(strict_types=1);

namespace Tvrtke;

final class SudregClient implements SudregGateway
{
    private ?string $token = null;
    private int $expiresAt = 0;
    private const BASE = 'https://sudreg-data.gov.hr/api/javni';
    private const TOKEN_URL = 'https://sudreg-data.gov.hr/api/oauth/token';

    private function request(string $url, ?string $post = null, array $headers = []): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('PHP cURL extension is required');
        }
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if ($post !== null) {
            curl_setopt($handle, CURLOPT_POST, true);
            curl_setopt($handle, CURLOPT_POSTFIELDS, $post);
        }
        $body = curl_exec($handle);
        if ($body === false) {
            $error = curl_error($handle);
            curl_close($handle);
            throw new \RuntimeException('Sudreg network request failed: ' . $error);
        }
        $code = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $headerLength = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        $headerBlock = substr($body, 0, $headerLength);
        $payload = substr($body, $headerLength);
        curl_close($handle);
        if ($code < 200 || $code >= 300) {
            $retryAfter = null;
            if (preg_match('/^retry-after:\\s*(\\d+)\\s*$/im', $headerBlock, $m)) {
                $retryAfter = min(3, max(0, (int)$m[1]));
            }
            // OAuth2 tokens, the original API response and raw client credentials
            // are deliberately excluded from exception text.
            throw new SudregHttpException($code, $retryAfter);
        }
        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Unexpected response type');
        }
        $metadata = [];
        foreach (explode("\n", strtolower($headerBlock)) as $line) {
            if (preg_match('/^x-(snapshot-id|total-count|rows-returned):\s*(\d+)/', trim($line), $m)) {
                $metadata[$m[1]] = (int) $m[2];
            }
        }
        return ['data' => $decoded, 'headers' => $metadata];
    }

    private function accessToken(): string
    {
        if ($this->token !== null && time() < $this->expiresAt - 30) {
            return $this->token;
        }
        $client = Config::require('SUDREG_CLIENT_ID');
        $secret = Config::require('SUDREG_CLIENT_SECRET');
        $response = $this->request(self::TOKEN_URL, http_build_query(['grant_type' => 'client_credentials']), [
            'Authorization: Basic ' . base64_encode($client . ':' . $secret),
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ]);
        $token = $response['data']['access_token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new \RuntimeException('OAuth token missing');
        }
        $this->token = $token;
        $this->expiresAt = time() + max(60, (int) ($response['data']['expires_in'] ?? 300));
        return $token;
    }

    /** @return array{data:array,headers:array} */
    public function get(string $endpoint, array $params = []): array
    {
        $allowed = ['snapshots', 'counts', 'subjekti', 'tvrtke', 'sjedista', 'pravni_oblici', 'predmeti_poslovanja'];
        if (!in_array($endpoint, $allowed, true)) {
            throw new \InvalidArgumentException('Endpoint not allowed');
        }
        $params['no_data_error'] = 'false';
        $url = self::BASE . '/' . $endpoint . '?' . http_build_query($params);
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            try {
                return $this->request($url, null, [
                    'Authorization: Bearer ' . $this->accessToken(),
                    'Accept: application/json',
                    'Content-Type: application/json',
                ]);
            } catch (SudregHttpException $error) {
                // A token can expire or be invalidated independently of its expiry.
                if ($error->status === 401 && $attempt === 0) {
                    $this->token = null;
                    $this->expiresAt = 0;
                    continue;
                }
                if (!in_array($error->status, [429,500,502,503,504], true) || $attempt === 2) {
                    throw $error;
                }
                sleep($error->retryAfter ?? ($attempt + 1));
            }
        }
        throw new \RuntimeException('Sudreg request retry budget exceeded');
    }
}
