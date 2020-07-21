Solid-o Cors
============

This component provides a way to manage cross-domain requests. 

Accordingly the Cross-Origin Resource Sharing (CORS) [specification](https://www.w3.org/TR/cors/) is it possible to define which requests are allowed (and which are not) using a set of headers that browser and server will utilize to communicate each other.

Installation
------------

```bash
composer require solido/cors
```

Configuration
-------------
```yaml
enabled: true
allow_credentials: true
allow_origin: ['*']
allow_headers: ['*']
expose_headers: ['*']
max_age: 0
paths:
  '^/api/':
    enabled: true
    allow_credentials: true
    host: '.+\.foo\.org'
    allow_origin: ['^http://localhost:[0-9]+']
    allow_headers: ['X-User-Id']
    expose_headers: ['*']
    max_age: 3600
```

|name|type|description|default|
|-|:-:|-|-|
| `enabled` | boolean | Enable or disable the CORS management. | `true` |
| `allow_credentials` | boolean | Indicates whether the response to the request can be exposed when the credentials flag is true. | `true` |
| `allow_origin` | string[] | Indicates whether the response can be shared with requesting code from the given origin. | `['*']` | 
| `allow_headers` | string[] | Used in response to a preflight request to indicate which HTTP headers can be used when making the actual request. | `['*']` | 
| `expose_headers` | string[] | Indicates which headers can be exposed as part of the response by listing their names. | `['*']` | 
| `max-age` | int | Indicates how long the results of a preflight request can be cached. | 0 |
| `paths` | array | Specifies a custom configuration for requests matching given path. Accepted config keys are the same as the root level except for `host`, string, which specifies that the given rules portion must be applied only to the given (server) hostname | [] |

