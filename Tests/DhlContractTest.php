<?php

namespace Omnibus\Dhl\Tests;

use Omnibus\Dhl\DhlGatewayFactory;
use Omnibus\GatewayFactoryInterface;
use Omnibus\Testing\GatewayContractTests;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** The conformance suite on DHL Express's recorded answers (MyDHL API test, Location Finder). */
final class DhlContractTest extends TestCase
{
    use GatewayContractTests;

    protected function gatewayFactory(HttpClientInterface $http): GatewayFactoryInterface
    {
        return new DhlGatewayFactory($http);
    }

    protected function gatewayOptions(): array
    {
        return ['api_key' => 'key', 'api_secret' => 'secret', 'account_number' => '123456789', 'sandbox' => true, 'location_api_key' => 'loc-key'];
    }

    protected function operationOptions(): array
    {
        return ['location_api_key' => [\Omnibus\Request\Pickup::class]];
    }

    protected function recordedAnswers(): array
    {
        $point = static fn (string $id, string $name, int $distance) => ['url' => '/locations/'.$id, 'location' => ['ids' => [['locationId' => $id, 'provider' => 'express']]], 'name' => $name, 'distance' => $distance, 'place' => ['address' => ['streetAddress' => '3 Rue des Francs-Bourgeois', 'postalCode' => '67000', 'addressLocality' => 'Strasbourg', 'countryCode' => 'FR'], 'geo' => ['latitude' => 48.58, 'longitude' => 7.75]], 'openingHours' => [['dayOfWeek' => 'http://schema.org/Monday', 'opens' => '08:30:00', 'closes' => '19:00:00']]];

        return [
            '/mydhlapi/test/rates' => ['products' => [
                ['productName' => 'EXPRESS WORLDWIDE', 'productCode' => 'P', 'totalPrice' => [['currencyType' => 'BILLC', 'priceCurrency' => 'EUR', 'price' => 57.4]], 'deliveryCapabilities' => ['totalTransitDays' => 2]],
                ['productName' => 'EXPRESS 12:00', 'productCode' => 'T', 'totalPrice' => [['currencyType' => 'BILLC', 'priceCurrency' => 'EUR', 'price' => 71.0]], 'deliveryCapabilities' => ['totalTransitDays' => 1]],
            ]],
            '/mydhlapi/test/shipments' => ['shipmentTrackingNumber' => '1234567890', 'trackingUrl' => 'https://express.api.dhl.com/track/1234567890', 'documents' => [['typeCode' => 'label', 'imageFormat' => 'PDF', 'content' => base64_encode('%PDF-1.4 dhl')]]],
            '/tracking' => ['shipments' => [['status' => 'Delivered', 'events' => [
                ['date' => '2026-10-01', 'time' => '09:12:00', 'typeCode' => 'PU', 'description' => 'Shipment picked up', 'serviceArea' => [['description' => 'STRASBOURG-FRA']]],
                ['date' => '2026-10-02', 'time' => '11:40:00', 'typeCode' => 'OK', 'description' => 'Delivered - Signed for by', 'serviceArea' => [['description' => 'BERLIN-DEU']]],
            ]]]],
            'location-finder' => ['locations' => [$point('8003-4SA3', 'Press Point', 350), $point('8003-4SA4', 'Tabac', 800), $point('8003-4SA5', 'Locker', 1200)]],
        ];
    }
}
