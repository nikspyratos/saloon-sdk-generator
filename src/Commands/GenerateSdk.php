<?php

namespace Crescat\SaloonSdkGenerator\Commands;

use cebe\openapi\Reader;
use cebe\openapi\ReferenceContext;
use Crescat\SaloonSdkGenerator\CodeGenerator;
use Crescat\SaloonSdkGenerator\Data\Generator\CodeGenerationResult;
use Crescat\SaloonSdkGenerator\Data\Postman\PostmanCollection;
use Crescat\SaloonSdkGenerator\Parsers\OpenApiParser;
use Crescat\SaloonSdkGenerator\Parsers\PostmanCollectionParser;
use Crescat\SaloonSdkGenerator\Utils;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use Nette\PhpGenerator\PhpFile;

class GenerateSdk extends Command
{
    protected $signature = 'generate:sdk {path} {--type=postman} {--name=Unnamed} {--output=./build} {--force} {--dry}';

    protected array $usedClassNames = [];

    protected array $collectionQueue = [];

    public function handle(): void
    {
        $inputPath = $this->argument('path');

        if (! file_exists($inputPath)) {
            $this->error("File not found: $inputPath");

            return;
        }

        $raw = file_get_contents($inputPath);

        if (! $raw) {
            $this->error('File is empty');

            return;
        }

        $type = trim(strtolower($this->option('type')));
        $parser = match ($type) {
            // TODO: improve "type guessing", should also support remote urls
            'openapi' => new OpenApiParser(
                openApi: str_ends_with($inputPath, '.json')
                    ? Reader::readFromJsonFile(fileName: realpath($inputPath), resolveReferences: ReferenceContext::RESOLVE_MODE_ALL)
                    : Reader::readFromYamlFile(fileName: realpath($inputPath), resolveReferences: ReferenceContext::RESOLVE_MODE_ALL)
            ),
            'postman' => new PostmanCollectionParser(
                PostmanCollection::fromJson(json_decode($raw, true))
            )
        };

        $generator = new CodeGenerator(
            namespace: "App\Sdk",
            resourceNamespaceSuffix: 'Resource',
            requestNamespaceSuffix: 'Requests',
            dtoNamespaceSuffix: 'Dto',
            connectorName: $this->option('name'),
            outputFolder: $this->option('output') ?? './Generated',
            ignoredQueryParams: [
                'after',
                'order_by',
                'per_page',
            ]
        );

        $result = $generator->run($parser);

        $this->option('dry')
            ? $this->printGeneratedFiles($result)
            : $this->dumpGeneratedFiles($result);
    }

    protected function printGeneratedFiles(CodeGenerationResult $result): void
    {
        $this->title('Generated Files');

        $this->comment("\nConnector:");
        if ($result->connectorClass) {
            $this->line(Utils::formatNamespaceAndClass($result->connectorClass));
        }

        $this->comment("\nBase Resource:");
        if ($result->resourceBaseClass) {
            $this->line(Utils::formatNamespaceAndClass($result->connectorClass));
        }

        $this->comment("\nResources:");
        foreach ($result->resourceClasses as $resourceClass) {
            $this->line(Utils::formatNamespaceAndClass($resourceClass));
        }

        $this->comment("\nRequests:");
        foreach ($result->requestClasses as $requestClass) {
            $this->line(Utils::formatNamespaceAndClass($requestClass));
        }
    }

    protected function dumpGeneratedFiles(CodeGenerationResult $result): void
    {

        $this->title('Generated Files');

        $this->comment("\nConnector:");
        if ($result->connectorClass) {
            $this->dumpToFile($result->connectorClass);
        }

        $this->comment("\nBase Resource:");
        if ($result->resourceBaseClass) {
            $this->dumpToFile($result->resourceBaseClass);
        }

        $this->comment("\nResources:");
        foreach ($result->resourceClasses as $resourceClass) {
            $this->dumpToFile($resourceClass);
        }

        $this->comment("\nRequests:");
        foreach ($result->requestClasses as $requestClass) {
            $this->dumpToFile($requestClass);
        }
    }

    protected function dumpToFile(PhpFile $file)
    {

        $wip = sprintf(
            '%s/%s/%s.php',
            $this->option('output'),
            str_replace('App\Sdk', '', Arr::first($file->getNamespaces())->getName()),
            Arr::first($file->getClasses())->getName(),
        );

        $filePath = Str::of($wip)->replace('\\', '/')->replace('//', '/')->toString();

        if (! file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), recursive: true);
        }

        if (file_exists($filePath) && ! $this->option('force')) {
            $this->warn("- File already exists: $filePath");

            return;
        }

        $ok = file_put_contents($filePath, (string) $file);

        if ($ok === false) {
            $this->error("- Failed to write: $filePath");
        } else {
            $this->line("- Created: $filePath");
        }
    }
}
