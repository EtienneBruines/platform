<?php declare(strict_types=1);

namespace Shopware\ProductMedia\Struct;

use Shopware\Framework\Struct\Struct;
use Shopware\Media\Struct\MediaBasicStruct;

class ProductMediaBasicStruct extends Struct
{
    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var string
     */
    protected $productUuid;

    /**
     * @var int
     */
    protected $isCover;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var string|null
     */
    protected $productDetailUuid;

    /**
     * @var string
     */
    protected $mediaUuid;

    /**
     * @var string|null
     */
    protected $parentUuid;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var MediaBasicStruct|null
     */
    protected $media;

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getProductUuid(): string
    {
        return $this->productUuid;
    }

    public function setProductUuid(string $productUuid): void
    {
        $this->productUuid = $productUuid;
    }

    public function getIsCover(): int
    {
        return $this->isCover;
    }

    public function setIsCover(int $isCover): void
    {
        $this->isCover = $isCover;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getProductDetailUuid(): ?string
    {
        return $this->productDetailUuid;
    }

    public function setProductDetailUuid(?string $productDetailUuid): void
    {
        $this->productDetailUuid = $productDetailUuid;
    }

    public function getMediaUuid(): string
    {
        return $this->mediaUuid;
    }

    public function setMediaUuid(string $mediaUuid): void
    {
        $this->mediaUuid = $mediaUuid;
    }

    public function getParentUuid(): ?string
    {
        return $this->parentUuid;
    }

    public function setParentUuid(?string $parentUuid): void
    {
        $this->parentUuid = $parentUuid;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getMedia(): ?MediaBasicStruct
    {
        return $this->media;
    }

    public function setMedia(?MediaBasicStruct $media): void
    {
        $this->media = $media;
    }
}