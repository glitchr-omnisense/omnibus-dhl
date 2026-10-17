# omnibus/dhl

DHL Express for [omniscient/omnibus](https://github.com/glitchr-studio/omnibus): rates, shipments
and labels, tracking - the MyDHL API (basic auth) - and service points through DHL's Location
Finder (its own API key).

```php
$gateway = (new DhlGatewayFactory($http))->create($options);   // $http: the application's HTTP client - none given, the factory makes its own; the options below
```

No framework needed: the package requires `omniscient/omnibus` and `symfony/http-client`. In a
Symfony application, the same through the bundle's configuration:

```yaml
omnibus:
    gateways:
        dhl:
            factory: dhl
            options:
                api_key: '%env(DHL_API_KEY)%'
                api_secret: '%env(DHL_API_SECRET)%'
                account_number: '%env(DHL_ACCOUNT)%'     # the export account
                sandbox: true
                location_api_key: '%env(DHL_LOCATION_KEY)%'   # optional: service points
                rates: [...]                              # optional: configured prices instead of the rates API
```

The service is the product code (P Express Worldwide, T Express 12:00, K Express 9:00, N Domestic
Express...). Shipment options: `customs` (true for a dutiable shipment; by default, across borders),
`incoterm` (DAP by default), `description`.

Credentials: a MyDHL API app at [developer.dhl.com](https://developer.dhl.com) (key and secret;
the test environment has its own), your DHL Express export account, and - for service points - a
Location Finder (Unified) app's key.

Built from DHL's published API documentation and tested on recorded answers; not yet run against
the test environment: that needs the credentials above.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by DHL Group. It reaches their service on behalf of whoever runs it, and it does not
redistribute their data beyond the short excerpts recorded as test fixtures. Its MIT licence covers
this package's code only and grants no right to the service or to its data.

Whoever installs and runs this package is responsible for complying with DHL's terms
([developer.dhl.com/terms-use](https://developer.dhl.com/terms-use)), for obtaining whatever they
require (an account, a key, a licence or subscription, an attribution or a citation, a rate limit to
respect), and for any use made of what it returns. The authors publish it free of charge, make no
commercial use of it, and accept no liability for how it is used (see [LICENSE](LICENSE)).

License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
