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
use Sulu\Product\Domain\Model\ProductInterface;

/**
 * Applies the `sulu_product.route` configuration to the route field of the product form, so a
 * project sets the route type and its params (e.g. `route_schema`) once instead of per form.
 *
 * @internal
 */
class ProductRouteFormMetadataVisitor implements FormMetadataVisitorInterface
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

        $routeField->setType($this->type);

        foreach ($this->params as $name => $value) {
            $option = new OptionMetadata();
            $option->setName($name);
            $option->setValue($value);

            // addOption is keyed by name, so a configured param replaces the one of the form
            $routeField->addOption($option);
        }
    }
}
