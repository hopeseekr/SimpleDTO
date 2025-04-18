<?php declare(strict_types=1);

/**
 * This file is part of SimpleDTO, a PHP Experts, Inc., Project.
 *
 * Copyright © 2019-2025 PHP Experts, Inc.
 * Author: Theodore R. Smith <theodore@phpexperts.pro>
 *   GPG Fingerprint: 4BF8 2613 1C34 87AC D28F  2AD8 EB24 A91D D612 5690
 *   https://www.phpexperts.pro/
 *   https://github.com/PHPExpertsInc/SimpleDTO
 *
 * This file is licensed under the MIT License.
 */

namespace PHPExperts\SimpleDTO\Tests;

use Carbon\Carbon;
use Error;
use PHPExperts\DataTypeValidator\InvalidDataTypeException;
use PHPExperts\SimpleDTO\IgnoreAsDTO;
use PHPExperts\SimpleDTO\SimpleDTO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/** @testdox PHPExperts\SimpleDTO\SimpleDTO */
#[TestDox('PHPExperts\SimpleDTO\SimpleDTO')]
final class SimpleDTOTest extends TestCase
{
    /** @var SimpleDTO */
    private $dto;

    protected function setUp(): void
    {
        try {
            $this->dto = new MyTypedPropertyTestDTO([
                'name' => 'World',
                'age'  => 4.51 * 1000000000,
                'year' => 1981,
            ]);
        } catch (InvalidDataTypeException $t) {
            dd($t->getReasons());
        }

        parent::setUp();
    }

    public function testPropertiesAreSetViaTheConstructor(): void
    {
        self::assertInstanceOf(SimpleDTO::class, $this->dto);
        self::assertInstanceOf(MyTypedPropertyTestDTO::class, $this->dto);
    }

    public function testPropertiesAreAccessedAsPublicProperties(): void
    {
        self::assertEquals('World', $this->dto->name);
    }

    /** @testdox Public, private and static protected properties will be ignored  */
    #[TestDox('Public, private and static protected properties will be ignored')]
    public function testPublicStaticAndPrivatePropertiesWillBeIgnored(): void
    {
        /**
         * Every public and private property is ignored, as are static protected ones.
         *
         * @property string $name
         */
        $dto = new class(['name' => 'Bharti Kothiyal']) extends SimpleDTO
        {
            protected $name;

            private $age = 27;

            public $country = 'India';

            protected static $employer = 'N/A';
        };

        $expected = [
            'name' => 'Bharti Kothiyal',
        ];

        self::assertSame($expected, $dto->toArray());
    }

    /** @testdox Each DTO is immutable */
    #[TestDox('Each DTO is immutable')]
    public function testEachDTOIsImmutable(): void
    {
        $this->testSettingAnyPropertyReturnsAnException();
    }

    public function testSettingAnyPropertyReturnsAnException(): void
    {
        try {
            $this->dto->name = 'asdf';
            $this->fail('Setting a property did not throw an error.');
        } catch (Error $e) {
            self::assertEquals(
                'SimpleDTOs are immutable. Create a new DTO to set a new value.',
                $e->getMessage()
            );
        }
    }

    private function buildDateDTO(array $values = ['remember' => '2001-09-11 8:46 EST']): SimpleDTO
    {
        /**
         * @property string $name
         * @property Carbon $remember
         */
        return new class($values) extends SimpleDTO
        {
            /** @var string */
            protected $name = '9/11';

            /** @var Carbon */
            protected $remember;
        };
    }

    public function testConcretePropertiesCanBeUsedToSetDefaultValues(): void
    {
        $dateDTO = $this->buildDateDTO();

        self::assertEquals('9/11', $dateDTO->name);
    }

    public function testPropertiesWithTheTypeCarbonBecomeCarbonDates(): void
    {
        $dateDTO = $this->buildDateDTO();

        self::assertInstanceOf(Carbon::class, $dateDTO->remember);
        self::assertEquals('September 11th, 2001', $dateDTO->remember->format('F jS, Y'));
        self::assertIsString($dateDTO->name);
        self::assertEquals('9/11', $dateDTO->name);
    }

    public function testCanEasilyOutputToArray(): void
    {
        $expected = [
            'name'     => 'Challenger Disaster',
            'remember' => Carbon::createFromDate('January 28 1986 11:39 EST'),
        ];

        $dateDTO = $this->buildDateDTO($expected);

        $actual = $dateDTO->toArray();
        self::assertIsArray($actual);
        self::assertEquals($expected, $actual);
    }

    public function testCanEasilyBeJsonEncoded(): void
    {
        $expected = '{"name":"9\/11","remember":"2001-09-11T13:46:00.000000Z"}';
        $dateDTO = $this->buildDateDTO();

        self::assertEquals($expected, json_encode($dateDTO));
    }

    public function testCanEasilyBeJsonDecoded(): void
    {
        $json = '{"name":"9\/11","remember":"2001-09-11T13:46:00.000000Z"}';
        $dateDTO = $this->buildDateDTO(json_decode($json, true));

        self::assertInstanceOf(Carbon::class, $dateDTO->remember);
        self::assertEquals('September 11th, 2001', $dateDTO->remember->format('F jS, Y'));
        self::assertIsString($dateDTO->name);
        self::assertEquals('9/11', $dateDTO->name);
    }

    public function testNullablePropertiesAreAllowed(): void
    {
        try {
            /**
             * Every public and private property is ignored, as are static protected ones.
             *
             * @property string $firstName
             * @property ?int $age
             * @property null|int $year
             * @property null|string $lastName
             * @property ?float $height
             */
            new class(['firstName' => 'Cheyenne', 'lastName' => 3, 'height' => 'asdf']) extends SimpleDTO
            {
            };

            $this->fail('A DTO was created with invalid nullable properties.');
        } catch (InvalidDataTypeException $e) {
            $expected = [
                'lastName' => 'lastName is not a valid string',
                'height'   => 'height is not a valid float',
            ];

            self::assertSame($expected, $e->getReasons());
        }
    }

    /** @testdox Every property is nullable with Permissive Move */
    #[TestDox('Every property is nullable with Permissive Move')]
    public function testEveryPropertyIsNullableWithPermissiveMode(): void
    {
        $testNonNullabbleWithNulls = function () {
            $info = ['firstName' => 'Nataly', 'lastName' => null, 'age' => null, 'height' => null];

            /**
             * Every public and private property is ignored, as are static protected ones.
             *
             * @property string $firstName
             * @property string $lastName
             * @property int    $age
             * @property float  $height
             */
            $dto = new class($info, [SimpleDTO::PERMISSIVE]) extends SimpleDTO {
            };

            $expected = [
                'firstName' => 'Nataly',
                'lastName'  => null,
                'age'       => null,
                'height'    => null,
            ];

            self::assertSame($expected, $dto->toArray());
        };
        $testNonNullabbleWithNulls();

        $testNullabbleWithNulls = function () {
            $info = ['firstName' => 'Nataly', 'lastName' => null, 'age' => null, 'height' => null];

            try {
                /**
                 * Every public and private property is ignored, as are static protected ones.
                 *
                 * @property ?string $firstName
                 * @property ?string $lastName
                 * @property ?int    $age
                 * @property ?float  $height
                 */
                $dto = new class($info, [SimpleDTO::PERMISSIVE]) extends SimpleDTO {
                    protected int $year = 1988;
                };
            } catch (\Throwable $t) {
                dd($t->getMessage());
            }

            $expected = [
                'year'      => 1988,
                'firstName' => 'Nataly',
                'lastName'  => null,
                'age'       => null,
                'height'    => null,
            ];

            self::assertSame($expected, $dto->toArray());
        };
        $testNullabbleWithNulls();
    }

    private function getSerializedDTO(): string
    {
        return 'O:49:"PHPExperts\SimpleDTO\Tests\MyTypedPropertyTestDTO":4:{s:3:"isA";s:45:"PHPExperts\DataTypeValidator\IsAFuzzyDataType";s:7:"options";a:1:{i:0;i:101;}s:9:"dataRules";a:3:{s:4:"name";s:6:"string";s:3:"age";s:5:"float";s:4:"year";s:3:"int";}s:4:"data";a:3:{s:4:"year";i:2019;s:4:"name";s:5:"World";s:3:"age";s:10:"4510000000";}}';
    }

    private function getSerializedDTOv1(): array
    {
        $expectedJSON = <<<'JSON'
{
    "isA": "PHPExperts\\DataTypeValidator\\IsAFuzzyDataType",
    "options": [
        101
    ],
    "dataRules": {
        "name": "string",
        "age": "float",
        "year": "int"
    },
    "data": {
        "year": 2019,
        "name": "World",
        "age": "4510000000"
    }
}
JSON;

        return json_decode($expectedJSON, true);
    }

    public function testCanBeSerialized(): SimpleDTO
    {
        $dto = new MyTypedPropertyTestDTO([
            'year' => 2019,
            'name' => 'World',
            'age'  => (string) (4.51 * 1000000000),
        ], [SimpleDTO::PERMISSIVE]);

        $expected = $this->getSerializedDTO();

        self::assertSame($expected, serialize($dto));

        return $dto;
    }

    /** @depends testCanBeSerialized */
    #[Depends('testCanBeSerialized')]
    public function testCanBeUnserialized(SimpleDTO $origDTO): void
    {
        $serializedJSON = $this->getSerializedDTO();
        $awokenDTO = unserialize($serializedJSON);

        self::assertEquals($origDTO->toArray(), $awokenDTO->toArray());
    }

    public function testExtraValidationCanBeAdded(): void
    {
        try {
            /**
             * @property string $name
             * @property ?float $age
             */
            new class(['name' => 'Theodore R. Smith']) extends SimpleDTO
            {
                protected function extraValidation(array $input): void
                {
                    $ifThisThenThat = [$this, 'ifThisThenThat'];
                    $ifThisThenThat($input, 'name', 'Theodore R. Smith', 'age');
                }
            };
            $this->fail('A DTO with invalid extra validation was created.');
        } catch (InvalidDataTypeException $e) {
            self::assertStringContainsString('$age must be set when self::$name is ', $e->getMessage());
        }

        /**
         * @property string $name
         * @property ?float $age
         */
        $dto = new class(['name' => 'Theodore R. Smith', 'age' => 37.426]) extends SimpleDTO
        {
            protected function extraValidation(array $input): void
            {
                $ifThisThenThat = [$this, 'ifThisThenThat'];
                $ifThisThenThat($input, 'name', 'Theodore R. Smith', 'age');
            }
        };

        $expected = [
            'name' => 'Theodore R. Smith',
            'age'  => 37.426,
        ];

        self::assertInstanceOf(SimpleDTO::class, $dto);
        self::assertSame(37.426, $dto->age);
        self::assertSame($expected, $dto->toArray());
    }

    public function testCanGetTheInternalData(): void
    {
        $dateDTO = $this->buildDateDTO();
        $expected = [
            'name' => '9/11',
            'remember' => Carbon::parse('2001-09-11 8:46 EST'),
        ];

        self::assertEquals($expected, $dateDTO->getData());
    }

    public function testCanIdentifyIfItIsPermissiveOrNot(): void
    {
        $dateDTO = $this->buildDateDTO();
        self::assertFalse($dateDTO->isPermissive());
    }

    /** @dataProvider provideTestCases */
    #[DataProvider('provideTestCases')]
    public function testConvertValueToArray($input, $expected): void
    {
        $dto = new class([], []) extends SimpleDTO {};

        $reflection = new \ReflectionClass($dto);
        $method = $reflection->getMethod('convertValueToArray');
        $method->setAccessible(true);

        $output = $method->invokeArgs($dto, [$input]);
        self::assertEquals($expected, $output);
    }

    public static function provideTestCases(): array
    {
        return [
            // An array with values that aren't objects.
            [[1, 2, 3], null],

            // An array with values that are objects.
            [[new ArrayableObject([1, 2, 3])], [[1, 2, 3]]],

            // An object that has a `toArray()` method.
            [new ArrayableObject([1, 2, 3]), [1, 2, 3]],

            // An instance of the `stdClass` class.
            [(object) ['a' => 1], ['a' => 1]],

            // An instance of the `Carbon` class.
            [Carbon::now(), null],

            // A value that is not an array or an object.
            [1, null],
        ];
    }

    public function testConstructorAssignsDefaultValues(): void
    {
        // Test a property that does not have a value in the input but has a default value.
        $dto = new class([], []) extends SimpleDTO {
            protected string $name = 'default name';
        };
        self::assertEquals(['name' => 'default name'], $dto->toArray());

        // Test a property that does not have a value in the input and does not have a default value.
        try {
            $dto = new class(['name' => null], []) extends SimpleDTO {
                protected ?string $name;
            };
            self::assertEquals(['name' => null], $dto->toArray());
        } catch (InvalidDataTypeException $e) {
            dd($e->getReasons());
        }

        // Test a property that has a value in the input.
        $dto = new class(['name' => 'input name'], []) extends SimpleDTO {
            protected string $name;
        };
        self::assertEquals(['name' => 'input name'], $dto->toArray());
    }

    /** @testdox Can ignore protected properties with the #[IgnoreDTO] Attribute. */
    #[TestDox('Can ignore protected properties with the #[IgnoreDTO] Attribute.')]
    public function testCanIgnoreProtectedPropertiesWithAttribute(): void
    {
        if (method_exists(ReflectionClass::class, 'getAttributes') === false) {
            // Skip the test for PHP versions lower than 8.0 without Attributes support.
            self::assertTrue(true);
            return;
        }

        try {
            $testDTO = new class(['name' => 'Sofia', 'birthYear' => 2010]) extends SimpleDTO {
                #[IgnoreAsDTO]
                protected int $age;

                protected string $name;
                protected int $birthYear;
                public function calcAge(): int
                {
                    $this->age = date('Y') - $this->birthYear;

                    return $this->age;
                }
            };
        } catch (InvalidDataTypeException $e) {
            self::fail("Couldn't create a DTO with an ignored-by-attribute protected property.");
        }

        $expectedResult = [
            'name'      => 'Sofia',
            'birthYear' => 2010
        ];
        $expectedAge = date('Y') - 2010;

        self::assertEquals($expectedResult, $testDTO->toArray());
        self::assertEquals($expectedAge, $testDTO->calcAge());
    }
}

class ArrayableObject
{
    private $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function toArray()
    {
        return $this->data;
    }
}
