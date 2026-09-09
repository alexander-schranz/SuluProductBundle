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

namespace Sulu\Product\Infrastructure\Sulu\Admin;

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataVisitorInterface;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TagMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadataVisitorInterface;
use Sulu\Content\Application\ContentDataMapper\DataMapper\TemplateDataMapper;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;

/**
 * Applies the `sulu_product.route` configuration to the route field of the product forms, so a
 * project sets the route type and its params (e.g. `route_schema`) once instead of per form.
 *
 * The product templates get the same field invisibly, because RoutableDataMapper reads the route
 * property off the template metadata while the editable field lives in the product forms.
 *
 * @internal
 */
class ProductRouteFormMetadataVisitor implements FormMetadataVisitorInterface, TypedFormMetadataVisitorInterface
{
    private const FORM_KEYS = [ProductInterface::FORM_KEY, ProductInterface::FORM_KEY_VARIANT];

    private const FIELD_NAME = 'url';

    /**
     * @param array<string, scalar|null> $params
     */
    public function __construct(
        private readonly string $type,
        private readonly array $params,
    ) {
    }

    public function visitFormMetadata(FormMetadata $formMetadata, string $locale, array $metadataOptions = []): void
    {
        if (!\in_array($formMetadata->getKey(), self::FORM_KEYS, true)) {
            return;
        }

        $routeField = $formMetadata->getItems()[self::FIELD_NAME] ?? null;
        if (!$routeField instanceof FieldMetadata) {
            return;
        }

        $this->applyRouteConfig($routeField);
    }

    public function visitTypedFormMetadata(TypedFormMetadata $formMetadata, string $key, string $locale, array $metadataOptions = []): void
    {
        if (ProductDimensionContent::getTemplateType() !== $key) {
            return;
        }

        foreach ($formMetadata->getForms() as $form) {
            $routeField = $form->getItems()[self::FIELD_NAME] ?? null;

            if (!$routeField instanceof FieldMetadata) {
                $routeField = new FieldMetadata(self::FIELD_NAME);
                $routeField->setVisibleCondition('false');
                $form->addItem($routeField);
            }

            $this->applyRouteConfig($routeField);
            $this->skipTemplateData($routeField);
        }
    }

    private function applyRouteConfig(FieldMetadata $routeField): void
    {
        $routeField->setType($this->type);

        foreach ($this->params as $name => $value) {
            $option = new OptionMetadata();
            $option->setName($name);
            $option->setValue($value);

            // addOption is keyed by name, so a configured param replaces the one of the form
            $routeField->addOption($option);
        }
    }

    /**
     * The slug belongs to the route entity. Tagged here rather than left to the visitor of the
     * content package, whose order against this one is not defined.
     */
    private function skipTemplateData(FieldMetadata $routeField): void
    {
        if ($routeField->hasTag(TemplateDataMapper::SKIP_TAG)) {
            return;
        }

        $tag = new TagMetadata();
        $tag->setName(TemplateDataMapper::SKIP_TAG);
        $routeField->addTag($tag);
    }
}
