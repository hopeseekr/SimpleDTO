<?php declare(strict_types=1);

/**
 * This file is part of SimpleDTO, a PHP Experts, Inc., Project.
 *
 * Copyright © 2019 PHP Experts, Inc.
 * Author: Theodore R. Smith <theodore@phpexperts.pro>
 *  GPG Fingerprint: 4BF8 2613 1C34 87AC D28F  2AD8 EB24 A91D D612 5690
 *  https://www.phpexperts.pro/
 *  https://github.com/phpexpertsinc/Zuora-API-Client
 *
 * This file is licensed under the MIT License.
 */

namespace PHPExperts\SimpleDTO\Tests;

use Carbon\Carbon;
use Error;
use PHPExperts\DataTypeValidator\InvalidDataTypeException;
use PHPExperts\SimpleDTO\NestedDTO;
use PHPExperts\SimpleDTO\SimpleDTO;
use PHPUnit\Framework\TestCase;

/** @testdox PHPExperts\SimpleDTO\NestedDTO */
final class NestedDTOTest extends TestCase
{
    /** @var SimpleDTO */
    private $dto;

    /** @testdox Will construct snested DTOs */
    function testWillBuildOutNestedDTOs()
    {
        $myDTO = new MyTestDTO([
            'name' => 'PHP Experts, Inc.',
            'age'  => 7.01,
            'year' => 2019,
        ]);

        /**
         * @property MyTestDTO $myDTO
         */
        $nestedDTO = new class(['myDTO' => $myDTO], ['myDTO' => MyTestDTO::class]) extends NestedDTO
        {
        };

        $expected = [
            'myDTO' => [
                'name' => 'PHP Experts, Inc.',
                'age'  => 7.01,
                'year' => 2019,
            ],
        ];

        self::assertSame($expected, $nestedDTO->toArray());
    }

    /** @testdox Will convert arrays into the appropriate Nested DTOs */
    function testWillConvertArraysIntoTheAppropriateNestedDTOs()
    {
        try {
            $myDTO = [
                'name' => 'PHP Experts, Inc.',
                'age'  => 7.2,
                'year' => 2012,
            ];
        } catch (InvalidDataTypeException $e) {
            dd($e->getReasons());
        }

        /**
         * @property MyTestDTO $myDTO
         */
        $nestedDTO = new class(['myDTO' => $myDTO], ['myDTO' => MyTestDTO::class]) extends NestedDTO
        {
        };

        $expected = [
            'myDTO' => [
                'name' => 'PHP Experts, Inc.',
                'age'  => 7.2,
                'year' => 2012,
            ],
        ];

        self::assertSame($expected, $nestedDTO->toArray());
    }

    /** @testdox Will convert stdClasses into the appropriate Nested DTOs */
    function testWillConvertStdClassesIntoTheAppropriateNestedDTOs()
    {
        try {
            $myDTO = (object) [
                'name' => 'PHP Experts, Inc.',
                'age'  => 7.2,
                'year' => 2012,
            ];
        } catch (InvalidDataTypeException $e) {
            dd($e->getReasons());
        }

        /**
         * @property MyTestDTO $myDTO
         */
        $nestedDTO = new class(['myDTO' => $myDTO], ['myDTO' => MyTestDTO::class]) extends NestedDTO
        {
        };

        $expected = [
            'myDTO' => [
                'name' => 'PHP Experts, Inc.',
                'age'  => 7.2,
                'year' => 2012,
            ],
        ];

        self::assertSame($expected, $nestedDTO->toArray());
    }

    /** @testdox Nested DTOs use Loose typing */
    function testNestedDTOsUseLooseTyping()
    {
        try {
            $myDTOInfo = [
                'name'  => 'PHP Experts, Inc.',
                'age'   => null,
                'year'  => '2019',
                'extra' => true,
            ];
        } catch (InvalidDataTypeException $e) {
            dd($e->getReasons());
        }

        /**
         * @property MyTestDTO $myDTO
         */
        $nestedDTO = new class(['myDTO' => $myDTOInfo], ['myDTO' => MyTestDTO::class]) extends NestedDTO
        {
        };

        $expected = [
            'myDTO' => [
                'name'  => 'PHP Experts, Inc.',
                'age'   => null,
                'year'  => '2019',
                'extra' => true,
            ],
        ];

        self::assertSame($expected, $nestedDTO->toArray());
    }

    /** @testdox All registered Nested DTOs are required */
    public function testAllRegisteredNestedDTOsAreRequired()
    {
        $myDTO = new MyTestDTO([
            'name' => 'PHP Experts, Inc.',
            'age'  => 7.01,
            'year' => 2019,
        ]);

        try {
            /**
             * @property MyTestDTO $myDTO
             */
            $dto = new class(['myDTO' => $myDTO], ['myDTO' => MyTestDTO::class, 'missing' => MyTestDTO::class]) extends NestedDTO
            {
            };

            $this->fail('A nested DTO was created without all of the required DTOs.');
        } catch (InvalidDataTypeException $e) {
            self::assertSame('Missing critical DTO input(s).', $e->getMessage());
            self::assertSame(['missing' => MyTestDTO::class], $e->getReasons());
        }
    }

    /** @testdox Optional, unregistered, Nested DTOs are handled gracefully */
    public function testOptionalUnregisteredNestedDTOsAreHandledGracefully()
    {
        $myDTO = (object) [
            'name' => 'PHP Experts, Inc.',
            'age'  => 7.01,
            'year' => 2019,
        ];

        /**
         * @property MyTestDTO $myDTO
         */
        $dto = new class(['myDTO' => $myDTO, 'extra' => $myDTO], ['myDTO' => MyTestDTO::class]) extends NestedDTO
        {
        };

        $expectedArray = [
            'myDTO' => [
                'name' => 'PHP Experts, Inc.',
                'age'  => 7.01,
                'year' => 2019,
            ],
            'extra' => [
                'name' => 'PHP Experts, Inc.',
                'age'  => 7.01,
                'year' => 2019,
            ],
        ];

        $expectedObject = (object) [
            'name' => 'PHP Experts, Inc.',
            'age'  => 7.01,
            'year' => 2019,
        ];

        self::assertSame($expectedArray, $dto->toArray());
        self::assertInstanceOf(MyTestDTO::class, $dto->myDTO);
        self::assertInstanceOf('\stdClass', $dto->extra);
        self::assertEquals($expectedObject, $dto->extra);
    }
}