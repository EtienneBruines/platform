<?php declare(strict_types=1);

namespace Shopware\Api\Version\Event\VersionCommitData;

use Shopware\Api\Version\Struct\VersionCommitSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class VersionCommitDataSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'version_commit_data.search.result.loaded';

    /**
     * @var VersionCommitSearchResult
     */
    protected $result;

    public function __construct(VersionCommitSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}