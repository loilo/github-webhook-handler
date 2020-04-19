<?php

namespace Loilo\GithubWebhook;

/**
 * Represents the relevant data of a webhook delivery
 */
class Delivery
{
    /**
     * @var array
     */
    private $payload;

    /**
     * @var string
     */
    private $event;

    /**
     * Create a hook request
     *
     * @param string $event   The event of the request
     * @param array  $payload The request's payload
     */
    public function __construct(string $event, array $payload)
    {
        $this->event = $event;
        $this->payload = $payload;
    }

    /**
     * Get the hook request's event name
     *
     * @return string
     */
    public function event(): string
    {
        return $this->event;
    }

    /**
     * Get the hook request's payload
     *
     * @param string|null $path An optional path to get nested payload items
     * @return mixed
     */
    public function payload(?string $path = null)
    {
        if (is_null($path)) {
            return $this->payload;
        }

        $pathParts = explode('.', $path);
        $current = $this->payload;

        foreach ($pathParts as $part) {
            if (is_array($current) && isset($current[$part])) {
                $current = $current[$part];
            } else {
                return null;
            }
        }

        return $current;
    }
}
