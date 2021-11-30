<?php

namespace AIO\Twig;

use Slim\Csrf\Guard;

class CsrfExtension extends \Twig\Extension\AbstractExtension implements \Twig\Extension\GlobalsInterface
{
    /**
     * @var Guard
     */
    protected Guard $csrf;

    public function __construct(Guard $csrf)
    {
        $this->csrf = $csrf;
    }

    public function getGlobals() : array
    {
        // CSRF token name and value
        $csrfNameKey = $this->csrf->getTokenNameKey();
        $csrfValueKey = $this->csrf->getTokenValueKey();
        $csrfName = $this->csrf->getTokenName();
        $csrfValue = $this->csrf->getTokenValue();

        return [
            'csrf'   => [
                'keys' => [
                    'name'  => $csrfNameKey,
                    'value' => $csrfValueKey
                ],
                'name'  => $csrfName,
                'value' => $csrfValue
            ]
        ];
    }
}