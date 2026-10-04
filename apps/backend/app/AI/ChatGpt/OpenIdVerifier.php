<?php

namespace App\AI\ChatGpt;

use Illuminate\Support\Facades\Http;
use phpseclib4\Crypt\RSA;
use phpseclib4\Crypt\RSA\PublicKey;
use Throwable;

final class OpenIdVerifier
{
    /** @return array<string, mixed> */
    public function verify(#[\SensitiveParameter] string $token, string $clientId, string $nonce): array
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3 || strlen($token) > 32768) {
                throw new PlanException('IDENTITY_INVALID');
            }
            $header = json_decode($this->decode($parts[0]), true, flags: JSON_THROW_ON_ERROR);
            $claims = json_decode($this->decode($parts[1]), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($header) || ! is_array($claims) || ($header['alg'] ?? null) !== 'RS256'
                || ! is_string($header['kid'] ?? null)) {
                throw new PlanException('IDENTITY_INVALID');
            }
            $discovery = Http::timeout(10)->get((string) config('chatgpt.discovery_url'));
            $jwksUri = $discovery->json('jwks_uri');
            if (! $discovery->successful() || $discovery->json('issuer') !== 'https://auth.openai.com'
                || ! is_string($jwksUri) || parse_url($jwksUri, PHP_URL_SCHEME) !== 'https'
                || parse_url($jwksUri, PHP_URL_HOST) !== 'auth.openai.com'
                || parse_url($jwksUri, PHP_URL_USER) !== null) {
                throw new PlanException('IDENTITY_KEYS_UNAVAILABLE', 503);
            }
            $jwks = Http::timeout(10)->get($jwksUri);
            if (! $jwks->successful()) {
                throw new PlanException('IDENTITY_KEYS_UNAVAILABLE', 503);
            }
            $verified = false;
            foreach ($jwks->json('keys', []) as $jwk) {
                if (($jwk['kid'] ?? null) !== $header['kid'] || ($jwk['kty'] ?? null) !== 'RSA'
                    || (isset($jwk['alg']) && $jwk['alg'] !== 'RS256') || (isset($jwk['use']) && $jwk['use'] !== 'sig')) {
                    continue;
                }
                $key = RSA::loadPublicKey(json_encode($jwk, JSON_THROW_ON_ERROR));
                if ($key instanceof PublicKey) {
                    $verified = $key->withHash('sha256')->withPadding(RSA::SIGNATURE_PKCS1)
                        ->verify($parts[0].'.'.$parts[1], $this->decode($parts[2]));
                }
            }
            $audience = $claims['aud'] ?? null;
            $audiences = is_array($audience) ? $audience : [$audience];
            if (! $verified || ($claims['iss'] ?? null) !== 'https://auth.openai.com'
                || ! in_array($clientId, $audiences, true)
                || (count($audiences) > 1 && ($claims['azp'] ?? null) !== $clientId)
                || ! is_int($claims['exp'] ?? null) || $claims['exp'] <= time()
                || (isset($claims['nbf']) && (! is_int($claims['nbf']) || $claims['nbf'] > time()))
                || ! is_string($claims['sub'] ?? null) || $claims['sub'] === '' || strlen($claims['sub']) > 255
                || ! is_string($claims['nonce'] ?? null) || ! hash_equals($nonce, $claims['nonce'])) {
                throw new PlanException('IDENTITY_INVALID');
            }

            return $claims;
        } catch (PlanException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new PlanException('IDENTITY_INVALID');
        }
    }

    private function decode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new PlanException('IDENTITY_INVALID');
        }

        return $decoded;
    }
}
