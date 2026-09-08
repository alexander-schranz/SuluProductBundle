<?php

declare(strict_types=1);

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Admin;

use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductRouteFormMetadataVisitor;

class ProductRouteFormMetadataVisitorTest extends TestCase
{
    public function testAppliesConfiguredTypeAndParams(): void
    {
        $routeField = new FieldMetadata('url');
        $routeField->setType('route');

        $form = new FormMetadata();
        $form->setKey('product_details');
        $form->addItem($routeField);

        $visitor = new ProductRouteFormMetadataVisitor('page_tree_route', [
            'route_schema' => '/products/{implode(\'-\', object)}',
            'available_locales' => true,
        ]);
        $visitor->visitFormMetadata($form, 'en', []);

        self::assertSame('page_tree_route', $routeField->getType());

        $options = $routeField->getOptions();
        self::assertArrayHasKey('route_schema', $options);
        self::assertSame('/products/{implode(\'-\', object)}', $options['route_schema']->getValue());
        self::assertArrayHasKey('available_locales', $options);
        self::assertTrue($options['available_locales']->getValue());
    }

    public function testConfiguredParamReplacesTheOneOfTheForm(): void
    {
        $routeField = new FieldMetadata('url');
        $routeField->setType('route');

        $formOption = new OptionMetadata();
        $formOption->setName('route_schema');
        $formOption->setValue('/{implode(\'-\', object)}');
        $routeField->addOption($formOption);

        $form = new FormMetadata();
        $form->setKey('product_details');
        $form->addItem($routeField);

        $visitor = new ProductRouteFormMetadataVisitor('route', ['route_schema' => '/products/{object[\'code\']}']);
        $visitor->visitFormMetadata($form, 'en', []);

        self::assertSame('/products/{object[\'code\']}', $routeField->getOptions()['route_schema']->getValue());
    }

    public function testKeepsFormParamsWhichAreNotConfigured(): void
    {
        $routeField = new FieldMetadata('url');
        $routeField->setType('route');

        $formOption = new OptionMetadata();
        $formOption->setName('mode');
        $formOption->setValue('leaf');
        $routeField->addOption($formOption);

        $form = new FormMetadata();
        $form->setKey('product_details');
        $form->addItem($routeField);

        $visitor = new ProductRouteFormMetadataVisitor('route', ['route_schema' => '/products/{object[\'code\']}']);
        $visitor->visitFormMetadata($form, 'en', []);

        self::assertSame('leaf', $routeField->getOptions()['mode']->getValue());
    }

    public function testAppliesConfiguredTypeAndParamsToVariantOverlay(): void
    {
        $routeField = new FieldMetadata('url');
        $routeField->setType('route');

        $form = new FormMetadata();
        $form->setKey('product_variant');
        $form->addItem($routeField);

        $visitor = new ProductRouteFormMetadataVisitor('page_tree_route', [
            'route_schema' => '/products/{implode(\'-\', object)}',
        ]);
        $visitor->visitFormMetadata($form, 'en', []);

        self::assertSame('page_tree_route', $routeField->getType());
        self::assertSame('/products/{implode(\'-\', object)}', $routeField->getOptions()['route_schema']->getValue());
    }

    public function testIgnoresOtherForms(): void
    {
        $routeField = new FieldMetadata('url');
        $routeField->setType('route');

        $form = new FormMetadata();
        $form->setKey('some_other_form');
        $form->addItem($routeField);

        $visitor = new ProductRouteFormMetadataVisitor('page_tree_route', ['route_schema' => '/products']);
        $visitor->visitFormMetadata($form, 'en', []);

        self::assertSame('route', $routeField->getType());
        self::assertSame([], $routeField->getOptions());
    }

    public function testIgnoresProductDetailsFormWithoutRouteField(): void
    {
        // A consumer that removed the route field from the form: the visitor must no-op, not blow up.
        $form = new FormMetadata();
        $form->setKey('product_details');

        $visitor = new ProductRouteFormMetadataVisitor('route', ['route_schema' => '/products']);
        $visitor->visitFormMetadata($form, 'en', []);

        self::assertSame([], $form->getItems());
    }
}
