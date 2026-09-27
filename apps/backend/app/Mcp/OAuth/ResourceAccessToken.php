<?php

namespace App\Mcp\OAuth;

use DateTimeImmutable;
use Laravel\Passport\Bridge\AccessToken as PassportAccessToken;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use League\OAuth2\Server\CryptKeyInterface;
use SensitiveParameter;

final class ResourceAccessToken extends PassportAccessToken
{
    private ?CryptKeyInterface $resourceSigningKey = null;

    public function setPrivateKey(#[SensitiveParameter] CryptKeyInterface $privateKey): void
    {
        parent::setPrivateKey($privateKey);
        $this->resourceSigningKey = $privateKey;
    }

    public function toString(): string
    {
        if ($this->resourceSigningKey === null || ! in_array('mcp:use', array_map(
            static fn ($scope): string => $scope->getIdentifier(),
            $this->getScopes(),
        ), true)) {
            return parent::toString();
        }

        $key = $this->resourceSigningKey;
        $configuration = Configuration::forAsymmetricSigner(
            new Sha256,
            InMemory::plainText($key->getKeyContents(), $key->getPassPhrase() ?? ''),
            InMemory::plainText('empty', 'empty'),
        );
        $now = new DateTimeImmutable;
        $clientId = $this->getClient()->getIdentifier();
        $subject = $this->getUserIdentifier() ?? $clientId;

        return $configuration->builder()
            ->issuedBy(app(McpResource::class)->authorizationServerUri())
            ->permittedFor($clientId)
            ->identifiedBy($this->getIdentifier())
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($this->getExpiryDateTime())
            ->relatedTo($subject)
            ->withClaim('scopes', array_map(static fn ($scope): string => $scope->getIdentifier(), $this->getScopes()))
            ->withClaim('resource', app(McpResource::class)->resourceUri())
            ->getToken($configuration->signer(), $configuration->signingKey())
            ->toString();
    }
}
