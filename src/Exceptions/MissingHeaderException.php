<?php namespace Loilo\GithubWebhook\Exceptions;

/**
 * A required header is missing from the webhook request
 */
class MissingHeaderException extends RequestException
{
    /**
     * @var string
     */
    protected $missingHeader;

    /**
     * @param string $message        The error message
     * @param string $missingHeader  The name of the missing header
     */
    public function __construct($message, $missingHeader)
    {
        parent::__construct($message);

        $this->missingHeader = $missingHeader;
    }

    /**
     * Get the name of the missing header
     *
     * @return string
     */
    public function getMissingHeader(): string
    {
        return $this->missingHeader;
    }
}
