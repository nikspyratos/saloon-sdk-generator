<?php

namespace Crescat\SaloonSdkGenerator\Parsers;

use Crescat\SaloonSdkGenerator\Contracts\Parser;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;
use Crescat\SaloonSdkGenerator\Data\Postman\Item;
use Crescat\SaloonSdkGenerator\Data\Postman\ItemGroup;
use Crescat\SaloonSdkGenerator\Data\Postman\PostmanCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PostmanCollectionParser implements Parser
{
    protected array $collectionQueue = [];

    public function __construct(protected PostmanCollection $postmanCollection)
    {
    }

    /**
     * @return array|Endpoint[]
     */
    public function parse(): array
    {
        return $this->parseItems($this->postmanCollection->item);
    }

    /**
     * @return array|Endpoint[]
     */
    public function parseItems(array $items): array
    {
        $requests = [];

        foreach ($items as $item) {

            if ($item instanceof ItemGroup) {
                // Nested resource Ids aka "{customer_id}" are not considered a "collection", skip those
                if (! Str::contains($item->name, ['{', '}'])) {
                    $this->collectionQueue[] = $item->name;
                }

                $requests = [...$requests, ...$this->parseItems($item->item)];
                array_pop($this->collectionQueue);
            }

            if ($item instanceof Item) {
                $requests = [...$requests, $this->parseEndpoint($item)];
            }
        }

        return $requests;
    }

    public function parseEndpoint(Item $item): ?Endpoint
    {
        return new Endpoint(
            name: $item->name,
            method: $item->request->method,
            pathSegments: $item->request->url->path,
            collection: end($this->collectionQueue),
            response: $item->request->body?->rawAsJson(),
            description: $item->description,
            queryParameters: $this->parseQueryParameters($item),
            pathParameters: $this->parsePathParameters($item),
            bodyParameters: $this->parseBodyParameters($item),

        );
    }

    protected function parseQueryParameters(Item $item): array
    {
        return collect($item->request->url->query)->map(function ($param) {
            if (! Arr::get($param, 'key')) {
                return null;
            }

            return new Parameter(
                type: 'string',
                nullable: true,
                name: Arr::get($param, 'key'),
                description: Arr::get($param, 'description', '')
            );
        })->filter()->values()->toArray();
    }

    protected function parsePathParameters(Item $item): array
    {
        return collect($item->request->url->path)->filter()->map(function ($param) {
            return new Parameter(
                type: 'string',
                nullable: true,
                name: $param,
            );
        })->filter()->values()->toArray();
    }

    protected function parseBodyParameters(Item $item): array
    {
        $body = $item->request->body?->rawAsJson();

        if (! $body) {
            return [];
        }

        return collect(array_keys($body))
            ->filter()
            ->map(function ($param) {
                return new Parameter(
                    type: 'mixed',
                    nullable: true,
                    name: $param,
                );
            })
            ->toArray();
    }
}
