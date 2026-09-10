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

namespace Sulu\Product\Infrastructure\Doctrine\EventListener;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Route\Domain\Model\Route;

/**
 * A product with variants is reached through its variants, so it never owns a route. Dropped on
 * flush rather than in the form, so no programmatic write can leave one behind.
 *
 * @internal
 */
class ProductWithVariantsRouteGuard
{
    public function onFlush(OnFlushEventArgs $eventArgs): void
    {
        $entityManager = $eventArgs->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        $entities = [
            ...$unitOfWork->getScheduledEntityInsertions(),
            ...$unitOfWork->getScheduledEntityUpdates(),
        ];

        foreach ($entities as $entity) {
            if (!$entity instanceof ProductDimensionContentInterface) {
                continue;
            }

            $route = $entity->getRoute();
            if (!$route instanceof Route) {
                continue;
            }

            if (!$entity->getResource()->isType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS)) {
                continue;
            }

            $entity->removeRoute();

            $metadata = $entityManager->getClassMetadata($entity::class);
            if ($unitOfWork->isScheduledForInsert($entity)) {
                $unitOfWork->computeChangeSet($metadata, $entity);
            } else {
                $unitOfWork->recomputeSingleEntityChangeSet($metadata, $entity);
            }

            if ($unitOfWork->isScheduledForInsert($route)) {
                $entityManager->detach($route);
            }
        }
    }
}
