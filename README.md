<p align="center"><img src=".github/header.png"></p>

# Saloon SDK Generator

This package offers a convenient solution for generating a PHP SDK utilizing the [Saloon](https://docs.saloon.dev/)
package.

It is designed to work with both Postman Collection JSON files (v2.1) and OpenAPI specifications. With this
tool, you can seamlessly convert your Postman Collection or OpenAPI specifications into a comprehensive PHP SDK,
enabling smooth interactions with APIs.

Additionally, the package provides support for creating custom parsers, allowing
you to integrate it with various API specification formats beyond the built-in support.

## Installation

You can install this package using Composer:

```shell
composer require crescat/saloon-sdk-generator
```

## Usage

To generate the PHP SDK from an API specification file, run the following Artisan command:

```shell
./codegen generate:sdk API_SPEC_FILE.{json|yaml|yml}
     --type={postman|openapi} 
    [--name=SDK_NAME] 
    [--output=OUTPUT_PATH] 
    [--force] 
    [--dry] 
    [--zip]
```

Replace the placeholders with the appropriate values:

- `API_SPEC_FILE`: Path to the API specification file (JSON or YAML format).
- `--type`: Specify the type of API specification (`postman` or `openapi`).
- `--name`: (Optional) Specify the name of the generated SDK (default: Unnamed).
- `--output`: (Optional) Specify the output path where the generated code will be created (default: ./Generated).
- `--force`: (Optional) Force overwriting existing files.
- `--dry`: (Optional) Perform a dry run. It will not save generated files, only show a list of them.
- `--zip`: (Optional) Use this flag to generate a zip archive containing all the generated files.

## Using the Code Generator and Parser Programmatically

1. **Configure Your Generator:**

Configure the `CodeGenerator` with the desired settings:

```php
$generator = new CodeGenerator(
   namespace: "App\Sdk",
   resourceNamespaceSuffix: 'Resource',
   requestNamespaceSuffix: 'Requests',
   dtoNamespaceSuffix: 'Dto',
   connectorName: 'MySDK', // Replace with your desired SDK name
   outputFolder: './Generated', // Replace with your desired output folder
   ignoredQueryParams: ['after', 'order_by', 'per_page'] // Ignore params used for pagination
);
```

2. **Parse and Generate:**

Parse your API specification file and generate the SDK classes:

```php
$inputPath = 'path/to/api_spec_file.json'; // Replace with your API specification file path
$type = 'postman'; // Replace with your API specification type

$result = $generator->run(Factory::parse($type, $inputPath));
```

3. **Use Generated Results:**

You can access the generated classes and perform actions with them:

```php
// Generated Connector Class
echo "Generated Connector Class: " . Utils::formatNamespaceAndClass($result->connectorClass) . "\n";

// Generated Base Resource Class
echo "Generated Base Resource Class: " . Utils::formatNamespaceAndClass($result->resourceBaseClass) . "\n";

// Generated Resource Classes
foreach ($result->resourceClasses as $resourceClass) {
   echo "Generated Resource Class: " . Utils::formatNamespaceAndClass($resourceClass) . "\n";
}

// Generated Request Classes
foreach ($result->requestClasses as $requestClass) {
   echo "Generated Request Class: " . Utils::formatNamespaceAndClass($requestClass) . "\n";
}
```

## Building a Custom Parser

If you're working with an API specification format that isn't natively supported by the Saloon SDK Generator, you can
build a custom parser to integrate it. Here's how you can do it:

1. **Create Your Custom Parser:**

Start by creating a custom parser class that implements the `Crescat\SaloonSdkGenerator\Contracts\Parser` interface.

There are two ways to initialize a Parser. The first one is through the constructor, which will receive the filePath
specified when running `./codegen generate:sdk {FILE_PATH}`.

Example:

```php
<?php

use Crescat\SaloonSdkGenerator\Contracts\Parser;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;

class CustomParser implements Parser
{
    public function __construct(protected $filePath) {}

    public function parse(): ApiSpecification
    {
        // TODO: Implement
        parseTheContents($this->filepath)
        // Implement a parser that will return an ApiSpecification object:

        return new ApiSpecification(
            name: 'Custom API',
            description: 'A custom API specification',
            baseUrl: 'https://api.example.com',
            endpoints: [
                new Endpoint(
                    name: 'GetUserInfo',
                    method: 'GET',
                    pathSegments: ['users', '{user_id}'],
                    description: 'Get user information by ID',
                    queryParameters: [
                        new Parameter('string', false, 'user_id', 'User ID'),
                    ],
                ), new Endpoint(
                    name: 'CreateUser',
                    method: 'POST',
                    pathSegments: ['users'],
                    description: 'Create a new user',
                    bodyParameters: [
                        new Parameter('string', false, 'username', 'Username'),
                        new Parameter('string', false, 'email', 'Email'),
                    ],

                )
            ],
        );
    }
}
```

Or, if you need to pre-process the file, either over the network or run third party code that is not suitable for the
constructor, you may add a `build` method, which will be called instead.

Example from the OpenApiParser:

```php
public static function build($content): self
{
    // Call file readers depending on the filetype provided (supports JSON and YAML)
    return new self(
        Str::endsWith($content, '.json')
            ? Reader::readFromJsonFile(fileName: realpath($content), resolveReferences: ReferenceContext::RESOLVE_MODE_ALL)
            : Reader::readFromYamlFile(fileName: realpath($content), resolveReferences: ReferenceContext::RESOLVE_MODE_ALL)
    );
}
```

2. **Register Your Custom Parser:**

To make your custom parser available in the SDK Generator, you need to register it using the `Factory`
class's `registerParser` method.

```php
use Crescat\SaloonSdkGenerator\Parsers\Factory;
use YourNamespace\CustomParser; // Replace with the actual namespace of your custom parser

// Register your custom parser
Factory::registerParser('custom', CustomParser::class);
```

3. **Use Your Custom Parser:**

Once registered, you can use your custom parser just like any other built-in parser. Specify `'custom'` as the `--type`
option when generating the SDK, and the SDK Generator will use your custom parser to process the API specification.

```shell
./codegen generate:sdk API_SPEC_FILE.xxx --type=custom
```

Replace `API_SPEC_FILE.xxx` with the path to your custom API specification file.

Now your custom parser is seamlessly integrated into the Saloon SDK Generator, allowing you to generate SDKs from API
specifications in your custom format.

## Tested with Real API Specifications Samples

To showcase the capabilities of the Saloon SDK Generator and to ensure its compatibility with real-world API
specifications, we have tested it with the following API specs.

- [Paddle](https://developer.paddle.com/api-reference/overview) (*Not publicly available*)
- [Stripe](https://www.postman.com/stripedev/workspace/stripe-developers/collection/665823-fb030f33-dcb4-4475-a812-968d7d449fa4) (
  *Postman*)
- [Tableau](https://www.postman.com/salesforce-developers/workspace/salesforce-developers/collection/12721794-7d783742-165f-4d10-8c4c-5719fb60fba2) (
  *Postman*)
- [OpenAI](https://www.postman.com/devrel/workspace/openai/api/7e57d35c-a167-487d-bbf2-51b11576a0d8) (*Postman*)
- [Fiken](https://api.fiken.no/api/v2/docs/swagger.yaml) (*OpenApi*)
- [GoCardless](https://bankaccountdata.gocardless.com/api/swagger.json) (*OpenApi*)
- [Tripletex](https://tripletex.no/v2/swagger.json) (*OpenApi*)

### Generate SDKs

To generate SDKs from the provided API specs, you can use the following commands:

```shell
./codegen build:paddle
./codegen build:stripe
./codegen build:tableau
./codegen build:openai
./codegen build:fiken
./codegen build:gocardless
./codegen build:tripletex
```

### Generate Zip Archives

For your convenience, you can also generate zip archives of the SDKs:

```shell
./codegen build:zip:paddle
./codegen build:zip:stripe
./codegen build:zip:tableau
./codegen build:zip:openai
./codegen build:zip:fiken
./codegen build:zip:gocardless
./codegen build:zip:tripletex
```

Feel free to experiment with these commands to see how the Saloon SDK Generator transforms API specifications into PHP
SDKs. While these tests provide valuable insights, keep in mind that compatibility may vary depending on the complexity
of your specific API specification.

## Reporting Incompatibilities

We understand that compatibility issues may arise when using the Saloon SDK Generator with various API specifications.
While we welcome reports about these incompatibilities, it's important to note that this tool was initially developed
for our internal use and has been open-sourced to share with the community.

If you encounter issues or incompatibilities while generating SDKs from your API specifications, we encourage you to
report them on our [GitHub Issue Tracker](https://github.com/crescat-io/saloon-sdk-generator/issues). Your feedback is
valuable and can help us improve the tool over time.

However, please understand that due to the nature of the project and our own priorities, we may not always be able to
implement immediate fixes for reported issues.

We appreciate your understanding and your interest in using the Saloon SDK Generator. Your contributions, feedback, and
reports will contribute to the ongoing development and improvement of this tool for the broader community.

## Links and References

- [Postman Collection Format](https://learning.postman.com/collection-format/getting-started/structure-of-a-collection/)
- [Postman Collection Format Schema](https://blog.postman.com/introducing-postman-collection-format-schema/)
- [Importing and Exporting Data in Postman](https://learning.postman.com/docs/getting-started/importing-and-exporting/exporting-data/)
- [OpenAPI Specification](https://swagger.io/specification/)

## Contributing

Contributions to this package are welcome! If you find any issues or want to suggest improvements, please submit a pull
request or open an issue on the [GitHub repository](link-to-your-repo).

## Credits

This package is built on the shoulders of giants, special thanks to the following people for their open source work that
helps us all build better software! ❤️

- [Nette PHP Generator](https://github.com/nette/php-generator)
- [Nuno Maduro for Laravel Zero](https://github.com/laravel-zero/laravel-zero)
- [Sam Carré for Saloon](https://github.com/Sammyjo20)

## Built by Crescat

[Crescat.io](https://crescat.io/products/) is a collaborative software designed for venues, festivals, and event
professionals.

With a comprehensive suite of features such as day sheets, checklists, reporting, and crew member booking, Crescat
simplifies event management. Professionals in the live event industry trust Crescat to streamline their workflows,
reducing the need for multiple tools and outdated spreadsheets.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
