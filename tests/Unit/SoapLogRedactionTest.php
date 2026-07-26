<?php

use App\Services\Soap\BaseSoapClient;

/**
 * Log files get shipped, backed up and shared. The SOAP envelopes carry the
 * web service password and rider personal data, so neither may be written out
 * verbatim.
 */
function redactXml(string $xml): string
{
    $client = new class extends BaseSoapClient
    {
        // Skip the parent constructor: no WSDL fetch needed to test redaction.
        public function __construct() {}

        protected function getWsdlUrl(): string
        {
            return '';
        }

        public function redactPublic(?string $xml): string
        {
            return $this->redact($xml);
        }
    };

    return $client->redactPublic($xml);
}

test('the service password never reaches the log', function () {
    // Fictional credentials on purpose: the real ones live in the environment
    // and belong nowhere in this repository.
    $xml = '<ns1:Login><ns1:username>ws-user</ns1:username><ns1:password>s3cr3t-value</ns1:password></ns1:Login>';

    $redacted = redactXml($xml);

    expect($redacted)->not->toContain('s3cr3t-value')
        ->and($redacted)->toContain('[REDACTED]')
        // The username is not a secret and is useful for tracing.
        ->and($redacted)->toContain('ws-user');
});

test('personal data is stripped from registration envelopes', function () {
    $xml = '<ns1:person>'
        . '<ns1:Name>Khaled Barakat</ns1:Name>'
        . '<ns1:EID>784-1997-4567891-7</ns1:EID>'
        . '<ns1:Email>rider@example.ae</ns1:Email>'
        . '<ns1:Mobile>+971501234567</ns1:Mobile>'
        . '<ns1:Dob>1997-07-03T00:00:00+00:00</ns1:Dob>'
        . '</ns1:person>';

    $redacted = redactXml($xml);

    expect($redacted)->not->toContain('784-1997-4567891-7')
        ->and($redacted)->not->toContain('rider@example.ae')
        ->and($redacted)->not->toContain('+971501234567')
        ->and($redacted)->not->toContain('1997-07-03')
        // Name is retained: it is what makes a log entry traceable.
        ->and($redacted)->toContain('Khaled Barakat');
});

test('unprefixed elements are redacted too', function () {
    expect(redactXml('<Password>hunter2</Password>'))->not->toContain('hunter2');
});

test('empty input is handled', function () {
    expect(redactXml(''))->toBe('');
});
