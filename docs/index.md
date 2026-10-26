# omnibus/dhl

DHL Express for [omnisense/omnibus](https://github.com/glitchr-omnisense/omnibus): rates, shipments
and labels and tracking through the MyDHL API (HTTP basic authentication), and DHL service points
through DHL's Location Finder (its own API key). The gateway calls:

| Operation | API | Request |
|---|---|---|
| rate | MyDHL API | `POST /rates` |
| ship | MyDHL API | `POST /shipments` |
| track | MyDHL API | `GET /shipments/{trackingNumber}/tracking` |
| pickup points | Location Finder | `GET https://api.dhl.com/location-finder/v1/find-by-address` |

MyDHL paths are on `https://express.api.dhl.com/mydhlapi` (live) or
`https://express.api.dhl.com/mydhlapi/test` (the test environment, `sandbox: true`). The Location
Finder has one address, whatever `sandbox` says.

## Installation

```sh
composer require omnibus/dhl
```

Requires PHP 8.2, `omnisense/omnibus` (2.x) and `symfony/http-client` (6.4, 7 or 8); given no
HTTP client, the factory makes one with `HttpClient::create()`. In a Symfony application with
`Omnibus\Bridge\Symfony\OmnibusBundle`, the factory is registered as soon as the package is
installed.

## Credentials

- A MyDHL API app at [developer.dhl.com](https://developer.dhl.com): its **API key** and **API
  secret** (the test environment has its own pair).
- The shop's **DHL Express export account** number: it pays for the shipments (account type
  `shipper`) and prices them.
- For service points only, a **Location Finder (Unified)** app's key, sent as `DHL-API-Key`.

## Options

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `api_key` | string | - | yes | the MyDHL API key (basic authentication user) |
| `api_secret` | string | - | yes | the MyDHL API secret (basic authentication password) |
| `account_number` | string | - | yes | the export account |
| `sandbox` | bool | `false` | no | `true`: the MyDHL test environment |
| `location_api_key` | ?string | `null` | no | the Location Finder's key; without it the gateway does not support `Pickup` |
| `rates` | list | - | no | prices from configuration instead of `POST /rates` ([the rates option](https://github.com/glitchr-omnisense/omnibus/blob/2.x/docs/configuration.md#the-rates-option)): with it, the rates API is not called |

Shipment options (`Shipment::$options`):

| Option | Read by | Default | |
|---|---|---|---|
| `customs` | rate, ship | `true` when sender and recipient countries differ | whether the shipment is dutiable (`isCustomsDeclarable`) |
| `incoterm` | ship | `DAP` | sent only for a dutiable shipment |
| `description` | ship | `Parcel` | the content's description |

## Capabilities

| Request | Supported |
|---|---|
| `Rating` | yes (MyDHL rates), or configured rates with `rates` |
| `Shipping` | yes |
| `Tracking` | yes |
| `Pickup` | only with `location_api_key` (the Location Finder) |
| `GetSlip` | no |
| `Cancel` | no |
| `Paperless` | no |

## Behaviour

**Calls.** Every MyDHL call uses basic authentication with the key and secret, sends JSON with a
random 32-character `Message-Reference`, and has a 20-second timeout. There is no token to keep.

**Addresses and parcels.** An address is its postcode, city, country and first two street lines;
its contact the email, the phone (`0` when the address has none), the company (the person's name
when there is none) and the person's name. No state or province is sent. A parcel is its weight in
kilograms rounded to two decimals (at least 0.1 kg), its dimensions in centimetres when all three
are given, and its `reference` (35 characters) when it has one. Units are metric.

**Shipping date.** `Shipment::$shippingDate`, else tomorrow at 10:00 in PHP's default time zone,
written `Y-m-d\TH:i:s GMTP` (`2026-10-26T10:00:00 GMT+01:00`).

**Rate.** `POST /rates` with both postal addresses, the account, the shipping date, `customs`, and
each parcel's weight and dimensions; `Shipment::$service` is not sent. Each product is a rate:
the service is its `productCode`, the label its `productName`, the amount its total price in the
billing currency (`currencyType BILLC`; else the first total listed), `days`
`deliveryCapabilities.totalTransitDays`. Rates come cheapest first; `toPickupPoint` is always
`false`. The product codes are DHL's: `P` Express Worldwide, `T` Express 12:00, `K` Express 9:00,
`N` Domestic Express, and the others DHL lists for the lane.

**Ship.** `POST /shipments`:

- product `Shipment::$service`, else `P` (Express Worldwide);
- no pickup is requested (`pickup.isRequested: false`): the parcel is handed over or collected as
  the account arranges;
- the shipment's `reference` (35 characters) as customer reference;
- for a dutiable shipment: the `incoterm`, the declared value (the sum of the parcels' `value`,
  in the first parcel's currency);
- a PDF label, template `ECOM26_84_001`.

The tracking number is `shipmentTrackingNumber`; the label's content the document of type `label`,
base64-decoded; the format `application/pdf`. `trackingUrl` is
`https://www.dhl.com/global-en/home/tracking.html?tracking-id=<number>`. `Label::$url` is set to
the `trackingUrl` field of DHL's answer, which is not a document to download (see "Verified, and
not"). A response without a number is a `CarrierException` (`DHL booked no shipment.`).
`pickupPoint` is not sent: delivery to a service point is not booked by this package.

> [!WARNING]
> Known defect: `Label::$url` holds DHL's tracking page, where it should be a document to download (or `null`).

**Track.** `GET` with `trackingView=all-checkpoints` and `levelOfDetail=all`; the locale is not
sent. The events of the first shipment are read from their `date` and `time` (in PHP's default
time zone, no offset), with DHL's `typeCode` as `code` and the first service area's description as
`location`, sorted oldest first. The status is the latest event's, or `DELIVERED` when the
shipment's own `status` is `Delivered`:

| DHL type code | `TrackingStatus` |
|---|---|
| `OK`, `DD` | `DELIVERED` |
| `WC` | `OUT_FOR_DELIVERY` |
| `PU`, `AR`, `DF`, `PL`, `AF`, `TR`, `CR`, `CC` | `IN_TRANSIT` |
| `RD`, `ND`, `OH`, `CA`, `BA`, `UD`, `MC` | `EXCEPTION` |
| `RT` | `RETURNED` |
| `SA`, `PE` | `PENDING` |
| anything else | `UNKNOWN` |

**Pickup points.** `find-by-address` with the country, postcode, city and first street line,
`providerType=express`, at most `min(50, limit)`; no radius is sent and the parcel is not used. The
id is the first `locationId` (else the location's URL), the name the location's name (else `DHL
ServicePoint`), the address its street, postcode, locality and country, the distance the Location
Finder's `distance` as an integer (metres). Opening hours come from `openingHours`: the schema.org
day (`http://schema.org/Monday`...) as ISO weekday, `opens` and `closes` cut to `HH:MM`. The
points are kept in the Location Finder's order and cut to `limit`.

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnibus\Dhl\DhlGatewayFactory;
use Omnibus\Model\Address;
use Omnibus\Model\Parcel;
use Omnibus\Model\Shipment;
use Symfony\Component\HttpClient\HttpClient;

$dhl = (new DhlGatewayFactory(HttpClient::create()))->create([
    'api_key' => getenv('DHL_API_KEY'),
    'api_secret' => getenv('DHL_API_SECRET'),
    'account_number' => getenv('DHL_ACCOUNT'),
    'location_api_key' => getenv('DHL_LOCATION_KEY'),
    'sandbox' => true,
]);

$shipment = new Shipment(
    new Address('Glitch Art', ['12 rue de la Paix'], '75002', 'Paris', 'FR', phone: '+33102030405'),
    new Address('Ada Lovelace', ['Unter den Linden 1'], '10117', 'Berlin', 'DE', email: 'ada@example.org', phone: '+49301234567'),
    [new Parcel(1200, 30, 20, 10, reference: 'BOX-1')],
    service: 'P',
    reference: 'CMD-1042',
    options: ['description' => 'Prints'],
    shippingDate: new \DateTimeImmutable('tomorrow 14:00'),
);

foreach ($dhl->rate($shipment) as $rate) {
    printf("%s %-24s %6.2f %s, %s days\n", $rate->service, $rate->label, $rate->amount / 100, $rate->currency, $rate->days ?? '?');
}

$label = $dhl->ship($shipment);
file_put_contents($label->trackingNumber.'.pdf', $label->content);
echo $label->trackingNumber, ' ', $label->trackingUrl, "\n";

$tracking = $dhl->track($label->trackingNumber);
echo $tracking->status->value, ' ', $tracking->latest()?->location, "\n";

foreach ($dhl->pickupPoints($shipment->recipient, 3) as $point) {
    printf("%s %s %d m\n", $point->id, $point->name, $point->distance);
}
```

In Symfony:

```yaml
omnibus:
    gateways:
        dhl:
            factory: dhl
            options:
                api_key: '%env(DHL_API_KEY)%'
                api_secret: '%env(DHL_API_SECRET)%'
                account_number: '%env(DHL_ACCOUNT)%'
                sandbox: '%env(bool:DHL_SANDBOX)%'
                location_api_key: '%env(DHL_LOCATION_KEY)%'   # leave it out: no service points
```

## Errors

Every failure is an `Omnibus\Exception\CarrierException` whose `carrier` is `dhl`:

- a MyDHL HTTP status of 400 or more: the answer's `detail`, else `message`, else `title`, else
  `HTTP <status>`; the answer's `status` in `carrierCode`;
- a network error or a body that is not JSON: `DHL request failed: ...` or `DHL answered HTTP
  <status> with a body that is not JSON.`;
- a Location Finder HTTP status of 400 or more: its `detail`, else `title`, else `DHL location
  finder answered HTTP <status>.`; a network error or unreadable body: `DHL location finder
  failed: ...`;
- `DHL booked no shipment.` when the answer has no tracking number.

A missing `api_key`, `api_secret` or `account_number` is an `InvalidConfigException` when the
gateway is built; `GetSlip`, `Cancel`, `Paperless`, and `Pickup` without `location_api_key`, raise
`RequestNotSupportedException` before any call.

## Verified, and not

Tested offline: `Tests/DhlGatewayTest.php` (products cheapest first with their transit days, the
account and metric units in the request; a shipment booked with product `P` and its PDF label;
events sorted with the shipment's `Delivered` status winning; service points with their id,
distance and Monday hours) and `Tests/DhlContractTest.php`, the family's conformance suite on
recorded answers (`Pickup` supported only with `location_api_key`). The example above was run in
the core's harness on those recorded answers.

Not verified: the README says the package is built from DHL's published API documentation and was
not yet run against the test environment; that needs the credentials above. `Label::$url` carries
the `trackingUrl` of DHL's booking answer (a MyDHL tracking address in the recorded answer), not a
label to download: the label itself is in `content`.

## Responsibility

An independent, unofficial client, not affiliated with DHL Group. Whoever runs it complies with DHL's
terms ([developer.dhl.com/terms-use](https://developer.dhl.com/terms-use)): see the README's
Responsibility section.
