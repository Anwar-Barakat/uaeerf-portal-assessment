<?php

namespace App\Services\Soap;

use SoapClient;
use SoapFault;
use Illuminate\Support\Facades\Log;

abstract class BaseSoapClient
{
    protected ?SoapClient $client = null;

    protected string $wsdlUrl;

    public function __construct()
    {
        $this->wsdlUrl = $this->getWsdlUrl();
    }

    abstract protected function getWsdlUrl(): string;

    /**
     * The SOAP client, built on first use.
     *
     * Constructing one fetches its WSDL over the network. Doing that eagerly
     * meant a controller injecting four SOAP services paid four remote fetches
     * on every request, including requests that used none of them - roughly
     * half a second before any work began.
     */
    protected function client(): SoapClient
    {
        if ($this->client instanceof SoapClient) {
            return $this->client;
        }

        try {
            return $this->client = new SoapClient($this->wsdlUrl, [
                'trace' => config('app.debug'),
                'exceptions' => true,
                // Cache the WSDL everywhere, not just in production: it is a
                // static contract, and re-fetching it per client costs ~100ms.
                'cache_wsdl' => config('services.soap.cache_wsdl', true)
                    ? WSDL_CACHE_DISK
                    : WSDL_CACHE_NONE,
                'connection_timeout' => 15,
                'soap_version' => SOAP_1_1,
            ]);
        } catch (SoapFault $e) {
            Log::error('SOAP client initialization failed', [
                'wsdl' => $this->wsdlUrl,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function call(string $method, array $parameters = []): mixed
    {
        $this->ensureAuthenticated();

        try {
            $result = $this->client()->__soapCall($method, [$parameters]);

            if (config('app.debug')) {
                // "completed", not "successful": a HTTP 200 here routinely
                // carries a business-level rejection in the body.
                Log::debug('SOAP call completed', [
                    'method' => $method,
                    'request' => $this->redact($this->client()->__getLastRequest()),
                    'response' => $this->redact($this->client()->__getLastResponse()),
                ]);
            }

            return $result;
        } catch (SoapFault $e) {
            Log::error('SOAP call failed', [
                'method' => $method,
                'parameters' => $this->redactParameters($parameters),
                'error' => $e->getMessage(),
                'request' => $this->redact($this->client()->__getLastRequest()),
                'response' => $this->redact($this->client()->__getLastResponse()),
            ]);
            throw $e;
        }
    }

    /**
     * Elements whose values must never reach the log: service credentials and
     * personal data. Log files get shipped, backed up and shared.
     */
    protected const REDACTED_ELEMENTS = [
        'password', 'uPass', 'Password',
        'EID', 'Email', 'Mobile', 'MobileNo', 'Telephone', 'ContactTel',
        'HomePhone', 'HomeMobile', 'Dob', 'BloodGroup',
    ];

    protected function redact(?string $xml): string
    {
        if ($xml === null || $xml === '') {
            return '';
        }

        $names = implode('|', array_map('preg_quote', self::REDACTED_ELEMENTS));

        return (string) preg_replace(
            '#<((?:\w+:)?)(' . $names . ')>(?!</).*?</(?:\w+:)?\2>#is',
            '<$1$2>[REDACTED]</$1$2>',
            $xml
        );
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    protected function redactParameters(array $parameters): array
    {
        $sensitive = array_map('mb_strtolower', self::REDACTED_ELEMENTS);

        return array_map(
            fn ($value) => is_array($value) ? $this->redactParameters($value) : $value,
            array_combine(
                array_keys($parameters),
                array_map(
                    fn ($key, $value) => in_array(mb_strtolower((string) $key), $sensitive, true)
                        ? '[REDACTED]'
                        : $value,
                    array_keys($parameters),
                    $parameters
                )
            )
        );
    }

    protected function ensureAuthenticated(): void
    {
        if ($this instanceof AuthenticationService) {
            return;
        }

        if (!app(AuthenticationService::class)->authenticateAndCache()) {
            Log::error('SOAP authentication handshake failed', [
                'service' => static::class,
            ]);

            throw new SoapFault('Client', 'SOAP authentication handshake failed');
        }
    }

    public function getFunctions(): array
    {
        return $this->client()->__getFunctions();
    }

    public function getTypes(): array
    {
        return $this->client()->__getTypes();
    }
}
