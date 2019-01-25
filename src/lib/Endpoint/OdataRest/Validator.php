<?php

declare(strict_types=1);

/**
 * tubee.io
 *
 * @copyright   Copryright (c) 2017-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Tubee\Endpoint\OdataRest;

use InvalidArgumentException;

class Validator
{
    /**
     * Validate resource.
     */
    public static function validate(array $resource): array
    {
        $defaults = [
            'options' => [
                'identifier' => 'id',
            ],
            'resource' => [
                'request_options' => [],
                'auth' => null,
                'oauth2' => [
                    'token_endpoint' => null,
                    'client_id' => null,
                    'client_secret' => null,
                    'scope' => null,
                ],
                'basic' => [
                    'username' => null,
                    'password' => null,
                ],
            ],
        ];

        if (!isset($resource['resource']['base_uri']) || !is_string($resource['resource']['base_uri'])) {
            throw new InvalidArgumentException('resource.base_uri is required and must be a valid balloon url [string]');
        }

        foreach ($resource['resource'] as $key => $value) {
            if ($value === null) {
                continue;
            }

            switch ($key) {
                case 'auth':
                    if ($value !== 'basic' && $value !== 'oauth2') {
                        throw new InvalidArgumentException('resource.auth must be either basic or oauth2');
                    }

                break;
                case 'request_options':
                case 'oauth2':
                case 'basic':
                case 'base_uri':
                break;
                default:
                    throw new InvalidArgumentException("unknown option resource.$key provided");
            }
        }

        return array_replace_recursive($defaults, $resource);
    }
}
