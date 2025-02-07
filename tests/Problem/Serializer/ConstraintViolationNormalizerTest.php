<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ApiPlatform\Tests\Problem\Serializer;

use ApiPlatform\Problem\Serializer\ConstraintViolationListNormalizer;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Serializer\NameConverter\AdvancedNameConverterInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ConstraintViolationNormalizerTest extends TestCase
{
    use ExpectDeprecationTrait;
    use ProphecyTrait;

    /**
     * @group legacy
     */
    public function testSupportNormalization(): void
    {
        $nameConverterProphecy = $this->prophesize(NameConverterInterface::class);
        $normalizer = new ConstraintViolationListNormalizer([], $nameConverterProphecy->reveal());

        $this->assertTrue($normalizer->supportsNormalization(new ConstraintViolationList(), ConstraintViolationListNormalizer::FORMAT));
        $this->assertFalse($normalizer->supportsNormalization(new ConstraintViolationList(), 'xml'));
        $this->assertFalse($normalizer->supportsNormalization(new \stdClass(), ConstraintViolationListNormalizer::FORMAT));
        $this->assertEmpty($normalizer->getSupportedTypes('json'));
        $this->assertSame([ConstraintViolationListInterface::class => true], $normalizer->getSupportedTypes($normalizer::FORMAT));

        if (!method_exists(Serializer::class, 'getSupportedTypes')) {
            $this->assertTrue($normalizer->hasCacheableSupportsMethod());
        }
    }

    /**
     * @group legacy
     *
     * @dataProvider nameConverterProvider
     */
    public function testNormalize(callable $nameConverterFactory, array $expected): void
    {
        $this->expectDeprecation('Since api-platform 3.2: "ApiPlatform\Serializer\AbstractConstraintViolationListNormalizer::ApiPlatform\Serializer\AbstractConstraintViolationListNormalizer::getMessagesAndViolations" will be removed in 4.0, use "ApiPlatform\Serializer\AbstractConstraintViolationListNormalizer::getViolations');
        $normalizer = new ConstraintViolationListNormalizer(['severity', 'anotherField1'], $nameConverterFactory($this));

        // Note : we use NotNull constraint and not Constraint class because Constraint is abstract
        $constraint = new NotNull();
        $constraint->payload = ['severity' => 'warning', 'anotherField2' => 'aValue'];
        $list = new ConstraintViolationList([
            new ConstraintViolation('a', 'b', [], 'c', 'd', 'e', null, 'f24bdbad0becef97a6887238aa58221c', $constraint),
            new ConstraintViolation('1', '2', [], '3', '4', '5'),
        ]);

        $this->assertSame($expected, $normalizer->normalize($list));
    }

    public static function nameConverterProvider(): iterable
    {
        $expected = [
            'type' => 'https://tools.ietf.org/html/rfc2616#section-10',
            'title' => 'An error occurred',
            'detail' => "_d: a\n_4: 1",
            'violations' => [
                [
                    'propertyPath' => '_d',
                    'message' => 'a',
                    'code' => 'f24bdbad0becef97a6887238aa58221c',
                    'payload' => [
                        'severity' => 'warning',
                    ],
                ],
                [
                    'propertyPath' => '_4',
                    'message' => '1',
                    'code' => null,
                ],
            ],
        ];

        $nameConverterFactory = function (self $that): NameConverterInterface {
            $nameConverterProphecy = $that->prophesize(NameConverterInterface::class);
            $nameConverterProphecy->normalize(Argument::type('string'))->will(fn ($args) => '_'.$args[0]);

            return $nameConverterProphecy->reveal();
        };
        yield [$nameConverterFactory, $expected];

        $nameConverterFactory = function (self $that): NameConverterInterface {
            $nameConverterProphecy = $that->prophesize(AdvancedNameConverterInterface::class);
            $nameConverterProphecy->normalize(Argument::type('string'), null, Argument::type('string'))->will(
                fn ($args) => '_'.$args[0]
            );

            return $nameConverterProphecy->reveal();
        };
        yield [$nameConverterFactory, $expected];

        $expected = [
            'type' => 'https://tools.ietf.org/html/rfc2616#section-10',
            'title' => 'An error occurred',
            'detail' => "d: a\n4: 1",
            'violations' => [
                [
                    'propertyPath' => 'd',
                    'message' => 'a',
                    'code' => 'f24bdbad0becef97a6887238aa58221c',
                    'payload' => [
                        'severity' => 'warning',
                    ],
                ],
                [
                    'propertyPath' => '4',
                    'message' => '1',
                    'code' => null,
                ],
            ],
        ];
        yield [fn () => null, $expected];
    }
}
