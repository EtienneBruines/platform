<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\ORM\Search\EntitySearchResult;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface ResponseTypeInterface
{
    public function supportsContentType(string $contentType): bool;

    public function createDetailResponse(Entity $entity, string $definition, Request $request, Context $context, bool $setLocationHeader = false): Response;

    public function createListingResponse(EntitySearchResult $searchResult, string $definition, Request $request, Context $context): Response;

    public function createRedirectResponse(string $definition, string $id, Request $request, Context $context): Response;
}
