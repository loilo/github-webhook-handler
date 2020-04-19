<?php

namespace Loilo\GithubWebhook;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Handles GitHub webhook deliveries
 *
 * Roughly based on https://gist.github.com/milo/daed6e958ea534e4eba3
 */
class Handler
{
    /**
     * @var string|null
     */
    protected $secret;

    /**
     * Creates a GitHub webhook handler
     *
     * @param string|null $secret The secret defined for the webhook
     */
    public function __construct($secret = null)
    {
        $this->secret = $secret;
    }

    /**
     * Validate the webhook request and throw an exception when the validation fails
     *
     * @param ServerRequestInterface $request The request to handle
     * @return Delivery
     *
     * @throws Exceptions\InvalidMethodException If a HTTP method other than "POST" was used
     * @throws Exceptions\MissingHeaderException If a mandatory header is missing from the request
     * @throws Exceptions\InvalidContentTypeException If an unsupported Content-Type header was provided
     * @throws Exceptions\ServerException If the environment does not offer a functionality necessary for request validation
     * @throws Exceptions\InvalidSecretException If the provided hook secret was deemed invalid
     * @throws Exceptions\InvalidPayloadException If the payload was not submitted in the expected format
     */
    public function handle(ServerRequestInterface $request): Delivery
    {
        if ($request->getMethod() !== 'POST') {
            throw new Exceptions\InvalidMethodException(
                'This endpoint only accepts POST requests.'
            );
        }

        if (!$request->hasHeader('Content-Type')) {
            throw new Exceptions\MissingHeaderException(
                'Missing HTTP "Content-Type" header.',
                'Content-Type'
            );
        }

        if (
            !in_array($request->getHeaderLine('Content-Type'), [
                'application/json',
                'application/x-www-form-urlencoded',
            ])
        ) {
            throw new Exceptions\InvalidContentTypeException(
                sprintf(
                    'Unsupported content type: "%s"',
                    $request->getHeaderLine('Content-Type')
                )
            );
        }

        if (!$request->hasHeader('X-GitHub-Event')) {
            throw new Exceptions\MissingHeaderException(
                'Missing HTTP "X-GitHub-Event" header.',
                'X-GitHub-Event'
            );
        }

        if (!$request->hasHeader('X-GitHub-Delivery')) {
            throw new Exceptions\MissingHeaderException(
                'Missing HTTP "X-GitHub-Delivery" header.',
                'X-GitHub-Delivery'
            );
        }

        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $body = (string) $request->getBody();
        } else {
            $body = $request->getParsedBody()['payload'] ?? '';
        }

        // Validate secret if provided
        if (!is_null($this->secret)) {
            if (!$request->hasHeader('X-Hub-Signature')) {
                throw new Exceptions\MissingHeaderException(
                    'HTTP header "X-Hub-Signature" is missing.',
                    'X-Hub-Signature'
                );
            } elseif (!extension_loaded('hash')) {
                throw new Exceptions\ServerException(
                    'Missing "hash" extension to check the secret code validity.'
                );
            }

            [$algorithm, $hash] = explode(
                '=',
                $request->getHeaderLine('X-Hub-Signature'),
                2
            ) + ['', ''];

            if (!in_array($algorithm, hash_algos(), true)) {
                throw new Exceptions\ServerException(
                    'Hash algorithm "' . $algorithm . '" is not supported.'
                );
            }

            if (
                !hash_equals($hash, hash_hmac($algorithm, $body, $this->secret))
            ) {
                throw new Exceptions\InvalidSecretException(
                    'Hook secret does not match.'
                );
            }
        }

        $parsedBody = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsedBody)) {
            throw new Exceptions\InvalidPayloadException(
                'Payload must be a JSON object.'
            );
        }

        return new Delivery(
            $request->getHeaderLine('X-GitHub-Event'),
            $parsedBody
        );
    }

    /**
     * Sets the HTTP response code and exits the script when an error occurs.
     * Designed as a drop-in solution for single files.
     *
     * @param ServerRequestInterface $request The request to handle
     * @return Delivery
     */
    public function respond(ServerRequestInterface $request): Delivery
    {
        try {
            return $this->handle($request);
        } catch (
            Exceptions\InvalidContentTypeException |
            Exceptions\InvalidPayloadException |
            Exceptions\MissingHeaderException $e
        ) {
            http_response_code(400);
        } catch (Exceptions\InvalidSecretException $e) {
            http_response_code(401);
        } catch (Exceptions\InvalidMethodException $e) {
            http_response_code(405);
        } catch (Exceptions\ServerException $e) {
            http_response_code(500);
        }

        header('Content-Type: text/plain');
        echo $e->getMessage();
        exit();
    }
}
